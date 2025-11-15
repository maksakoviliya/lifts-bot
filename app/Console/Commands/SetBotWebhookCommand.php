<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Telegram\Bot\Laravel\Facades\Telegram;

class SetBotWebhookCommand extends Command
{
    protected $signature = 'app:set-bot-webhook-command';

    protected $description = 'Set webhook command';

    public function handle(): int
    {
	    Telegram::setWebhook(['url' => route('webhook')]);
	    
		return parent::SUCCESS;
    }
}
