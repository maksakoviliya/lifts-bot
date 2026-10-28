<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Telegram\UpdateHandler;
use Illuminate\Http\Request;
use Telegram\Bot\Laravel\Facades\Telegram;
use Throwable;

/**
 * Webhook mode. Prod uses polling instead (telegram:poll), because incoming connections
 * from Telegram don't reach the hosting; the route stays for environments where they do.
 */
final class WebhookController extends Controller
{
    public function __construct(private readonly UpdateHandler $updateHandler)
    {
    }

    public function __invoke(Request $request): string
    {
        $secret = config('services.telegram.webhook_secret');
        abort_if($secret && !hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token')), 403);

        try {
            $this->updateHandler->handle(Telegram::getWebhookUpdate());
        } catch (Throwable $e) {
            // A non-2xx response makes Telegram redeliver the same update again and again
            report($e);
        }

        return 'ok';
    }
}
