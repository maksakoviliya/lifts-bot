<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Parsers\EgegeshParser;
use Illuminate\Console\Command;

class ParseLiftsCommand extends Command
{
    protected $signature = 'app:parse-lifts-command';
	
    protected $description = 'Parse gesh lifts';
	
    public function handle(): int
    {
        $parser = new EgegeshParser();

	    $processed = $parser->parse();
		
		$this->info(sprintf("Processed %d items.", $processed));
		
		return parent::SUCCESS;
    }
}
