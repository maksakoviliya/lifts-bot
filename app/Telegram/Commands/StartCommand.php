<?php

declare(strict_types=1);

namespace App\Telegram\Commands;

use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;

final class StartCommand extends Command
{
	protected string $name = 'start';

	protected string $description = 'Start Command to get you started';

	public function handle(): void
	{
		$keyboard = Keyboard::make()
			->inline()
			->row([
				Keyboard::inlineButton([
					'text' => '🎿 Проверить подъемники',
					'callback_data' => 'lifts'
				])
			]);
		
		$this->replyWithMessage([
			'text' => "👋 Привет! Я бот для проверки статусов подъемников.\n\nИспользуй команду /lifts или нажми кнопку ниже.",
			'reply_markup' => $keyboard
		]);
	}
}