<?php

namespace App\Http\Middleware;

use App\Models\InstallationSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class LoadInstallationName
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (Schema::hasTable('installation_settings')) {
            $name = InstallationSetting::query()->whereKey(InstallationSetting::APP_NAME)->value('value');

            if ($name !== null) {
                config(['app.name' => $name]);
            }
        }

        return $next($request);
    }
}
