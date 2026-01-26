<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Lift;
use App\Telegram\Commands\LiftsCommand;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;

class SendMessageToChannel extends Command
{
	protected $signature = 'telegram:send-lifts-to-channel';

	protected $description = 'Send lifts status message to Telegram channel';

	public function handle(): int
	{
		$channelId = config('services.telegram.required_channel');

		$keyboard = Keyboard::make()
			->inline()
			->row([
				Keyboard::inlineButton([
					'text' => '🔄 Обновить статус',
					'callback_data' => 'refresh_lifts'
				])
			])
			->row([
				Keyboard::inlineButton([
					'text' => '📢 Просмотр камер',
					'url' => 'https://t.me/gesh_lifts_bot'
				])
			]);

		$text = $this->getLiftsStatus();

		try {
			$response = Telegram::sendMessage([
				'chat_id' => $channelId,
				'text' => $text,
				'parse_mode' => 'Markdown',
				'reply_markup' => $keyboard
			]);

			$this->info("✅ Сообщение отправлено в канал!");
			$this->info("Message ID: " . $response->getMessageId());

			return self::SUCCESS;
		} catch (\Exception $e) {
			$this->error("❌ Ошибка: " . $e->getMessage());
			return self::FAILURE;
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
