<?php

declare(strict_types=1);

namespace App\Services\Parsers;

use App\Models\WebCam;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class EgegeshWebCamParser
{
    /**
     * Returns whether a screenshot was found and saved
     */
    public function parse(WebCam $webCam): bool
	{
        $url = sprintf("https://egegesh.ru/screens/%s", $webCam->aliace);
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
            $log->error('cam: request failed', [
                'url' => $url,
                'ms' => (int) ((microtime(true) - $start) * 1000),
                'error' => $exception->getMessage(),
            ]);
            return false;
        }

        $html = $response->getBody()->getContents();
        $crawler = new Crawler($html);

        $img = $crawler->filter('.slick-slide')->last()->filter('img');
        if (!$img->count()) {
            $log->warning('cam: no screenshot in gallery', [
                'url' => $url,
                'status' => $response->getStatusCode(),
            ]);
            return false;
        }

        $webCam->update([
            'screenshot' => $img->attr('src')
        ]);

        // "changed: false" for hours means the site stopped taking new screenshots for this camera
        $log->info('cam: screenshot', [
            'cam' => $webCam->aliace,
            'work' => (bool) $webCam->work,
            'screenshot' => $webCam->screenshot,
            'changed' => $webCam->wasChanged('screenshot'),
            'ms' => (int) ((microtime(true) - $start) * 1000),
        ]);

        return true;
	}
}