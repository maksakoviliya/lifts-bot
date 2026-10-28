<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\ChatMember;
use Telegram\Bot\Objects\Update;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // independent of whatever secret the local .env has
        config(['services.telegram.webhook_secret' => 's3cret']);
    }

    private function postUpdate(): TestResponse
    {
        return $this->postJson('/webhook', $this->startMessage(), ['X-Telegram-Bot-Api-Secret-Token' => 's3cret']);
    }

    private function startMessage(): array
    {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 10,
                'from' => ['id' => 555, 'is_bot' => false, 'first_name' => 'Test', 'username' => 'tester'],
                'chat' => ['id' => 555, 'type' => 'private', 'first_name' => 'Test', 'username' => 'tester'],
                'date' => 1700000000,
                'text' => '/start',
            ],
        ];
    }

    public function test_rejects_requests_without_valid_secret(): void
    {
        $this->postJson('/webhook', $this->startMessage())->assertForbidden();
        $this->postJson('/webhook', $this->startMessage(), ['X-Telegram-Bot-Api-Secret-Token' => 'wrong'])
            ->assertForbidden();
    }

    public function test_handles_message_when_excluded_chats_is_not_configured(): void
    {
        config(['services.telegram.excluded_chats' => null]);

        $telegram = $this->fakeTelegram();
        $telegram->shouldReceive('getWebhookUpdate')->andReturn(new Update($this->startMessage()));
        $telegram->shouldReceive('getChatMember')->once()->andReturn(new ChatMember(['status' => 'member']));
        $telegram->shouldReceive('commandsHandler')->once()->andReturn(new Update($this->startMessage()));

        $this->postUpdate()->assertOk()->assertSee('ok');

        $this->assertDatabaseHas('users', ['telegram_id' => 555, 'usage_count' => 1]);
    }

    public function test_answers_ok_even_when_handling_fails(): void
    {
        // anything but 2xx makes Telegram redeliver the update forever
        $this->fakeTelegram()->shouldReceive('getWebhookUpdate')->andThrow(new RuntimeException('boom'));

        $this->postUpdate()->assertOk();
    }

    // BotsManager is final, so Telegram::shouldReceive() can't mock it
    private function fakeTelegram(): MockInterface
    {
        $telegram = Mockery::mock();
        Telegram::swap($telegram);

        return $telegram;
    }
}
