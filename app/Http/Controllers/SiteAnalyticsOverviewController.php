<?php

namespace App\Http\Controllers;

use App\Exceptions\GoogleAnalyticsException;
use App\Exceptions\GoogleAnalyticsReportException;
use App\Models\Site;
use App\Services\GoogleAnalyticsReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteAnalyticsOverviewController extends Controller
{
    public function __invoke(Request $request, Site $site, GoogleAnalyticsReportService $reports): View
    {
        $requestedDays = $request->integer('range', 28);
        $days = in_array($requestedDays, [7, 28, 90], true) ? $requestedDays : 28;
        $site->load('googleAnalyticsProperty.connection');
        $property = $site->googleAnalyticsProperty;
        $overview = null;
        $reportError = null;

        if ($property?->connection?->status === 'active') {
            try {
                $overview = $reports->overview($property, $site->host, $days);
            } catch (GoogleAnalyticsException $exception) {
                $reportError = $exception instanceof GoogleAnalyticsReportException
                    ? $exception
                    : new GoogleAnalyticsReportException($exception->getMessage());
            }
        }

        return view('sites.show', [
            'site' => $site,
            'analyticsProperty' => $property,
            'analyticsIsConnected' => $property?->connection?->status === 'active',
            'overview' => $overview,
            'reportError' => $reportError,
            'days' => $days,
        ]);
    }
}
