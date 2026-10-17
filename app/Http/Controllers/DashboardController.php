<?php

namespace App\Http\Controllers;

use App\Models\IntegrationSetting;
use App\Models\Site;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $aiSetting = IntegrationSetting::query()
            ->where('provider', IntegrationSetting::AI)
            ->first();

        return view('dashboard', [
            'siteCount' => Site::query()->count(),
            'connectedAnalyticsCount' => Site::query()
                ->whereHas('googleAnalyticsProperty.connection', fn ($query) => $query->where('status', 'active'))
                ->count(),
            'aiIntegration' => $aiSetting?->configuration,
        ]);
    }
}
