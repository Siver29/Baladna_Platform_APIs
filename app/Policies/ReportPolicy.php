<?php

namespace App\Policies;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    /**
     * Determine whether the user can view the report.
     */
    public function view(User $user, Report $report): bool
    {
        return $user->isAdmin()
            || $user->isEmployee()
            || ($report->user_id !== null && $report->user_id === $user->id);
    }

    /**
     * Determine whether the user can update the report.
     */
    public function update(User $user, Report $report): bool
    {
        return $user->isCitizen()
            && $report->user_id !== null
            && $report->user_id === $user->id
            && $report->status === ReportStatus::Submitted;
    }

    /**
     * Determine whether the user can cancel the report.
     */
    public function cancel(User $user, Report $report): bool
    {
        return $user->isCitizen()
            && $report->user_id !== null
            && $report->user_id === $user->id
            && in_array($report->status, [ReportStatus::Submitted, ReportStatus::UnderReview], true);
    }

    /**
     * Determine whether the user can confirm the report.
     */
    public function confirm(User $user, Report $report): bool
    {
        return $user->isCitizen() && $report->user_id !== null && $report->user_id !== $user->id;
    }

    /**
     * Determine whether the user can review the report.
     */
    public function review(User $user, Report $report): bool
    {
        return $user->isCitizen()
            && $report->user_id !== null
            && $report->user_id === $user->id
            && $report->status === ReportStatus::Resolved;
    }

    /**
     * Determine whether the user can manage the report as an employee/admin.
     */
    public function manage(User $user, Report $report): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isEmployee() && $user->agency_id === $report->agency_id;
    }
}
