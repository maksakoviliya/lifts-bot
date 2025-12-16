<?php

declare(strict_types=1);

namespace App\Telegram\Callbacks;

use App\Models\Lift;
use App\Telegram\Commands\LiftsCommand;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Objects\CallbackQuery;

class RefreshLiftsCallback
{
    protected Api $telegram;

    public function __construct(Api $telegram)
    {
        $this->telegram = $telegram;
    }

    /**
     * @throws TelegramSDKException
     */
    public function handle(CallbackQuery $callbackQuery): void
    {
        // Показываем уведомление пользователю
        $this->telegram->answerCallbackQuery([
            'callback_query_id' => $callbackQuery->id,
            'text' => '🔄 Обновляем данные...',
            'show_alert' => false
        ]);

        // Получаем обновленные данные
        $text = $this->getLiftsStatus();

        $keyboard = Keyboard::make()
            ->inline()
            ->row([
                Keyboard::inlineButton([
                    'text' => '🔄 Обновить статус',
                    'callback_data' => 'refresh_lifts'
                ])
            ]);

        try {
            // Обновляем сообщение
            $this->telegram->editMessageText([
                'chat_id' => $callbackQuery->message->chat->id,
                'message_id' => $callbackQuery->message->messageId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'reply_markup' => $keyboard
            ]);
        } catch (\Exception $e) {
            // Если сообщение не изменилось, Telegram вернет ошибку
            Log::warning('Telegram edit message error: ' . $e->getMessage());
        }
    }

    protected function getLiftsStatus(): string
    {
        $groups = Lift::query()->get()->groupBy('data.operator');

        if ($groups->isEmpty()) {
            return "Пока нет данных о подъемниках ❗️";
        }

        $output = "🎿 *Статусы подъемников*\n";
        $output .= "_Обновлено: " . now()->format('d.m.Y H:i') . "_";

        foreach ($groups as $key => $group) {
            $output .= "\n\n*$key:*\n";

            $output .= $group->map(function ($lift) {
                return sprintf(
                    "%s %s",
                    $lift->is_active ? '🟢' : '🔴',
                    LiftsCommand::processName($lift->name)
                );
            })->implode("\n");
        }

        return $output . "\n\n" . Cache::get('weather');
    }
}