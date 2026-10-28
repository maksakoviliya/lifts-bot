<?php

namespace App\Telegram\Commands;

use App\Models\Lift;
use Illuminate\Support\Facades\Cache;
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
            ])
            ->row([
                Keyboard::inlineButton([
                    'text' => '📹 Посмотреть камеры',
                    'callback_data' => 'cams'
                ])
            ]);

		$this->replyWithMessage([
			'text' => self::statusText(),
			'parse_mode' => 'Markdown',
			'reply_markup' => $keyboard
		]);
	}

	/**
	 * Lifts status message shared by the command, the channel post and its refresh button
	 */
	public static function statusText(): string
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
					self::processName($lift->name)
				);
			})->implode("\n");
		}

		return $output . "\n\n" . Cache::get('weather');
	}

	public static function processName(string $name): string
	{
		if (Str::contains($name, 'Гондольный подъёмник')) {
			$name .= '🚠';
		}
		return trim(Str::replace(['Гондольный подъёмник', 'Кресельный подъёмник ', 'Гондольно-кресельный подъёмник ' ], '', $name));
	}
}
