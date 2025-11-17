<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Objects\Update;

final class UsersService
{
	public function processUser(Update $update): void
	{
		Log::info('Update: ', ['updateData' => $update]);

		$chat = $update->message?->chat
			?? $update->callbackQuery?->message?->chat;

		if (!$chat) {
			Log::alert('No chat in update', ['update' => $update]);
			return;
		}

		if ($chat->type === 'private') {
			$user = User::query()
				->where('telegram_id', $chat->id)
				->orWhere('email', "_$chat->id@t.me")
				->first();

			if (!$user) {
				$user = User::query()->create([
					'telegram_id' => $chat->id,
					'name' => $chat->username ?: "name_$chat->id",
					'email' => "_$chat->id@t.me",
					'first_name' => $chat->first_name ?: null,
					'last_name' => $chat->last_name ?: null,
					'username' => $chat->username ?: "name_$chat->id",
					'password' => Hash::make($chat->username),
				]);
			}

			$user->update([
				'usage_count' => $user->usage_count + 1,
			]);
		}
	}
}