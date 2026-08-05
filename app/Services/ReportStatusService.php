<?php

namespace App\Services;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\ReportStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReportStatusService
{
    /**
     * The allowed status transitions in the workflow.
     *
     * @var array<string, array<int, ReportStatus>>
     */
    protected const TRANSITIONS = [
        'submitted' => [ReportStatus::UnderReview, ReportStatus::Cancelled],
        'under_review' => [ReportStatus::Accepted, ReportStatus::Rejected, ReportStatus::Cancelled],
        'accepted' => [ReportStatus::InProgress],
        'in_progress' => [ReportStatus::Resolved],
        'resolved' => [],
        'rejected' => [],
        'cancelled' => [],
    ];

    /**
     * Determine whether a transition is allowed.
     */
    public function canTransition(Report $report, ReportStatus $newStatus): bool
    {
        if ($report->status === $newStatus) {
            return false;
        }

        return in_array($newStatus, self::TRANSITIONS[$report->status->value] ?? [], true);
    }

    /**
     * Apply a status transition, persisting the change and a history record.
     */
    public function transition(Report $report, ReportStatus $newStatus, User $actor, ?string $note = null): Report
    {
        if (! $this->canTransition($report, $newStatus)) {
            throw new InvalidArgumentException(
                "Invalid status transition from {$report->status->value} to {$newStatus->value}."
            );
        }

        return DB::transaction(function () use ($report, $newStatus, $actor, $note) {
            $oldStatus = $report->status;

            $report->status = $newStatus;

            if ($newStatus === ReportStatus::Resolved) {
                $report->resolved_at = now();
                $report->resolution_note = $note;
            }

            if ($newStatus === ReportStatus::Rejected) {
                $report->rejection_reason = $note;
            }

            if ($newStatus === ReportStatus::Cancelled) {
                $report->cancelled_at = now();
            }

            $report->save();

            ReportStatusHistory::create([
                'report_id' => $report->id,
                'user_id' => $actor->id,
                'old_status' => $oldStatus->value,
                'new_status' => $newStatus->value,
                'note' => $note,
            ]);

            return $report;
        });
    }
}
