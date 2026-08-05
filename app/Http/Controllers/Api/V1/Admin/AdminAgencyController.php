<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAgencyRequest;
use App\Http\Requests\Admin\UpdateAgencyRequest;
use App\Http\Resources\AgencyResource;
use App\Http\Responses\ApiResponse;
use App\Models\Agency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAgencyController extends Controller
{
    use ApiResponse;

    /**
     * List all agencies (including inactive).
     */
    public function index(Request $request): JsonResponse
    {
        $agencies = Agency::query()
            ->withCount('categories')
            ->when($request->has('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return $this->successCollection(
            AgencyResource::collection($agencies),
            200,
            [
                'current_page' => $agencies->currentPage(),
                'per_page' => $agencies->perPage(),
                'total' => $agencies->total(),
                'last_page' => $agencies->lastPage(),
            ]
        );
    }

    /**
     * Create a new agency.
     */
    public function store(StoreAgencyRequest $request): JsonResponse
    {
        $agency = Agency::create($request->validated());

        return $this->success(new AgencyResource($agency), 'Agency created successfully.', 201);
    }

    /**
     * Show a single agency.
     */
    public function show(Agency $agency): JsonResponse
    {
        $agency->loadCount('categories');

        return $this->success(new AgencyResource($agency));
    }

    /**
     * Update an agency.
     */
    public function update(UpdateAgencyRequest $request, Agency $agency): JsonResponse
    {
        $agency->update($request->validated());

        return $this->success(new AgencyResource($agency), 'Agency updated successfully.');
    }

    /**
     * Delete an agency, or refuse if it has dependents.
     */
    public function destroy(Agency $agency): JsonResponse
    {
        if ($agency->reports()->exists() || $agency->users()->exists() || $agency->categories()->exists()) {
            return $this->error('Cannot delete an agency that is referenced by reports, users, or categories.', 422);
        }

        $agency->delete();

        return $this->success(null, 'Agency deleted successfully.', 204);
    }
}
