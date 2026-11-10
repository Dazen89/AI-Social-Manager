<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('x_connections', function (Blueprint $table): void {
            $table->renameColumn('publishing_enabled', 'manual_publishing_enabled');
        });

        Schema::table('x_connections', function (Blueprint $table): void {
            $table->boolean('automatic_publishing_enabled')->default(false);
        });

        DB::table('x_connections')->where('manual_publishing_enabled', true)->update(['automatic_publishing_enabled' => true]);
    }

    public function down(): void
    {
        // The combined switch can be restored only for accounts that allow both modes.
        DB::table('x_connections')->where('automatic_publishing_enabled', false)->update(['manual_publishing_enabled' => false]);

        Schema::table('x_connections', function (Blueprint $table): void {
            $table->dropColumn('automatic_publishing_enabled');
        });

        Schema::table('x_connections', function (Blueprint $table): void {
            $table->renameColumn('manual_publishing_enabled', 'publishing_enabled');
        });
    }
};
