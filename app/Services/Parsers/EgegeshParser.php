<?php

declare(strict_types=1);

namespace App\Services\Parsers;

use App\Services\Lifts\LiftService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class EgegeshParser
{
	private LiftService $liftService;

	private int $processed = 0;

	public function __construct()
	{
		$this->liftService = new LiftService();
	}

	public function parse(): int
	{
		$url = 'https://egegesh.ru/info';
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

		$crawler->filter('.accord__title')->each(function (Crawler $node) {
			$status = $node->filter('.accord__status img')->attr('title');
			$operator = $node->filter('.accord__operator')->text();
			$name = $node->filter('.accord__name-text')->text();
			$rise_time = $node->filter('.accord__rise_time')->text();
			$length = $node->filter('.accord__length')->text();
			$icon = $node->filter('.accord__icon img')->attr('src');

			if ($this->liftService->createOrUpdateLift([
				'status' => $status,
				'operator' => $operator,
				'name' => $name,
				'rise_time' => $rise_time,
				'length' => $length,
				'icon' => $icon,
			])) {
				$this->processed++;
			}
		});
		
		return $this->processed;
	}
}