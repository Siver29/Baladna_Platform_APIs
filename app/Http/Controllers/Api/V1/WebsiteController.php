<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReportResource;
use App\Http\Resources\WebsiteStatResource;
use App\Http\Responses\ApiResponse;
use App\Models\Report;
use App\Services\WebsiteStatsService;
use Illuminate\Http\JsonResponse;

class WebsiteController extends Controller
{
    use ApiResponse;

    /**
     * Return the latest 6 anonymous reports (no authentication required).
     *
     * These are displayed on the public landing page.
     */
    public function latestAnonymousReports(): JsonResponse
    {
        $reports = Report::query()
            ->whereNull('user_id')
            ->with(['category', 'area', 'agency', 'images'])
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get();

        return $this->success(ReportResource::collection($reports), 'Latest anonymous reports retrieved.');
    }

    /**
     * Return the website status table data (no authentication required).
     */
    public function stats(): JsonResponse
    {
        $stats = app(WebsiteStatsService::class)->get();

        return $this->success(new WebsiteStatResource($stats), 'Website statistics retrieved.');
    }
}
