<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_integrations', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('provider', 64);
            $table->string('model', 128);
            $table->text('api_key');
            $table->string('active_marker', 16)->nullable()->unique();
            $table->foreignId('configured_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('configured');
            $table->timestamp('configured_at')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index('provider');
        });

        $legacy = DB::table('integration_settings')
            ->where('provider', 'ai')
            ->first();

        if (! $legacy) {
            return;
        }

        try {
            $configuration = json_decode(
                Crypt::decryptString((string) $legacy->configuration),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (Throwable) {
            return;
        }

        $provider = (string) data_get($configuration, 'provider');
        $model = (string) data_get($configuration, 'model');
        $apiKey = (string) data_get($configuration, 'api_key');

        if ($provider === '' || $model === '' || $apiKey === '') {
            return;
        }

        $now = now();

        DB::table('ai_integrations')->insert([
            'name' => (string) config("ai.providers.{$provider}.name", ucfirst($provider)),
            'provider' => $provider,
            'model' => $model,
            'api_key' => Crypt::encryptString($apiKey),
            'active_marker' => 'active',
            'configured_by_user_id' => $legacy->configured_by_user_id,
            'status' => $legacy->status,
            'configured_at' => $legacy->configured_at,
            'last_tested_at' => $legacy->last_tested_at,
            'last_error' => $legacy->last_error,
            'created_at' => $legacy->created_at ?? $now,
            'updated_at' => $legacy->updated_at ?? $now,
        ]);

        DB::table('integration_settings')
            ->where('provider', 'ai')
            ->delete();
    }

    public function down(): void
    {
        if (DB::table('ai_integrations')->count() > 1) {
            throw new RuntimeException('Rollback blocked: the legacy schema can preserve only one AI integration.');
        }

        $active = DB::table('ai_integrations')
            ->whereNotNull('active_marker')
            ->first()
            ?? DB::table('ai_integrations')->orderBy('id')->first();

        if ($active) {
            DB::table('integration_settings')->updateOrInsert([
                'provider' => 'ai',
            ], [
                'configuration' => Crypt::encryptString(json_encode([
                    'provider' => $active->provider,
                    'model' => $active->model,
                    'api_key' => Crypt::decryptString($active->api_key),
                ], JSON_THROW_ON_ERROR)),
                'configured_by_user_id' => $active->configured_by_user_id,
                'status' => $active->status,
                'configured_at' => $active->configured_at,
                'last_tested_at' => $active->last_tested_at,
                'last_error' => $active->last_error,
                'created_at' => $active->created_at,
                'updated_at' => $active->updated_at,
            ]);
        }

        Schema::dropIfExists('ai_integrations');
    }
};
