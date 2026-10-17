<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_search_console_properties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id');
            $table->unique('site_id', 'gsc_properties_site_unique');
            $table->foreign('site_id', 'gsc_properties_site_fk')
                ->references('id')
                ->on('sites')
                ->cascadeOnDelete();
            $table->foreignId('google_search_console_connection_id');
            $table->foreign('google_search_console_connection_id', 'gsc_properties_connection_fk')
                ->references('id')
                ->on('google_search_console_connections')
                ->cascadeOnDelete();
            $table->string('property_url', 2048);
            $table->string('property_type', 32);
            $table->string('permission_level', 32)->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_search_console_properties');
    }
};
