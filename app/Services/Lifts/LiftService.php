<?php

declare(strict_types=1);

namespace App\Services\Lifts;

use App\Models\Lift;
use Illuminate\Support\Arr;

final class LiftService
{
	protected array $data;

	public function createOrUpdateLift(array $data)
	{
		$this->data = $data;

		return Lift::query()
			->updateOrCreate([
				'name' => $this->getStringValue('name')
			], [
				'raise_time' => $this->getIntValue('rise_time'),
				'length' => $this->getIntValue('length'),
				'is_active' => $this->getStatus(),
				'data' => [
					'operator' => $this->getStringValue('operator')
				]
			])
			->touch();
	}

	protected function getStringValue(string $key): string
	{
		return trim(Arr::get($this->data, $key, ''));
	}

	protected function getIntValue(string $key): int
	{
		return (int)filter_var(Arr::get($this->data, $key), FILTER_SANITIZE_NUMBER_INT);
	}
	
	protected function getStatus(): bool
	{
		$status = $this->getStringValue('status');
		return match ($status) {
			'Работает' => true,
			default => false
		};
	}
}