<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifts', function (Blueprint $table) {
            $table->id();
			
			$table->string('name')->unique();
			$table->boolean('is_active')->default(false);
			$table->unsignedSmallInteger('raise_time')->nullable();
			$table->unsignedSmallInteger('length')->nullable();
			$table->json('data')->nullable();
			
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lifts');
    }
};
