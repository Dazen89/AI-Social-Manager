<?php

namespace App\Http\Controllers;

use App\Models\AiIntegration;
use App\Models\Site;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $aiIntegration = AiIntegration::query()
            ->where('active_marker', 'active')
            ->first();

        return view('dashboard', [
            'siteCount' => Site::query()->count(),
            'connectedAnalyticsCount' => Site::query()
                ->whereHas('googleAnalyticsProperty.connection', fn ($query) => $query->where('status', 'active'))
                ->count(),
            'aiIntegration' => $aiIntegration,
        ]);
    }
}
