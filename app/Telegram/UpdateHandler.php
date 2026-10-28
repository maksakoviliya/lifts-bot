<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Services\Users\UsersService;
use App\Telegram\Callbacks\RefreshLiftsCallback;
use App\Telegram\Commands\WebCamsCommand;
use Exception;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\Update;

use function Sentry\captureException;

/**
 * Handles one Telegram update, whichever way it arrived: webhook or getUpdates polling
 */
final class UpdateHandler
{
    public function __construct(private readonly UsersService $usersService)
    {
    }

    public function handle(Update $update): void
    {
        $this->usersService->processUser($update);

        // Получаем ID пользователя из разных типов обновлений
        $userId = $this->getUserId($update);
        $chat = $update->getChat();
        $chatId = $chat->get('id');
        $callbackQuery = $update->callbackQuery;

        $mustBeSubscribed = $chat->get('type') !== 'channel' // never post the prompt into the channel itself
            && !in_array($chatId, explode(',', (string) config('services.telegram.excluded_chats')))
            && $callbackQuery?->data !== 'verify_subscription';

        // Проверяем подписку перед обработкой
        if ($mustBeSubscribed && $userId && $chatId && !$this->checkSubscription($userId)) {
            $this->sendSubscriptionRequired($chatId);
            return;
        }

        if ($callbackQuery) {
            $this->handleCallbackQuery($callbackQuery, $update);
            return;
        }

        Telegram::processCommand($update);

        Log::info('Обработка обновления', [
            'update' => $update->updateId,
            'message' => $update->getMessage(),
        ]);
    }

    /**
     * Проверяет, подписан ли пользователь на требуемый канал
     */
    private function checkSubscription(int $userId): bool
    {
        try {
            $chatMember = Telegram::getChatMember([
                'chat_id' => config('services.telegram.required_channel'),
                'user_id' => $userId
            ]);

            $status = $chatMember->status;

            Log::debug('Проверка статуса подписки', [
                'user_id' => $userId,
                'status' => $status,
                'chat_member' => $chatMember
            ]);

            // Разрешенные статусы: создатель, администратор, участник
            return in_array($status, ['creator', 'administrator', 'member']);
        } catch (Exception $e) {
            Log::error('Ошибка проверки подписки', [
                'user_id' => $userId,
                'exception' => $e->getMessage()
            ]);

            // В случае ошибки разрешаем доступ, чтобы не блокировать пользователей
            return true;
        }
    }

