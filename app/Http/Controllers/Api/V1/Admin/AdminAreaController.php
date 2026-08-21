<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAreaRequest;
use App\Http\Requests\Admin\UpdateAreaRequest;
use App\Http\Resources\AreaResource;
use App\Http\Responses\ApiResponse;
use App\Models\Area;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAreaController extends Controller
{
    use ApiResponse;

    /**
     * List all areas (including inactive).
     */
    public function index(Request $request): JsonResponse
    {
        $areas = Area::query()
            ->with('parent')
            ->when($request->has('parent_id'), fn ($q) => $q->where('parent_id', $request->parent_id))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return $this->successCollection(
            AreaResource::collection($areas),
            200,
            [
                'current_page' => $areas->currentPage(),
                'per_page' => $areas->perPage(),
                'total' => $areas->total(),
                'last_page' => $areas->lastPage(),
            ]
        );
    }

    /**
     * Create a new area.
     */
    public function store(StoreAreaRequest $request): JsonResponse
    {
        $area = Area::create($request->validated());

        return $this->success(new AreaResource($area), 'Area created successfully.', 201);
    }

    /**
     * Show a single area.
     */
    public function show(Area $area): JsonResponse
    {
        $area->load('parent', 'children');

        return $this->success(new AreaResource($area));
    }

    /**
     * Update an area.
     */
    public function update(UpdateAreaRequest $request, Area $area): JsonResponse
    {
        $area->update($request->validated());

        return $this->success(new AreaResource($area), 'Area updated successfully.');
    }

    /**
     * Delete an area, or refuse if it has dependents.
     */
    public function destroy(Area $area)
    {
        if ($area->reports()->exists()) {
            return $this->error(
                'Cannot delete an area that is referenced by reports.',
                422
            );
        }
    
        $area->delete();
    
        return response()->noContent();
    }
}
