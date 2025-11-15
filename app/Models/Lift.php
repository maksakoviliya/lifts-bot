<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Lift extends Model
{
	/** @use HasFactory<LiftFactory> */
	use HasFactory;

	protected $fillable = [
		'name',
		'is_active',
		'raise_time',
		'length',
		'data',
	];
	
	protected $casts = [
		'is_active' => 'boolean',
		'data' => 'array'
	];
}
