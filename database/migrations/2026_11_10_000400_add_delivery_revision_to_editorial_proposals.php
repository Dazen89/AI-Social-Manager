<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editorial_proposals', function (Blueprint $table): void {
            $table->unsignedInteger('delivery_revision')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('editorial_proposals', function (Blueprint $table): void {
            $table->dropColumn('delivery_revision');
        });
    }
};
