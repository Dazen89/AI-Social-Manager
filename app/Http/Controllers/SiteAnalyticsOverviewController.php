<?php

namespace App\Http\Controllers;

use App\Exceptions\GoogleAnalyticsException;
use App\Exceptions\GoogleAnalyticsReportException;
use App\Exceptions\GoogleSearchConsoleException;
use App\Models\AiIntegration;
use App\Models\Site;
use App\Services\GoogleAnalyticsReportService;
use App\Services\GoogleSearchConsoleReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteAnalyticsOverviewController extends Controller
{
    public function __invoke(Request $request, Site $site, GoogleAnalyticsReportService $reports, GoogleSearchConsoleReportService $searchReports): View
    {
        $requestedDays = $request->integer('range', 28);
        $days = in_array($requestedDays, [7, 28, 90], true) ? $requestedDays : 28;
        $site->load('googleAnalyticsProperty.connection', 'googleSearchConsoleProperty.connection');
        $property = $site->googleAnalyticsProperty;
        $searchProperty = $site->googleSearchConsoleProperty;
        $overview = null;
        $reportError = null;
        $searchOverview = null;
        $searchReportError = null;

        if ($property?->connection?->status === 'active') {
            try {
                $overview = $reports->overview($property, $site->host, $days);
            } catch (GoogleAnalyticsException $exception) {
                $reportError = $exception instanceof GoogleAnalyticsReportException
                    ? $exception
                    : new GoogleAnalyticsReportException($exception->getMessage());
            }
        }

        if ($searchProperty?->connection?->status === 'active') {
            try {
                $searchOverview = $searchReports->overview($searchProperty, $site->host, $days);
            } catch (GoogleSearchConsoleException $exception) {
                $searchReportError = $exception->getMessage();
            }
        }

        return view('sites.show', [
            'site' => $site,
            'analyticsProperty' => $property,
            'analyticsIsConnected' => $property?->connection?->status === 'active',
            'overview' => $overview,
            'reportError' => $reportError,
            'searchIsConnected' => $searchProperty?->connection?->status === 'active',
            'searchOverview' => $searchOverview,
            'searchReportError' => $searchReportError,
            'days' => $days,
            'activeAiIntegration' => AiIntegration::query()
                ->where('active_marker', 'active')
                ->first(),
            'analyticsInsight' => $site->analyticsInsightReports()
                ->where('range_days', $days)
                ->latest('generated_at')
                ->first(),
        ]);
    }
}
