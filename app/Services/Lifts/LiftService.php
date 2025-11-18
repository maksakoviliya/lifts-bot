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

		$lift = Lift::query()
			->updateOrCreate([
				'name' => $this->getStringValue('name')
			], [
				'raise_time' => $this->getIntValue('rise_time'),
				'length' => $this->getIntValue('length'),
				'data' => [
					'operator' => $this->getStringValue('operator')
				]
			]);

		$lift->update([
			'is_active' => $this->getStatus($lift),
		]);
	}

	protected function getStringValue(string $key): string
	{
		return trim(Arr::get($this->data, $key, ''));
	}

	protected function getIntValue(string $key): int
	{
		return (int)filter_var(Arr::get($this->data, $key), FILTER_SANITIZE_NUMBER_INT);
	}

	protected function getStatus(Lift $lift): bool
	{
		$isEnabled = !is_null($lift->enabled_at) && !is_null($lift->enabled_by);
		$isDisabled = !is_null($lift->disabled_at) && !is_null($lift->disabled_by);

		if ($isEnabled && $isDisabled) {
			return $lift->enabled_at > $lift->disabled_at;
		}
		elseif ($isEnabled) {
			return true;
		}
		elseif ($isDisabled) {
			return false;
		}
		
		$status = $this->getStringValue('status');
		return match ($status) {
			'Работает' => true,
			default => false
		};
	}
}