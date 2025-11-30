<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Users\UsersService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

use function Sentry\captureException;

final class WebhookController extends Controller
{
    // ID канала/группы для проверки подписки
    private const REQUIRED_CHANNEL = '@sheregeshafisha';
    private const CHANNEL_URL = 'https://t.me/sheregeshafisha';

    public function __construct(private readonly UsersService $usersService)
    {
    }

    public function __invoke(Request $request): string
    {
        $update = Telegram::getWebhookUpdate();

        $this->usersService->processUser($update);

        // Получаем ID пользователя из разных типов обновлений
        $userId = $this->getUserId($update);
        $chatId = $this->getChatId($update);

        // Проверяем подписку перед обработкой
        if ($userId && $chatId && !$this->checkSubscription($userId)) {
            $this->sendSubscriptionRequired($chatId);
            return 'ok';
        }

        $callbackQuery = $update->callbackQuery;
        if ($callbackQuery) {
            $this->handleCallbackQuery($callbackQuery, $update);
            return 'ok';
        }

        $update = Telegram::commandsHandler(true);

        Log::info('Обработка обновления', [
            'update' => $update->updateId,
            'message' => $update->getMessage(),
        ]);

        return 'ok';
    }

    /**
     * Проверяет, подписан ли пользователь на требуемый канал
     */
    private function checkSubscription(int $userId): bool
    {
        try {
            $chatMember = Telegram::getChatMember([
                'chat_id' => self::REQUIRED_CHANNEL,
                'user_id' => $userId
            ]);

            $status = $chatMember->getStatus();

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
                            ['text' => '📢 Подписаться на канал', 'url' => self::CHANNEL_URL]
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

    /**
     * Обрабатывает callback query
     */
    private function handleCallbackQuery($callbackQuery, $update): void
    {
        $callbackData = $callbackQuery->data;
        $message = $callbackQuery->message;

        Log::info('Message', [
            'message' => $message
        ]);

        $chatId = $message->chat->id;

        Log::info("Callback received", [
            'data' => $callbackData,
            'chat_id' => $chatId,
        ]);

        // Обработка проверки подписки
        if ($callbackData === 'verify_subscription') {
            $this->verifySubscription($callbackQuery);
            return;
        }

        switch ($callbackData) {
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
            default:
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Неизвестная команда.'
                ]);
                break;
        }
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
            try {
                Telegram::answerCallbackQuery([
                    'callback_query_id' => $callbackQuery->id,
                    'text' => '❌ Вы еще не подписались на канал! Пожалуйста, подпишитесь и попробуйте снова.',
                    'show_alert' => true
                ]);
            } catch (Exception $e) {
                Log::error('Ошибка ответа на callback', [
                    'exception' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Получает ID пользователя из update
     */
    private function getUserId($update): ?int
    {
        if ($update->getMessage() && $update->getMessage()->from) {
            Log::debug('Getting user ID from message' . __METHOD__, [
                '$update->getMessage()' => $update->getMessage(),
            ]);
            return $update->getMessage()->from->id ?? null;
        }

        if ($update->callbackQuery && $update->callbackQuery->from) {
            Log::debug('Getting user ID from callbackQuery' . __METHOD__, [
                '$update->callbackQuery' => $update->callbackQuery,
            ]);
            return $update->callbackQuery->from->id ?? null;
        }

        if ($update->myChatMember && $update->myChatMember->from) {
            Log::debug('Getting user ID from myChatMember' . __METHOD__, [
                '$update->myChatMember' => $update->myChatMember,
            ]);
            return $update->myChatMember->from->id ?? null;
        }
        
        Log::debug('User ID not found in update' . __METHOD__, [
            '$update' => $update,
        ]);

        return null;
    }

    /**
     * Получает ID чата из update
     */
    private function getChatId($update): ?int
    {
        if ($update->getMessage()) {
            return $update->getMessage()->chat->id ?? null;
        }

        if ($update->callbackQuery) {
            return $update->callbackQuery->message->chat->id ?? null;
        }

        return null;
    }

    public function test(Request $request)
    {
//		$groups = Lift::query()->get()->groupBy('data.operator');

//		if ($lifts->isEmpty()) {
//			$this->replyWithMessage([
//				'text' => "Пока нет данных о подъемниках ❗️"
//			]);
//			return;
//		}

//		$output = "🎿 *Статусы подъемников*";
//		
//		foreach ($groups as $key => $group) {
//			$output .= "\n\n*$key:*\n";
//
//			$output .= $group->map(function ($lift) {
//				return sprintf(
//					"%s: %s",
//					$this->processName($lift->name),
//					$lift->is_active ? '✅ Работает' : '❌ Закрыт'
//				);
//			})->implode("\n");
//		}
//
//		
//		dd($output);
    }
}