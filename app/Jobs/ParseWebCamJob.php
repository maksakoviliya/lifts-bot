<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WebCam;
use App\Services\Parsers\EgegeshWebCamParser;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ParseWebCamJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private WebCam $webCam
    ) {
    }

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        $parser = new EgegeshWebCamParser();
        $parser->parse(sprintf("https://egegesh.ru/screens/%s", $this->webCam->aliace));
    }
}
