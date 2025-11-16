<?php

declare(strict_types=1);

namespace App\Services\Users;

use Illuminate\Support\Facades\Log;
use Telegram\Bot\Objects\Update;

final class UsersService
{
	public function processUser(Update $update)
	{
		Log::info('Update: ', [
			'updateData' => $update
		]);
	}
}