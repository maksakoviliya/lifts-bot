<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LiftFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
		'disabled_at',
		'disabled_by',
		'enabled_at',
		'enabled_by',
	];
	
	protected $casts = [
		'is_active' => 'boolean',
		'data' => 'array'
	];
	
	public function Status(): Attribute
	{
		return Attribute::get(function (mixed $value, array $attributes) {
			if (isset($attributes['enabled_at']) && isset($attributes['enabled_by'])) {
				return 'enabled';
			}

			if (isset($attributes['disabled_at']) && isset($attributes['disabled_by'])) {
				return 'disabled';
			}

			return 'auto';
		});
	}
}
