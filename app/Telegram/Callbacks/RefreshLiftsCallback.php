<?php

declare(strict_types=1);

namespace App\Telegram\Callbacks;

use App\Models\Lift;
use App\Telegram\Commands\LiftsCommand;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Objects\CallbackQuery;

class RefreshLiftsCallback
{
    protected Api $telegram;

    public function __construct(Api $telegram)
    {
        $this->telegram = $telegram;
    }
    
    public function handle(CallbackQuery $callbackQuery): void
    {
        // Сначала сразу отвечаем на callback (это нужно сделать быстро)
        try {
            $this->telegram->answerCallbackQuery([
                'callback_query_id' => $callbackQuery->id,
                'text' => '🔄 Обновляем...',
                'show_alert' => false
            ]);
        } catch (\Exception $e) {
            // Игнорируем ошибки answerCallbackQuery (callback может быть устаревшим)
            Log::warning('Failed to answer callback query: ' . $e->getMessage());
        }

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
        } catch (Exception $e) {
            // Если сообщение не изменилось или другая ошибка
            Log::warning('Failed to edit message: ' . $e->getMessage());
            // Можно попробовать отправить новое сообщение в чат
            // (но только если это не канал, иначе будет спам)
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