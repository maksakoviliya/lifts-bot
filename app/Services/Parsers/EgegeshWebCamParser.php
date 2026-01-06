<?php

declare(strict_types=1);

namespace App\Services\Parsers;

use App\Models\WebCam;
use App\Services\Lifts\LiftService;
use Exception;
use Faker\Provider\PhoneNumber;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class EgegeshWebCamParser
{
    /**
     * @throws Exception
     */
    public function parse(WebCam $webCam): int
	{
        $url = sprintf("https://egegesh.ru/screens/%s", $webCam->aliace);
        $client = new Client();
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
            return 0;
        }

        $html = $response->getBody()->getContents();
        $crawler = new Crawler($html);

        $lastGalleryItem = $crawler->filter('.slick-slide')->last();
        $img = $lastGalleryItem->filter('img');

        $imgNodes = $lastGalleryItem->filter('img');
        if (!$imgNodes->count()) {
           return 1;
        }

        $src = $img->attr('src');
        $webCam->update([
            'screenshot' => $src
        ]);
        
        return 0;
	}
}