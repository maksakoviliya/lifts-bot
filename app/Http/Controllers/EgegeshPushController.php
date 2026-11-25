<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\WebCam;
use App\Services\Parsers\EgegeshParser;
use App\Services\Parsers\EgegeshWebCamParser;
use App\Services\Parsers\EgegeshWebCamsParser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Takes egegesh pages fetched by scripts/egegesh-push.sh from a machine that can reach the site
 * (prod hosting is blocked there) and runs the usual parsers on them.
 * Answers in plain "one item per line" text telling the script what to fetch next:
 *   1. info + screens         -> lifts and cam list updated; answers "alias page_url" per camera
 *   2. alias + page           -> cam screenshot url updated; answers the image url if prod doesn't have it yet
 *   3. alias + image          -> image saved for the bot to send (it can't download it from static.egegesh.ru)
 */
final class EgegeshPushController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $token = (string) config('services.egegesh.push_token');
        abort_if($token === '' || !hash_equals($token, (string) $request->bearerToken()), 403);

        if ($request->hasFile('info') || $request->hasFile('screens')) {
            if ($request->hasFile('info')) {
                (new EgegeshParser())->parse($request->file('info')->get());
            }
            if ($request->hasFile('screens')) {
                (new EgegeshWebCamsParser())->parse($request->file('screens')->get());
            }

            return $this->lines(WebCam::query()->pluck('aliace')->map(fn (string $alias) => "$alias https://egegesh.ru/screens/$alias")->all());
        }

        $request->validate(['alias' => 'required|string']);
        $cam = WebCam::query()->where('aliace', $request->input('alias'))->firstOrFail();

        if ($request->hasFile('page')) {
            (new EgegeshWebCamParser())->parse($cam, $request->file('page')->get());
            $path = $cam->screenshotPath();

            return $this->lines($path && !Storage::exists($path) ? [$cam->screenshot] : []);
        }

        $request->validate(['image' => 'required|file|image|max:5120']);
        $path = $cam->screenshotPath();
        abort_if($path === null, 409, 'Camera has no screenshot url yet');

        // one file per camera: drop the previous screenshot
        Storage::delete(array_filter(Storage::files('webcams'), fn (string $file) => str_starts_with($file, "webcams/{$cam->id}-")));
        Storage::put($path, $request->file('image')->get());

        return $this->lines([]);
    }

    private function lines(array $lines): Response
    {
        return response(implode("\n", $lines) . ($lines ? "\n" : ''), 200, ['Content-Type' => 'text/plain']);
    }
}
