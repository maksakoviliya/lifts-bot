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
}
