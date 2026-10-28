<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Telegram\Commands\LiftsCommand;
use Exception;
use Illuminate\Console\Command;
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

		$text = LiftsCommand::statusText();

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
		} catch (Exception $e) {
			$this->error("❌ Ошибка: " . $e->getMessage());
			return self::FAILURE;
		}
	}
}
