<?php

namespace App\Telegram\Commands;

use App\Models\Lift;
use Telegram\Bot\Commands\Command;

class LiftsCommand extends Command
{
	protected string $name = 'lifts';

	protected string $description = 'Show lifts statuses';
	
	public function handle(): void
	{
		$lifts = Lift::query()->get();

		if ($lifts->isEmpty()) {
			$this->replyWithMessage([
				'text' => "Пока нет данных о подъемниках ❗️"
			]);
			return;
		}

		$output = "🎿 *Статусы подъемников*\n\n";

		$output .= $lifts->map(function ($lift) {
			return sprintf(
				"%s: %s",
				$lift->name,
				$lift->is_active ? '✅ Работает' : '❌ Закрыт'
			);
		})->implode("\n");

		$this->replyWithMessage([
			'text' => $output,
			'parse_mode' => 'Markdown'
		]);
	}
}