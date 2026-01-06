<?php

declare(strict_types=1);

namespace App\Telegram\Commands;

use App\Models\WebCam;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Exceptions\TelegramOtherException;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Keyboard\Keyboard;

final class WebCamsCommand extends Command
{
	protected string $name = 'cams';

	protected string $description = 'View web cams screenshots';

    public function handle(): void
    {
        $this->showSectors($this->getUpdate()->getChat()->getId());
    }

    /**
     * @throws TelegramSDKException
     */
    public function showSectors($chatId): void
    {
        $sectors = WebCam::query()
            ->distinct()
            ->where('work', true)
            ->pluck('sector')
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        if (empty($sectors)) {
            $this->replyWithMessage([
                'text' => 'Нет доступных секторов.'
            ]);
            return;
        }

        $keyboard = Keyboard::make()
            ->inline();

        $chunks = array_chunk($sectors, 2);

        foreach ($chunks as $chunk) {
            $row = [];
            foreach ($chunk as $sector) {
                $row[] = Keyboard::inlineButton([
                    'text' => "Сектор {$sector}",
                    'callback_data' => json_encode([
                        'action' => 'show_sector',
                        'sector' => $sector
                    ])
                ]);
            }
            $keyboard->row(...$row);
        }

        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => 'Выберите сектор:',
            'reply_markup' => $keyboard
        ]);
    }

    /**
     * @throws TelegramSDKException
     */
    public function showCamerasInSector($chatId, $sector): void
    {
        $cameras = WebCam::query()
            ->where('sector', $sector)
            ->where('work', true)
            ->get();

        if ($cameras->isEmpty()) {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'В этом секторе нет доступных камер.'
            ]);
            return;
        }

        $keyboard = Keyboard::make()
            ->inline();

        $chunks = array_chunk($cameras->toArray(), 2);

        foreach ($chunks as $chunk) {
            $row = [];
            foreach ($chunk as $camera) {
                $row[] = Keyboard::inlineButton([
                    'text' => $camera['name'],
                    'callback_data' => json_encode([
                        'action' => 'show_camera',
                        'camera_id' => $camera['id']
                    ])
                ]);
            }
            $keyboard->row(...$row);
        }

        $keyboard->row(
            Keyboard::inlineButton([
                'text' => '← Назад к секторам',
                'callback_data' => json_encode(['action' => 'show_sectors'])
            ])
        );

        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "Камеры в секторе {$sector}:",
            'reply_markup' => $keyboard
        ]);
    }

    /**
     * @throws TelegramSDKException
     */
    public function showCameraDetails($chatId, $cameraId): void
    {
        $camera = WebCam::query()->find($cameraId);

        if (!$camera) {
            $this->telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Камера не найдена.'
            ]);
            return;
        }

        $message = "📹 *{$camera->name}*\n\n";
        $message .= "📍 *Сектор:* {$camera->sector}\n";

        if ($camera->description) {
            $message .= "📝 *Описание:* {$camera->description}\n";
        }

        $message .= "\n🔗 *Ссылка:* {$camera->aliace}";

        // Создаем клавиатуру с кнопками
        $keyboard = Keyboard::make()
            ->inline()
            ->row(
                Keyboard::inlineButton([
                    'text' => '← Назад к камерам',
                    'callback_data' => json_encode([
                        'action' => 'show_sector',
                        'sector' => $camera->sector
                    ])
                ]),
                Keyboard::inlineButton([
                    'text' => '← К секторам',
                    'callback_data' => json_encode(['action' => 'show_sectors'])
                ])
            );

        // Отправляем сообщение с описанием
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'reply_markup' => $keyboard
        ]);

        // Если есть скриншот, отправляем фото
        if ($camera->screenshot) {
            try {
                $this->telegram->sendPhoto([
                    'chat_id' => $chatId,
                    'photo' => $camera->screenshot,
                    'caption' => "Скриншот камеры: {$camera->name}"
                ]);
            } catch (\Exception $e) {
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'Не удалось загрузить скриншот.'
                ]);
            }
        }
    }
}