<?php

declare(strict_types=1);

namespace App\Services\Weather;

use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class WeatherService
{
    public function parse(CarbonInterface $date, string $time, array $data): bool
    {
        $bottom = Arr::get($data, 'bottom.0');
        $mid = Arr::get($data, 'mid.0');
        $top = Arr::get($data, 'top.0');

        $output = sprintf("*О погоде на %s на %s*\n", $date->format('d.m.Y'), $time);

        $bottomOutput = sprintf("🏞 Низ: %s\n", Arr::get($bottom, 'lang_ru.0.value'));
        $bottomOutput = $bottomOutput . sprintf(
                "🌡  %s°C  💨  %s км/ч",
                Arr::get($bottom, 'tempC'),
                Arr::get($bottom, 'windspeedKmph'),
            ) . "\n";

        $midOutput = sprintf("⛰ Средина: %s\n", Arr::get($mid, 'lang_ru.0.value'));
        $midOutput = $midOutput . sprintf(
                "🌡  %s°C  💨  %s км/ч",
                Arr::get($mid, 'tempC'),
                Arr::get($mid, 'windspeedKmph'),
            )  . "\n";

        $topOutput = sprintf("🏔 Верх: %s\n", Arr::get($top, 'lang_ru.0.value'));
        $topOutput = $topOutput . sprintf(
                "🌡  %s°C  💨  %s км/ч",
                Arr::get($top, 'tempC'),
                Arr::get($top, 'windspeedKmph'),
            );

        Cache::put(
            'weather',
            sprintf(
                "%s%s%s%s",
                $output,
                $bottomOutput,
                $midOutput,
                $topOutput
            ),
            // stale weather should disappear from messages rather than linger forever
            now()->addHours(3),
        );

        return true;
    }
}