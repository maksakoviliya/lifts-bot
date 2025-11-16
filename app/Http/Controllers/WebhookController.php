<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Lift;
use App\Services\Users\UsersService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Telegram\Bot\Laravel\Facades\Telegram;

final class WebhookController extends Controller
{
	public function __construct(private readonly UsersService $usersService)
	{
	}

	public function __invoke(Request $request): string
	{
		$update = Telegram::getWebhookUpdate();
		
		$callbackQuery = $update->callbackQuery;
		if ($callbackQuery) {
			$this->usersService->processUser($update);
			
			$callbackData = $callbackQuery->data;
			$message = $callbackQuery->message;
			Log::info('Message', [
				'message' => $message
			]);
			$chatId = $message->chat->id;

			Log::info("Callback received", [
				'data' => $callbackData,
				'chat_id' => $chatId,
			]);

			switch ($callbackData) {
				case 'lifts':
					Telegram::triggerCommand('lifts', $update);
					Telegram::deleteMessage([
						'chat_id' => $chatId,
						'message_id' => $message->messageId
					]);
					break;
				default:
					Telegram::sendMessage([
						'chat_id' => $chatId,
						'text' => 'Неизвестная команда.'
					]);
					break;
			}

			return 'ok';
		}

		$update = Telegram::commandsHandler(true);

		$this->usersService->processUser($update);

		Log::info('Обработка обновления', [
			'update' => $update->updateId,
			'message' => $update->getMessage(),
		]);

		return 'ok';
	}

	public function test(Request $request)
	{
//		$groups = Lift::query()->get()->groupBy('data.operator');

//		if ($lifts->isEmpty()) {
//			$this->replyWithMessage([
//				'text' => "Пока нет данных о подъемниках ❗️"
//			]);
//			return;
//		}

//		$output = "🎿 *Статусы подъемников*";
//		
//		foreach ($groups as $key => $group) {
//			$output .= "\n\n*$key:*\n";
//
//			$output .= $group->map(function ($lift) {
//				return sprintf(
//					"%s: %s",
//					$this->processName($lift->name),
//					$lift->is_active ? '✅ Работает' : '❌ Закрыт'
//				);
//			})->implode("\n");
//		}
//
//		
//		dd($output);
	}
}
