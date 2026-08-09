<?php

namespace App\Services;

use App\Enums\ReportStatus;
use App\Models\Agency;
use App\Models\Area;
use App\Models\Category;
use App\Models\Report;
use App\Models\WebsiteStat;

class WebsiteStatsService
{
    /**
     * The singleton row id used to store the aggregated website stats.
     */
    protected const STAT_ROW_ID = 1;

    /**
     * Recompute the website statistics from the current database state.
     *
     * This is called whenever a new request is submitted or a report is resolved.
     */
    public function refresh(): WebsiteStat
    {
        $stats = [
            'total_reports' => Report::count(),
            'resolved_reports' => Report::where('status', ReportStatus::Resolved->value)->count(),
            'pending_reports' => Report::whereNotIn('status', [
                ReportStatus::Resolved->value,
                ReportStatus::Rejected->value,
                ReportStatus::Cancelled->value,
            ])->count(),
            'anonymous_reports' => Report::whereNull('user_id')->count(),
            'active_categories' => Category::where('is_active', true)->count(),
            'active_areas' => Area::count(),
            'active_agencies' => Agency::where('is_active', true)->count(),
        ];

        return WebsiteStat::updateOrCreate(
            ['id' => self::STAT_ROW_ID],
            $stats
        );
    }

    /**
     * Return the current website statistics, refreshing them if none exist yet.
     */
    public function get(): WebsiteStat
    {
        $stats = WebsiteStat::find(self::STAT_ROW_ID);

        if ($stats === null) {
            return $this->refresh();
        }

        return $stats;
    }
}
