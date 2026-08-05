<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Priority;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\StoreReportImageRequest;
use App\Http\Requests\Report\StoreReportRequest;
use App\Http\Requests\Report\StoreReportReviewRequest;
use App\Http\Requests\Report\UpdateReportRequest;
use App\Http\Resources\ReportResource;
use App\Http\Resources\ReportStatusHistoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Category;
use App\Models\Report;
use App\Models\ReportConfirmation;
use App\Models\ReportImage;
use App\Models\ReportStatusHistory;
use App\Models\User;
use App\Services\ReportStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ReportController extends Controller
{
    use ApiResponse;

    /**
     * List reports with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 10), 50);

        $reports = Report::query()
            ->with(['category', 'area', 'agency', 'user', 'images'])
            ->withCount('confirmations')
            ->filter($request->all())
            ->paginate($perPage);

        $reports->getCollection()->each(function (Report $report) {
            $report->confirmed_by_me = $report->confirmations()
                ->where('user_id', auth()->id())
                ->exists();
        });

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
     * Create a new report.
     */
    public function store(StoreReportRequest $request): JsonResponse
    {
        $category = Category::findOrFail($request->category_id);
        $user = $request->user();

        $report = DB::transaction(function () use ($request, $category, $user) {
            $report = Report::create([
                'reference_number' => $this->generateReferenceNumber(),
                'user_id' => $user->id,
                'category_id' => $request->category_id,
                'area_id' => $request->area_id,
                'agency_id' => $category->agency_id,
                'title' => $request->title,
                'description' => $request->description,
                'address' => $request->address,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'priority' => Priority::Normal,
                'status' => ReportStatus::Submitted,
            ]);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('reports', 'public');
                    $report->images()->create(['image_path' => $path]);
                }
            }

            ReportStatusHistory::create([
                'report_id' => $report->id,
                'user_id' => $user->id,
                'old_status' => null,
                'new_status' => ReportStatus::Submitted->value,
                'note' => 'Report submitted.',
            ]);

            return $report;
        });

        $report->load(['category', 'area', 'agency', 'user', 'images']);

        return $this->success(new ReportResource($report), 'Report created successfully.', 201);
    }

    /**
     * Show a single report.
     */
    public function show(Request $request, Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        $report->load(['category', 'area', 'agency', 'user', 'images', 'review', 'assignedEmployee']);
        $report->confirmed_by_me = $report->confirmations()
            ->where('user_id', $request->user()->id)
            ->exists();

        return $this->success(new ReportResource($report));
    }

    /**
     * Update the citizen's own submitted report.
     */
    public function update(UpdateReportRequest $request, Report $report): JsonResponse
    {
        $this->authorize('update', $report);

        $report->update($request->validated());

        $report->load(['category', 'area', 'agency', 'user', 'images']);

        return $this->success(new ReportResource($report), 'Report updated successfully.');
    }

    /**
     * Cancel the citizen's own report.
     */
    public function cancel(Request $request, Report $report): JsonResponse
    {
        $this->authorize('cancel', $report);

        try {
            $report = app(ReportStatusService::class)
                ->transition($report, ReportStatus::Cancelled, $request->user(), 'Report cancelled by the citizen.');
        } catch (InvalidArgumentException) {
            return $this->error('This report cannot be cancelled in its current state.', 409);
        }

        $report->load(['category', 'area', 'agency', 'user', 'images']);

        return $this->success(new ReportResource($report), 'Report cancelled successfully.');
    }

    /**
     * List the authenticated user's own reports.
     */
    public function myReports(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 10), 50);

        $reports = Report::query()
            ->where('user_id', $request->user()->id)
            ->with(['category', 'area', 'agency', 'user', 'images'])
            ->withCount('confirmations')
            ->orderBy('created_at', 'desc')
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
     * Upload images to an existing report.
     */
    public function storeImages(StoreReportImageRequest $request, Report $report): JsonResponse
    {
        $this->authorize('update', $report);

        $existing = $report->images()->count();
        $incoming = count($request->file('images', []));

        if ($existing + $incoming > 5) {
            return $this->error('A report can have a maximum of 5 images.', 422);
        }

        foreach ($request->file('images') as $image) {
            $path = $image->store('reports', 'public');
            $report->images()->create(['image_path' => $path]);
        }

        $report->load('images');

        return $this->success(new ReportResource($report), 'Images uploaded successfully.');
    }

    /**
     * Delete a report image.
     */
    public function destroyImage(Request $request, Report $report, ReportImage $image): JsonResponse
    {
        $this->authorize('update', $report);

        if ($image->report_id !== $report->id) {
            return $this->notFound('Image not found for this report.');
        }

        Storage::disk('public')->delete($image->image_path);
        $image->delete();

        return $this->success(null, 'Image deleted successfully.', 204);
    }

    /**
     * Confirm another citizen's report.
     */
    public function confirm(Request $request, Report $report): JsonResponse
    {
        $this->authorize('confirm', $report);

        $exists = ReportConfirmation::where('report_id', $report->id)
            ->where('user_id', $request->user()->id)
            ->exists();

        if ($exists) {
            return $this->error('You have already confirmed this report.', 409);
        }

        ReportConfirmation::create([
            'report_id' => $report->id,
            'user_id' => $request->user()->id,
        ]);

        return $this->success([
            'confirmations_count' => $report->confirmations()->count(),
        ], 'Report confirmed successfully.');
    }

    /**
     * Remove the authenticated user's confirmation.
     */
    public function unconfirm(Request $request, Report $report): JsonResponse
    {
        $deleted = ReportConfirmation::where('report_id', $report->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        if (! $deleted) {
            return $this->error('You have not confirmed this report.', 404);
        }

        return $this->success([
            'confirmations_count' => $report->confirmations()->count(),
        ], 'Confirmation removed successfully.');
    }

    /**
     * Show the status history of a report.
     */
    public function history(Request $request, Report $report): JsonResponse
    {
        $this->authorize('view', $report);

        $history = ReportStatusHistory::with('user')
            ->where('report_id', $report->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(ReportStatusHistoryResource::collection($history));
    }

    /**
     * Submit a review for the owner's own resolved report.
     */
    public function review(StoreReportReviewRequest $request, Report $report): JsonResponse
    {
        $this->authorize('review', $report);

        if ($report->review()->exists()) {
            return $this->error('This report has already been reviewed.', 409);
        }

        $review = $report->review()->create([
            'user_id' => $request->user()->id,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return $this->success([
            'id' => $review->id,
            'rating' => $review->rating,
            'comment' => $review->comment,
        ], 'Review submitted successfully.', 201);
    }

    /**
     * Generate a unique reference number like BLD-2026-000001.
     */
    protected function generateReferenceNumber(): string
    {
        $prefix = 'BLD-'.now()->year.'-';

        do {
            $ref = $prefix.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (Report::where('reference_number', $ref)->exists());

        return $ref;
    }
}
