<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\MockInterface;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\ChatMember;
use Telegram\Bot\Objects\Update;
use Tests\TestCase;

class PollUpdatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_handles_updates_and_remembers_offset(): void
    {
        $telegram = $this->fakeTelegram();
        $telegram->shouldReceive('getUpdates')->once()
            ->with(Mockery::on(fn (array $params) => $params['offset'] === 0))
            ->andReturn([new Update([
                'update_id' => 42,
                'message' => [
                    'message_id' => 10,
                    'from' => ['id' => 555, 'is_bot' => false, 'first_name' => 'Test'],
                    'chat' => ['id' => 555, 'type' => 'private', 'first_name' => 'Test'],
                    'date' => 1700000000,
                    'text' => '/start',
                ],
            ])]);
        $telegram->shouldReceive('getChatMember')->andReturn(new ChatMember(['status' => 'member']));
        $telegram->shouldReceive('processCommand')->once();

        $this->artisan('telegram:poll', ['--seconds' => 0])->assertSuccessful();

        // next getUpdates confirms 42 and asks only for newer updates
        $this->assertSame(43, Cache::get('telegram:updates-offset'));
        $this->assertDatabaseHas('users', ['telegram_id' => 555]);
    }

    public function test_deletes_webhook_that_blocks_polling(): void
    {
        $telegram = $this->fakeTelegram();
        $telegram->shouldReceive('getUpdates')
            ->andThrow(new TelegramSDKException("Conflict: can't use getUpdates method while webhook is active", 409));
        $telegram->shouldReceive('deleteWebhook')->once();

        $this->artisan('telegram:poll', ['--seconds' => 0])->assertSuccessful();
    }

    // BotsManager is final, so Telegram::shouldReceive() can't mock it
    private function fakeTelegram(): MockInterface
    {
        $telegram = Mockery::mock();
        Telegram::swap($telegram);

        return $telegram;
    }
}
