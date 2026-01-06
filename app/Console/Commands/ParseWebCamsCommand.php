<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Parsers\EgegeshWebCamsParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ParseWebCamsCommand extends Command
{
    protected $signature = 'app:parse-web-cams';
    
    protected $description = 'Parse webcams and their screenshots';

    public function handle(): int
    {
        Log::info('Start parsing web cams');

        $parser = new EgegeshWebCamsParser();

        $processed = $parser->parse();

        $this->info(sprintf("Processed %d items.", $processed));

        return parent::SUCCESS;
    }
}
