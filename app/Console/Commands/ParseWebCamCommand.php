<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ParseWebCamJob;
use App\Models\WebCam;
use App\Services\Parsers\EgegeshWebCamParser;
use Exception;
use Illuminate\Console\Command;

use function Sentry\captureException;

class ParseWebCamCommand extends Command
{
    protected $signature = 'app:parse-web-cam';

    protected $description = 'Process each webcam command';

    public function handle(): int
    {
        $cams = WebCam::query()->get();
        $parser = new EgegeshWebCamParser();

        foreach ($cams as $cam) {
//            ParseWebCamJob::dispatch($cam);
            try {
                $parser->parse($cam);
            } catch (Exception $exception) {
                captureException($exception);
                continue;
            }
        }

        return self::SUCCESS;
    }
}
