<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Telegram\Bot\Laravel\Facades\Telegram;

class SetBotCommandsCommand extends Command
{
	protected $signature = 'app:set-bot-commands-command';

	protected $description = 'Set bot commands with their description';

	public function handle(): int
	{
		Telegram::setMyCommands([
			'commands' => [
				['command' => 'start', 'description' => 'Начать работу с ботом'],
				['command' => 'lifts', 'description' => 'Доступные подъемники и их статусы'],
				['command' => 'help', 'description' => 'Список доступных команд'],
			]
		]);
		
		return parent::SUCCESS;
	}
}
