<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Weather\WeatherService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FetchWeatherCommand extends Command
{
    protected $signature = 'app:fetch-weather';

    protected $description = 'Fetch weather from "worldweatheronline"';

    public function handle(): int
    {
        $url = config('services.worldweatheronline.url');
        $key = config('services.worldweatheronline.key');
        $latLng = config('services.worldweatheronline.latlng');

        $this->info('Fetching weather from "' . $url . '"' . "\n");
        $this->info('Key: ' . $key . "\n");
        $this->info('LatLng: ' . $latLng . "\n");

        $date = Carbon::now('Asia/Novokuznetsk')->setMinutes(0);
        $dateFormatted = $date->format('Y-m-d');
        $hour = $date->addHour()->hour;

        $response = Http::get($url, [
            'key' => $key,
            'q' => $latLng,
            'format' => 'json',
            'date' => $dateFormatted,
            'lang' => 'ru'
        ]);

        if (!$response->successful()) {
            $this->error('Fetch weather failed');
        }

        $data = $response->json('data');
        if (Arr::get($data, 'error')) {
            $this->error(Arr::get($data, 'error.0.msg', 'Что-то пошло не так!'));
        }

        $weather = Arr::get($data, 'weather.0');
        $hours = Arr::get($weather, 'hourly');
        $weatherService = new WeatherService();

        $result = $weatherService->parse(
            $date,
            $date->addHour()->format('H:i'),
            $hours[$hour]
        );

        if ($result) {
            $this->info('Finished!');
        } else {
            $this->error('Fetch weather failed!');
        }

        return self::SUCCESS;
    }
}
