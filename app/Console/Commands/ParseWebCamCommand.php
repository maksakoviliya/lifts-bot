<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ParseWebCamJob;
use App\Models\WebCam;
use App\Services\Parsers\EgegeshWebCamParser;
use Illuminate\Console\Command;

class ParseWebCamCommand extends Command
{
    protected $signature = 'app:parse-web-cam';

    protected $description = 'Process each webcam command';

    public function handle(): int
    {
        $cams = WebCam::query()->get();

        foreach ($cams as $cam) {
            ParseWebCamJob::dispatch($cam);
        }

        return self::SUCCESS;
    }
}
