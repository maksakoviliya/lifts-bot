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
		'disabled_at',
		'disabled_by',
		'enabled_at',
		'enabled_by',
	];
	
	protected $casts = [
		'is_active' => 'boolean',
		'data' => 'array'
	];
	
	protected $appends = [
		'status'
	];

	public function getStatusAttribute(): string
	{
		if (!is_null($this->enabled_at) && !is_null($this->enabled_by)) {
			return 'enabled';
		}

		if (!is_null($this->disabled_at) && !is_null($this->disabled_by)) {
			return 'disabled';
		}

		return 'auto';
	}
}
