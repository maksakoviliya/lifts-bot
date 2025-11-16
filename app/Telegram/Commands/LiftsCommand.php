<?php

namespace App\Telegram\Commands;

use App\Models\Lift;
use Illuminate\Support\Str;
use Telegram\Bot\Commands\Command;
use Telegram\Bot\Keyboard\Keyboard;

class LiftsCommand extends Command
{
	protected string $name = 'lifts';

	protected string $description = 'Show lifts statuses';
	
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

		$groups = Lift::query()->get()->groupBy('data.operator');

		if ($groups->isEmpty()) {
			$this->replyWithMessage([
				'text' => "Пока нет данных о подъемниках ❗️"
			]);
			return;
		}

		$output = "🎿 *Статусы подъемников*";

		foreach ($groups as $key => $group) {
			$output .= "\n\n*$key:*\n";

			$output .= $group->map(function ($lift) {
				return sprintf(
					"%s: %s",
					$this->processName($lift->name),
					$lift->is_active ? '✅' : '❌'
				);
			})->implode("\n");
		}

		$this->replyWithMessage([
			'text' => $output,
			'parse_mode' => 'Markdown',
			'reply_markup' => $keyboard
		]);
	}

	protected function processName(string $name): string
	{
		return Str::replace(['Гондольный подъёмник', 'Кресельный подъёмник'], ['🚠', '🪑'], $name);
	}
}