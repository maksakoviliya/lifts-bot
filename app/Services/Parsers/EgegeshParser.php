<?php

declare(strict_types=1);

namespace App\Services\Parsers;

use App\Services\Lifts\LiftService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
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
			$log->error('lifts: request failed', [
				'url' => $url,
				'ms' => (int) ((microtime(true) - $start) * 1000),
				'error' => $exception->getMessage(),
			]);
			return 0;
		}

		$html = $response->getBody()->getContents();
		$log->info('lifts: page fetched', [
			'url' => $url,
			'status' => $response->getStatusCode(),
			'bytes' => strlen($html),
			'ms' => (int) ((microtime(true) - $start) * 1000),
		]);
		$crawler = new Crawler($html);
		$rows = $crawler->filter('.accord__title');

		$rows->each(function (Crawler $node) {
			try {
				$status = $node->filter('.accord__status img')->attr('title');
				$operator = $node->filter('.accord__operator')->text();
				$name = $node->filter('.accord__name-text')->text();
				$rise_time = $node->filter('.accord__rise_time')->text();
				$length = $node->filter('.accord__length')->text();
				$icon = $node->filter('.accord__icon img')->attr('src');
			} catch (InvalidArgumentException $exception) {
				// one malformed row must not stop the rest from updating
				Log::channel('egegesh')->warning('lifts: row skipped', [
					'error' => $exception->getMessage(),
					'html' => mb_substr(trim($node->text('')), 0, 200),
				]);
				return;
			}

			$this->liftService->createOrUpdateLift([
				'status' => $status,
				'operator' => $operator,
				'name' => $name,
				'rise_time' => $rise_time,
				'length' => $length,
				'icon' => $icon,
			]);
			$this->processed++;
		});

		// zero rows on a 200 page almost always means the site layout changed
		$log->log($this->processed ? 'info' : 'warning', 'lifts: parsed', [
			'rows' => $rows->count(),
			'processed' => $this->processed,
		]);

		return $this->processed;
	}
}