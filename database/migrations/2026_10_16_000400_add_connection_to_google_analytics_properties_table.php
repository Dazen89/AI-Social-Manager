<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_analytics_properties', function (Blueprint $table): void {
            $table->foreignId('google_analytics_connection_id')
                ->nullable()
                ->index();
            $table->string('account_name')->nullable();
            $table->string('property_name')->nullable();
            $table->timestamp('last_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('google_analytics_properties', function (Blueprint $table): void {
            $table->dropIndex(['google_analytics_connection_id']);
            $table->dropColumn([
                'google_analytics_connection_id',
                'account_name',
                'property_name',
                'last_verified_at',
            ]);
        });
    }
};
