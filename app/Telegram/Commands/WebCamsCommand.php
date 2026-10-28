<?php

declare(strict_types=1);

namespace App\Telegram\Commands;

use App\Models\WebCam;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;

use function Sentry\captureException;

class WebCamsCommand extends Command
{
    protected string $name = 'cams';
    protected string $description = 'Просмотр веб-камер курорта';
    protected array $aliases = ['камеры', 'camera', 'вебкамеры', 'webcams'];

    /**
     * @throws TelegramSDKException
     */
    public function handle(): void
    {
        $chatId = $this->getUpdate()->getChat()->getId();
        $this->showSectors($chatId);
    }

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

        // Telegram facade instead of $this->...: the controller calls these methods on `new WebCamsCommand()`,
        // where the SDK hasn't injected the bot or the update
        if (empty($sectors)) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Нет доступных секторов с камерами.'
            ]);
            return;
        }

        $keyboard = Keyboard::make()->inline();

        $chunks = array_chunk($sectors, 2);

        foreach ($chunks as $chunk) {
            $buttons = [];
            foreach ($chunk as $sector) {
                $buttons[] = Keyboard::inlineButton([
                    'text' => "📍 Сектор {$sector}",
                    'callback_data' => json_encode([
                        'action' => 'show_sector_cameras',
                        'sector' => $sector
                    ])
                ]);
            }
            $keyboard->row($buttons);
        }

        // Добавляем кнопку "Назад" если это не первый экран
        $keyboard->row([
            Keyboard::inlineButton([
                'text' => '🏠 В главное меню',
                'callback_data' => 'main_menu'
            ])
        ]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => '📹 *Выберите сектор для просмотра камер:*',
            'parse_mode' => 'Markdown',
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
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "В секторе {$sector} нет доступных камер в данный момент."
            ]);
            return;
        }

        $keyboard = Keyboard::make()->inline();

        // Добавляем камеры по 2 в ряд
        $chunks = array_chunk($cameras->toArray(), 2);

        foreach ($chunks as $chunk) {
            $buttons = [];
            foreach ($chunk as $camera) {
                $buttons[] = Keyboard::inlineButton([
                    'text' => "📷 " . mb_substr($camera['name'], 0, 15) . (mb_strlen($camera['name']) > 15 ? '...' : ''),
                    'callback_data' => json_encode([
                        'action' => 'show_camera_details',
                        'camera_id' => $camera['id']
                    ])
                ]);
            }
            $keyboard->row($buttons);
        }

        // Кнопки навигации
        $keyboard->row([
            Keyboard::inlineButton([
                'text' => '← Назад к секторам',
                'callback_data' => json_encode(['action' => 'show_camera_sectors'])
            ]),
            Keyboard::inlineButton([
                'text' => '🏠 Главная',
                'callback_data' => 'main_menu'
            ])
        ]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "📹 *Камеры в секторе {$sector}:*\nВсего доступно: " . count($cameras),
            'parse_mode' => 'Markdown',
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
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Камера не найдена или временно недоступна.'
            ]);
            return;
        }

        $keyboard = Keyboard::make()->inline();

        $link = sprintf("https://egegesh.ru/online/%s", $camera->aliace);

        $keyboard
            ->row([
                Keyboard::inlineButton([
                    'text' => '📹 Онлайн камера',
                    'url' => $link
                ])
            ])
            ->row([
                Keyboard::inlineButton([
                    'text' => '← Назад к списку камер',
                    'callback_data' => json_encode([
                        'action' => 'show_sector_cameras',
                        'sector' => $camera->sector
                    ])
                ])
            ])
            ->row([
                Keyboard::inlineButton([
                    'text' => '← К выбору сектора',
                    'callback_data' => json_encode(['action' => 'show_camera_sectors'])
                ])
            ]);

        // Если есть скриншот, отправляем фото
        if ($camera->screenshot && filter_var($camera->screenshot, FILTER_VALIDATE_URL)) {
            // Two steps logged separately: egegesh.log = the site didn't give the picture,
            // laravel.log = Telegram didn't accept it
            $start = microtime(true);
            try {
                // InputFile::create($url) downloads via fopen and can block the webhook for up to 60s
                $response = Http::timeout(10)->throw()->get($camera->screenshot);
                $photo = $response->body();

                Log::channel('egegesh')->info('screenshot: downloaded', [
                    'camera_id' => $cameraId,
                    'url' => $camera->screenshot,
                    'status' => $response->status(),
                    'bytes' => strlen($photo),
                    'ms' => (int) ((microtime(true) - $start) * 1000),
                ]);
            } catch (Exception $e) {
                $photo = null;
                Log::channel('egegesh')->warning('screenshot: download failed', [
                    'camera_id' => $cameraId,
                    'url' => $camera->screenshot,
                    'ms' => (int) ((microtime(true) - $start) * 1000),
                    'error' => $e->getMessage(),
                ]);
            }

            if ($photo !== null) {
                try {
                    Telegram::sendPhoto([
                        'chat_id' => $chatId,
                        'photo' => InputFile::createFromContents($photo, 'camera.jpg'),
                        'caption' => "📹 Скриншот с камеры: *{$camera->name}*",
                        'parse_mode' => 'Markdown',
                        'reply_markup' => $keyboard,
                    ]);
                    return;
                } catch (Exception $e) {
                    // Логируем ошибку, но не прерываем выполнение
                    captureException($e);
                    Log::error('Ошибка отправки фото камеры в Telegram', [
                        'camera_id' => $cameraId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } else {
            Log::channel('egegesh')->warning('screenshot: no valid url in DB', [
                'camera_id' => $cameraId,
                'screenshot' => $camera->screenshot
            ]);
        }

        // No screenshot: still give the user the online link and navigation instead of silence
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "📹 *{$camera->name}*\nСкриншот пока недоступен.",
            'parse_mode' => 'Markdown',
            'reply_markup' => $keyboard,
        ]);
    }
}