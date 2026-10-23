<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editorial_sources', function (Blueprint $table): void {
            $table->string('verification_status', 20)->default('not_checked');
            $table->string('verification_message')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('editorial_sources', function (Blueprint $table): void {
            $table->dropColumn([
                'verification_status',
                'verification_message',
                'last_checked_at',
                'last_verified_at',
            ]);
        });
    }
};