    /**
     * Отправляет сообщение с требованием подписаться
     */
    private function sendSubscriptionRequired(int $chatId): void
    {
        try {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "❌ *Для использования бота необходимо подписаться на наш канал!*\n\n" .
                    "После подписки нажмите кнопку \"Я подписался\" для проверки.",
                'parse_mode' => 'Markdown',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '📢 Подписаться на канал', 'url' => config('services.telegram.channel_url')]
                        ],
                        [
                            ['text' => '✅ Я подписался', 'callback_data' => 'verify_subscription']
                        ]
                    ]
                ])
            ]);
        } catch (Exception $e) {
            Log::error('Ошибка отправки сообщения о подписке', [
                'chat_id' => $chatId,
                'exception' => $e->getMessage()
            ]);
        }
    }

    private function handleCallbackQuery($callbackQuery, Update $update): void
    {
        $callbackData = $callbackQuery->data;
        $message = $callbackQuery->message;
        $chatId = $message->chat->id;

        Log::info("Callback received", [
            'data' => $callbackData,
            'chat_id' => $chatId,
        ]);

        // these two answer the callback themselves, with their own text
        if ($callbackData !== 'refresh_lifts' && $callbackData !== 'verify_subscription') {
            $this->answerCallbackQuery($callbackQuery->id);
        }

        // Обработка проверки подписки
        if ($callbackData === 'verify_subscription') {
            $this->verifySubscription($callbackQuery);
            return;
        }

        $data = json_decode($callbackData, true);

        if (is_array($data) && isset($data['action'])) {
            $this->handleCameraCallback($data, $chatId);
            return;
        }

        switch ($callbackData) {
            case 'main_menu':
                try {
                    Telegram::triggerCommand('start', $update);
                    Telegram::deleteMessage([
                        'chat_id' => $chatId,
                        'message_id' => $message->messageId
                    ]);
                } catch (Exception $e) {
                    Log::error("Error showing main menu", ['exception' => $e]);
                }
                break;

            case 'show_camera_sectors':
                try {
                    $this->showCameraSectors($chatId);
                } catch (Exception $e) {
                    Log::error("Error show_camera_sectors", [
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    captureException($e);
                }
                break;

            case 'refresh_lifts':
                try {
                    $handler = new RefreshLiftsCallback(Telegram::bot());
                    $handler->handle($callbackQuery);
                } catch (Exception $e) {
                    Log::error("Error refreshing lifts", [
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    captureException($e);

                    // Не пытаемся отвечать на callback если он устарел
                    // просто логируем ошибку
                }
                break;

            case 'lifts':
                try {
                    Telegram::triggerCommand('lifts', $update);
                    Telegram::deleteMessage([
                        'chat_id' => $chatId,
                        'message_id' => $message->messageId
                    ]);
                } catch (Exception $e) {
                    Log::error("Error processing lifts command", [
                        'exception' => $e,
                        'update' => $update,
                    ]);
                    captureException($e);

                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Произошла ошибка при получении статусов подъемников. Пожалуйста, попробуйте позже.'
                    ]);
                }
                break;

            case 'cams':
                try {
                    Telegram::triggerCommand('cams', $update);
                    Telegram::deleteMessage([
                        'chat_id' => $chatId,
                        'message_id' => $message->messageId
                    ]);
                } catch (Exception $e) {
                    Log::error("Error processing cams command", [
                        'exception' => $e,
                        'update' => $update,
                    ]);
                    captureException($e);

                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Произошла ошибка при получении списка камер. Пожалуйста, попробуйте позже.'
                    ]);
                }
                break;

            default:
                // Проверяем, может быть это команда
                if (str_starts_with($callbackData, '/')) {
                    try {
                        Telegram::triggerCommand(substr($callbackData, 1), $update);
                    } catch (Exception $e) {
                        Log::error("Error triggering command", ['exception' => $e]);
                    }
                } else {
                    Telegram::sendMessage([
                        'chat_id' => $chatId,
                        'text' => 'Неизвестная команда. Используйте /start для начала работы.'
                    ]);
                }
                break;
        }
    }

    /**
     * Обработка callback'ов для камер
     */
    private function handleCameraCallback(array $data, int $chatId): void
    {
        $cameraCommand = new WebCamsCommand();

        try {
            switch ($data['action']) {
                case 'show_sector_cameras':
                    if (isset($data['sector'])) {
                        $cameraCommand->showCamerasInSector($chatId, $data['sector']);
                    }
                    break;

                case 'show_camera_details':
                    if (isset($data['camera_id'])) {
                        $cameraCommand->showCameraDetails($chatId, $data['camera_id']);
                    }
                    break;

                case 'show_camera_sectors':
                    $cameraCommand->showSectors($chatId);
                    break;
            }
        } catch (Exception $e) {
            Log::error('Ошибка обработки callback камер', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Произошла ошибка при обработке запроса. Пожалуйста, попробуйте позже.'
            ]);
        }
    }

    /**
     * @throws TelegramSDKException
     */
    private function showCameraSectors($chatId): void
    {
        $cameraCommand = new WebCamsCommand();
        $cameraCommand->showSectors($chatId);
    }

    /**
     * Проверяет подписку при нажатии на кнопку "Я подписался"
     */
    private function verifySubscription($callbackQuery): void
    {
        $userId = $callbackQuery->from->id;
        $chatId = $callbackQuery->message->chat->id;
        $messageId = $callbackQuery->message->messageId;

        if ($this->checkSubscription($userId)) {
            $this->answerCallbackQuery($callbackQuery->id);
            try {
                Telegram::editMessageText([
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                    'text' => "✅ *Отлично!*\n\nВы подписаны на канал. Теперь вы можете пользоваться ботом.\n\nНапишите /start для начала работы.",
                    'parse_mode' => 'Markdown'
                ]);
            } catch (Exception $e) {
                Log::error('Ошибка обновления сообщения', [
                    'exception' => $e->getMessage()
                ]);
            }
        } else {
            $this->answerCallbackQuery($callbackQuery->id, [
                'text' => '❌ Вы еще не подписались на канал! Пожалуйста, подпишитесь и попробуйте снова.',
                'show_alert' => true
            ]);
        }
    }

    /**
     * Убирает "часики" с нажатой кнопки
     */
    private function answerCallbackQuery(string $callbackQueryId, array $params = []): void
    {
        try {
            Telegram::answerCallbackQuery(['callback_query_id' => $callbackQueryId] + $params);
        } catch (Exception $e) {
            // stale callbacks can't be answered anymore, nothing to do about it
            Log::warning('Failed to answer callback query: ' . $e->getMessage());
        }
    }

    /**
     * Получает ID пользователя из update
     */
    private function getUserId(Update $update): ?int
    {
        // For callbacks getMessage() is the bot's own message, so its "from" is the bot, not the user
        return $update->callbackQuery?->from?->id ?? $update->getMessage()->get('from')?->id;
    }
}
