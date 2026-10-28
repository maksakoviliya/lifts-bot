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
        try {
            $response = $client->get($url, [
				'headers' => [
					'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
				]
			]);
        } catch (GuzzleException $exception) {
            Log::error('Egegesh parse error', [
				'exception' => $exception
			]);
            return false;
        }

        $html = $response->getBody()->getContents();
        $crawler = new Crawler($html);

        $img = $crawler->filter('.slick-slide')->last()->filter('img');
        if (!$img->count()) {
           return false;
        }

        $webCam->update([
            'screenshot' => $img->attr('src')
        ]);

        return true;
	}
}