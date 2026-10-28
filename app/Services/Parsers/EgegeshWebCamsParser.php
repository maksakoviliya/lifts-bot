<?php

declare(strict_types=1);

namespace App\Services\Parsers;

use App\Models\WebCam;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class EgegeshWebCamsParser
{
	private int $processed = 0;

    /**
     * @throws Exception
     */
    public function parse(): int
	{
		$url = 'https://egegesh.ru/screens';
		$client = new Client(['timeout' => 15, 'connect_timeout' => 5]);
		$log = Log::channel('egegesh');
		$start = microtime(true);
		try {
			$response = $client->get($url, [
				'headers' => [
					'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
				]
			]);
		} catch (GuzzleException $exception) {
			$log->error('cams: request failed', [
				'url' => $url,
				'ms' => (int) ((microtime(true) - $start) * 1000),
				'error' => $exception->getMessage(),
			]);
			return 0;
		}

		$html = $response->getBody()->getContents();
		$log->info('cams: page fetched', [
			'url' => $url,
			'status' => $response->getStatusCode(),
			'bytes' => strlen($html),
			'ms' => (int) ((microtime(true) - $start) * 1000),
		]);

        if (preg_match('/window\.__REDUX_STATE__\s*=\s*(\{.*?\});/s', $html, $matches)) {
            $jsonString = $matches[1];

            $pureJson = $this->extractFirstJsonObject($jsonString);
            $data = $pureJson === null ? null : json_decode($pureJson, true);

            if ($data === null) {
                throw new Exception('Can\'t read data from ' . $url);
            }
            $cams = Arr::get($data, 'cams', []);
            
            foreach ($cams as $cam) {
                WebCam::query()
                    ->updateOrCreate([
                        'sector' => Arr::get($cam, 'sector'),
                        'aliace' => Arr::get($cam, 'aliace')
                    ], [
                        'name' => $this->parseName($cam),
                        'work' => Arr::get($cam, 'work'),
                        'description' => Arr::get($cam, 'description'),
                    ]);
                $this->processed++;
            }

            $log->info('cams: parsed', [
                'cams' => count($cams),
                'working' => collect($cams)->where('work', 1)->count(),
            ]);
        } else {
            $log->warning('cams: __REDUX_STATE__ not found on the page, layout changed?', ['url' => $url]);
        }

        return $this->processed;
	}
    
    private function parseName(array $cam)
    {
        $name = Arr::get($cam, 'name');
        $parts = explode('. ', $name, 2);
        return $parts[1] ?? $name;
    }

    private function extractFirstJsonObject(string $str): ?string
    {
        $str = trim($str);

        if ($str[0] !== '{') {
            return null;
        }

        $level = 0;
        $inString = false;
        $escape = false;
        $len = strlen($str);

        for ($i = 0; $i < $len; $i++) {
            $ch = $str[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;
                } elseif ($ch === '\\') {
                    $escape = true;
                } elseif ($ch === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($ch === '"') {
                $inString = true;
            } elseif ($ch === '{') {
                $level++;
            } elseif ($ch === '}') {
                $level--;
                if ($level === 0) {
                    return substr($str, 0, $i + 1);
                }
            }
        }

        return null;
    }
}