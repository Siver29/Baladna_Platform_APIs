<?php

namespace App\Services;

use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Report;
use App\Models\ReportStatusHistory;
use App\Models\User;

class ReportAssignmentService
{
    /**
     * The statuses that still count as workload for an employee.
     *
     * @var array<int, ReportStatus>
     */
    protected const OPEN_STATUSES = [
        ReportStatus::Submitted,
        ReportStatus::UnderReview,
        ReportStatus::Accepted,
        ReportStatus::InProgress,
    ];

    /**
     * Resolve the employee responsible for a category.
     *
     * The category's designated employee wins; otherwise the least loaded
     * active employee of the category's agency picks the report up.
     */
    public function resolveForCategory(Category $category): ?User
    {
        $responsible = $category->responsibleEmployee;

        if ($this->isEligible($responsible, $category)) {
            return $responsible;
        }

        return $this->leastLoadedEmployeeForAgency($category->agency_id);
    }

    /**
     * Assign a report to the employee responsible for its category.
     *
     * Returns the assigned employee, or null when the agency has no employee
     * available to take the report (it then waits for a manual assignment).
     */
    public function assignFromCategory(Report $report, Category $category, ?User $actor = null): ?User
    {
        $employee = $this->resolveForCategory($category);

        if (! $employee) {
            return null;
        }

        $report->update(['assigned_employee_id' => $employee->id]);

        ReportStatusHistory::create([
            'report_id' => $report->id,
            'user_id' => $actor?->id,
            'old_status' => $report->status->value,
            'new_status' => $report->status->value,
            'note' => "Automatically assigned to {$employee->name} as the employee responsible for the \"{$category->name}\" category.",
        ]);

        return $employee;
    }

    /**
     * Determine whether an employee may take reports for a category.
     */
    protected function isEligible(?User $employee, Category $category): bool
    {
        return $employee !== null
            && $employee->role === Role::Employee
            && $employee->is_active
            && $employee->agency_id === $category->agency_id;
    }

    /**
     * Find the active employee of an agency with the fewest open reports.
     */
    protected function leastLoadedEmployeeForAgency(?int $agencyId): ?User
    {
        if ($agencyId === null) {
            return null;
        }

        return User::query()
            ->where('role', Role::Employee)
            ->where('agency_id', $agencyId)
            ->where('is_active', true)
            ->withCount(['assignedReports' => fn ($q) => $q->whereIn('status', array_map(
                fn (ReportStatus $status) => $status->value,
                self::OPEN_STATUSES
            ))])
            ->orderBy('assigned_reports_count')
            ->orderBy('id')
            ->first();
    }
}
