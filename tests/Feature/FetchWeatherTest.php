<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchWeatherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.worldweatheronline.url' => 'https://weather.test/ski.ashx']);
    }

    private function slot(string $time, int $temp): array
    {
        $level = ['tempC' => (string) $temp, 'windspeedKmph' => '5', 'lang_ru' => [['value' => 'Ясно']]];

        return ['time' => $time, 'bottom' => [$level], 'mid' => [$level], 'top' => [$level]];
    }

    public function test_uses_three_hour_slot_covering_next_hour(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-10 13:20', 'Asia/Novokuznetsk'));
        Http::fake(['*' => Http::response(['data' => ['weather' => [['hourly' => [
            $this->slot('900', -5),
            $this->slot('1200', -7),
            $this->slot('1500', -9),
        ]]]]])]);

        $this->artisan('app:fetch-weather')->assertSuccessful();

        // next hour is 14:00, which falls into the 12:00-15:00 slot
        $this->assertStringContainsString('на 10.01.2026 на 14:00', Cache::get('weather'));
        $this->assertStringContainsString('-7°C', Cache::get('weather'));
    }

    public function test_api_error_fails_and_keeps_cached_weather(): void
    {
        Cache::put('weather', 'old');
        Http::fake(['*' => Http::response(['data' => ['error' => [['msg' => 'Invalid key']]]])]);

        $this->artisan('app:fetch-weather')->assertFailed();

        $this->assertSame('old', Cache::get('weather'));
    }
}
