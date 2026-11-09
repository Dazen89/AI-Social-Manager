<?php

namespace App\Http\Controllers;

use App\Models\AiIntegration;
use App\Models\Site;
use App\Models\XConnection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'sites' => Site::query()
                ->withExists([
                    'googleAnalyticsProperty as analytics_connected' => fn ($property) => $property
                        ->whereHas('connection', fn ($connection) => $connection->where('status', 'active')),
                    'googleSearchConsoleProperty as search_console_connected' => fn ($property) => $property
                        ->whereHas('connection', fn ($connection) => $connection->where('status', 'active')),
                ])
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(12),
            'xConnected' => XConnection::query()->where('status', 'active')->exists(),
            'aiConnected' => AiIntegration::query()
                ->where('active_marker', 'active')
                ->where('status', 'verified')
                ->exists(),
        ]);
    }
}
