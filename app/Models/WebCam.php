<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WebCamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebCam extends Model
{
    /** @use HasFactory<WebCamFactory> */
    use HasFactory;
    
    protected $fillable = [
        'name',
        'work',
        'aliace',
        'description',
        'sector',
        'screenshot',
    ];

    /**
     * Local copy of the screenshot pushed by scripts/egegesh-push.sh (default disk);
     * named after the url, so a new screenshot url never matches an old file
     */
    public function screenshotPath(): ?string
    {
        $name = $this->screenshot ? basename((string) parse_url($this->screenshot, PHP_URL_PATH)) : '';

        return $name === '' ? null : "webcams/{$this->id}-{$name}";
    }
}
