<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Objects\Update;

final class UsersService
{
	public function processUser(Update $update): void
	{
		Log::info('Update: ', [
			'updateData' => $update
		]);
		
		$chat = $update->message?->chat;
		if (!$chat) {
			Log::alert('No chat in update', [
				'update' => $update
			]);
			return;
		}
		
		$type = $chat->type;
		if ($type === 'private') {
			$user = User::query()
				->where('telegram_id', $chat->id);
			
			if (!$user) {
				$user = User::query()
					->create([
						'name' => $chat->username,
						'email' => "_$chat->id@t.me",
						'first_name' => $chat->first_name,
			            'last_name' => $chat->last_name,
			            'username' => $chat->username,
					]);
			}
			
			$user->update([
				'usage_count' => $user->usage_count++
			]);
		}
	}
}