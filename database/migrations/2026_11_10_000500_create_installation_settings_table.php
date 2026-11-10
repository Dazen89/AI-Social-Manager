<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_settings', function (Blueprint $table): void {
            $table->string('key', 64)->primary();
            $table->string('value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_settings');
    }
};
