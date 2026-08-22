<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\AddPublicNoteRequest;
use App\Http\Requests\Report\UpdateReportStatusRequest;
use App\Http\Resources\ReportResource;
use App\Http\Resources\ReportReviewResource;
use App\Http\Responses\ApiResponse;
use App\Models\Report;
use App\Models\ReportReview;
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
            ->with(['category', 'area', 'agency', 'user', 'images', 'review.user', 'assignedEmployee'])
            ->withCount('confirmations')
            ->when(! $request->user()->isAdmin(), fn ($q) => $q->where('agency_id', $request->user()->agency_id))
            ->when($request->boolean('assigned_to_me'), fn ($q) => $q->where('assigned_employee_id', $request->user()->id))
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

        $report->load(['category', 'area', 'agency', 'user', 'images', 'statusHistories.user', 'assignedEmployee', 'review.user']);

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

    /**
     * List the citizen reviews left on the employee's agency reports.
     *
     * Admins see every review. Pass assigned_to_me=1 to only get the reviews
     * of the reports the employee is assigned to.
     */
    public function reviews(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 10), 50);
        $user = $request->user();

        $query = ReportReview::query()
            ->with(['user', 'report'])
            ->whereHas('report', function ($q) use ($request, $user) {
                $q->when(! $user->isAdmin(), fn ($sub) => $sub->where('agency_id', $user->agency_id))
                    ->when($request->boolean('assigned_to_me'), fn ($sub) => $sub->where('assigned_employee_id', $user->id))
                    ->when($request->filled('report_id'), fn ($sub) => $sub->where('id', $request->integer('report_id')))
                    ->when($request->filled('category_id'), fn ($sub) => $sub->where('category_id', $request->integer('category_id')))
                    ->when($request->filled('area_id'), fn ($sub) => $sub->where('area_id', $request->integer('area_id')));
            })
            ->when($request->filled('rating'), fn ($q) => $q->where('rating', $request->integer('rating')));

        $averageRating = (clone $query)->avg('rating');
        $total = (clone $query)->count();

        $reviews = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->successCollection(
            ReportReviewResource::collection($reviews),
            200,
            [
                'current_page' => $reviews->currentPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
                'last_page' => $reviews->lastPage(),
                'average_rating' => $averageRating !== null ? round((float) $averageRating, 2) : null,
                'reviews_count' => $total,
            ]
        );
    }

    /**
     * Show the citizen review left on a single report.
     */
    public function review(Request $request, Report $report): JsonResponse
    {
        $this->authorize('manage', $report);

        $review = $report->review()->with(['user', 'report'])->first();

        if (! $review) {
            return $this->notFound('This report has not been reviewed yet.');
        }

        return $this->success(new ReportReviewResource($review));
    }
}
