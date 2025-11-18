<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	public function up(): void
	{
		Schema::table('lifts', function (Blueprint $table) {
			$table->dateTime('disabled_at')->nullable();
			$table->foreignIdFor(User::class, 'disabled_by')->nullable()->constrained()->nullOnDelete();
			$table->dateTime('enabled_at')->nullable();
			$table->foreignIdFor(User::class, 'enabled_by')->nullable()->constrained()->nullOnDelete();
		});
	}

	public function down(): void
	{
		Schema::table('lifts', function (Blueprint $table) {
			$table->dropForeign('lifts_disabled_by_foreign');
			$table->dropForeign('lifts_enabled_by_foreign');
			
			$table->dropColumn([
				'disabled_at',
				'disabled_by',
				'enabled_at',
				'enabled_by',
			]);
		});
	}
};
