<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('editorial_sources', function (Blueprint $table): void {
            $table->timestamp('promotion_authorized_at')->nullable();
            $table->foreignId('promotion_authorized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('editorial_schedules', function (Blueprint $table): void {
            $table->boolean('x_autopublish_enabled')->default(false);
        });
        Schema::table('editorial_proposals', function (Blueprint $table): void {
            $table->boolean('manual_override')->default(false);
            $table->string('x_post_id', 100)->nullable()->unique();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('delivery_attempted_at')->nullable();
            $table->text('delivery_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('editorial_proposals', function (Blueprint $table): void {
            $table->dropColumn(['manual_override', 'x_post_id', 'published_at', 'delivery_attempted_at', 'delivery_error']);
        });
        Schema::table('editorial_schedules', function (Blueprint $table): void {
            $table->dropColumn('x_autopublish_enabled');
        });
        Schema::table('editorial_sources', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('promotion_authorized_by_user_id');
            $table->dropColumn('promotion_authorized_at');
        });
    }
};
