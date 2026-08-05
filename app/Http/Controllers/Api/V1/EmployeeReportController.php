<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\AddPublicNoteRequest;
use App\Http\Requests\Report\UpdateReportStatusRequest;
use App\Http\Resources\ReportResource;
use App\Http\Responses\ApiResponse;
use App\Models\Report;
use App\Services\ReportStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class EmployeeReportController extends Controller
{
    use ApiResponse;

    /**
     * List reports scoped to the authenticated employee's agency.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 10), 50);

        $reports = Report::query()
            ->with(['category', 'area', 'agency', 'user', 'images'])
            ->withCount('confirmations')
            ->when(! $request->user()->isAdmin(), fn ($q) => $q->where('agency_id', $request->user()->agency_id))
            ->filter($request->all())
            ->paginate($perPage);

        return $this->successCollection(
            ReportResource::collection($reports),
            200,
            [
                'current_page' => $reports->currentPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
                'last_page' => $reports->lastPage(),
            ]
        );
    }

    /**
     * Show a single report scoped to the employee's agency (or any for admins).
     */
    public function show(Request $request, Report $report): JsonResponse
    {
        $this->authorize('manage', $report);

        $report->load(['category', 'area', 'agency', 'user', 'images', 'statusHistories.user', 'assignedEmployee']);

        return $this->success(new ReportResource($report));
    }

    /**
     * Update the report status.
     */
    public function updateStatus(UpdateReportStatusRequest $request, Report $report): JsonResponse
    {
        $this->authorize('manage', $report);

        $newStatus = ReportStatus::from($request->status);

        $note = match ($newStatus) {
            ReportStatus::Rejected => $request->rejection_reason,
            ReportStatus::Resolved => $request->resolution_note,
            default => $request->note,
        };

        try {
            $report = app(ReportStatusService::class)->transition(
                $report,
                $newStatus,
                $request->user(),
                $note
            );
        } catch (InvalidArgumentException) {
            return $this->error(
                "Invalid status transition from {$report->status->value} to {$newStatus->value}.",
                409
            );
        }

        $report->load(['category', 'area', 'agency', 'user', 'images']);

        return $this->success(new ReportResource($report), 'Report status updated successfully.');
    }

    /**
     * Add (or overwrite) a public note on a report.
     */
    public function addPublicNote(AddPublicNoteRequest $request, Report $report): JsonResponse
    {
        $this->authorize('manage', $report);

        $report->update(['public_note' => $request->note]);

        return $this->success([
            'id' => $report->id,
            'public_note' => $report->public_note,
        ], 'Public note added successfully.');
    }
}
