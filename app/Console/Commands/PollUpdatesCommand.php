<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Telegram\UpdateHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Laravel\Facades\Telegram;
use Throwable;

/**
 * Long polling instead of the webhook: the hosting doesn't receive incoming connections from Telegram,
 * but outgoing requests to api.telegram.org work. The scheduler starts it every minute.
 */
class PollUpdatesCommand extends Command
{
    protected $signature = 'telegram:poll {--seconds=55 : How long to keep polling before exiting}';

    protected $description = 'Fetch Telegram updates with getUpdates and handle them';

    private const OFFSET_KEY = 'telegram:updates-offset';

    public function handle(UpdateHandler $updateHandler): int
    {
        $deadline = time() + (int) $this->option('seconds');

        do {
            try {
                $updates = Telegram::getUpdates([
                    // updates below the offset are confirmed and never come back
                    'offset' => Cache::get(self::OFFSET_KEY, 0),
                    // Telegram holds the request open until an update arrives or this many seconds pass
                    'timeout' => max(0, min(25, $deadline - time())),
                ]);
            } catch (TelegramSDKException $e) {
                if ($e->getCode() !== 409) {
                    throw $e;
                }

                // 409: getUpdates is refused while a webhook is set (e.g. app:set-bot-webhook-command was run)
                Log::warning('Telegram webhook is set, deleting it to switch to polling');
                Telegram::deleteWebhook();
                continue;
            }

            foreach ($updates as $update) {
                try {
                    $updateHandler->handle($update);
                } catch (Throwable $e) {
                    // a broken update must not block the ones after it
                    report($e);
                }

                Cache::forever(self::OFFSET_KEY, $update->updateId + 1);
            }
        } while (time() < $deadline);

        return self::SUCCESS;
    }
}
