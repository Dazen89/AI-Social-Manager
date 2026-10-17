<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_search_console_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('authorized_by_user_id')->nullable();
            $table->foreign('authorized_by_user_id', 'gsc_connections_authorized_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->string('status', 32)->default('active');
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_search_console_connections');
    }
};
