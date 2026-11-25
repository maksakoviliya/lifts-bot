<?php

declare(strict_types=1);

namespace App\Services\Parsers;

use App\Models\WebCam;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

class EgegeshWebCamParser
{
    /**
     * Returns whether a screenshot was found and saved
     *
     * @param string|null $html page fetched elsewhere (egegesh:push), skips the request
     */
    public function parse(WebCam $webCam, ?string $html = null): bool
	{
        $url = sprintf("https://egegesh.ru/screens/%s", $webCam->aliace);
        $log = Log::channel('egegesh');
        $start = microtime(true);
        $html ??= $this->fetch($url);
        if ($html === null) {
            return false;
        }

        // The slider <img> is lazy-loaded (src is a placeholder) and lags behind; the newest shot is galery[0] in the page state
        $data = null;
        if (preg_match('/window\.__REDUX_STATE__\s*=\s*(\{.*?\});/s', $html, $matches)) {
            $json = EgegeshWebCamsParser::extractFirstJsonObject($matches[1]);
            $data = $json === null ? null : json_decode($json, true);
        }
        $screenshot = is_array($data) ? Arr::get($data, "show.{$webCam->aliace}.galery.0.image") : null;
        if (!$screenshot) {
            $log->warning('cam: no screenshot in gallery', [
                'url' => $url,
                'bytes' => strlen($html),
            ]);
            return false;
        }

        $webCam->update([
            'screenshot' => $screenshot
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

    private function fetch(string $url): ?string
    {
        $client = new Client(['timeout' => 15, 'connect_timeout' => 5]);
        $start = microtime(true);
        try {
            $response = $client->get($url, [
				'headers' => [
					'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
				]
			]);
        } catch (GuzzleException $exception) {
            Log::channel('egegesh')->error('cam: request failed', [
                'url' => $url,
                'ms' => (int) ((microtime(true) - $start) * 1000),
                'error' => $exception->getMessage(),
            ]);
            return null;
        }

        return $response->getBody()->getContents();
    }
}
