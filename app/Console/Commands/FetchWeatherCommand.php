<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Weather\WeatherService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class FetchWeatherCommand extends Command
{
    protected $signature = 'app:fetch-weather';

    protected $description = 'Fetch weather from "worldweatheronline"';

    public function handle(): int
    {
        $url = config('services.worldweatheronline.url');

        $this->info('Fetching weather from "' . $url . '"');

        // Forecast for the upcoming hour; computed once because Carbon is mutable
        $date = Carbon::now('Asia/Novokuznetsk')->addHour()->startOfHour();

        $response = Http::get($url, [
            'key' => config('services.worldweatheronline.key'),
            'q' => config('services.worldweatheronline.latlng'),
            'format' => 'json',
            'date' => $date->format('Y-m-d'),
            'lang' => 'ru'
        ]);

        $data = $response->json('data');
        if (!$response->successful() || Arr::get($data, 'error')) {
            $this->error(Arr::get($data, 'error.0.msg', 'Fetch weather failed'));
            return self::FAILURE;
        }

        // "hourly" is split into tp-hour slots (3h by default) with "time" like "0", "300", ..., "2100"
        $slot = collect(Arr::get($data, 'weather.0.hourly', []))
            ->last(fn (array $hour) => (int) $hour['time'] <= $date->hour * 100);

        if (!$slot) {
            $this->error('No hourly data in response');
            return self::FAILURE;
        }

        (new WeatherService())->parse($date, $date->format('H:i'), $slot);

        $this->info('Finished!');

        return self::SUCCESS;
    }
}
