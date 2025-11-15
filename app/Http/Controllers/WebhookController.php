<?php

namespace App\Http\Controllers;

use App\Telegram\Commands\LiftsCommand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class WebhookController extends Controller
{
	public function __invoke(Request $request): string
	{
		$update = Telegram::getWebhookUpdate();
		$callbackQuery = $update->callbackQuery;
		if ($callbackQuery) {
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

		Log::info('Обработка обновления', [
			'update' => $update->updateId,
			'message' => $update->getMessage(),
		]);

		return 'ok';
	}
}
