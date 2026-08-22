<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AreaStatus;
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
            ->with(['parent', 'user'])
            ->when($request->has('parent_id'), fn ($q) => $request->parent_id === ''
                ? $q->whereNull('parent_id')
                : $q->where('parent_id', $request->parent_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
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
        // Areas an admin creates are live immediately; only citizen
        // suggestions go through the pending queue.
        $area = Area::create(array_merge($request->validated(), [
            'status' => AreaStatus::APPROVED,
        ]));

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
        $data = $request->validated();

        if (array_key_exists('parent_id', $data) && (int) $data['parent_id'] === $area->id) {
            return $this->error('An area cannot be its own parent.', 422);
        }

        $area->update($data);

        return $this->success(new AreaResource($area->load('parent')), 'Area updated successfully.');
    }

    /**
     * Delete an area, or refuse if it has dependents.
     */
    public function destroy(Area $area): JsonResponse
    {
        if ($area->reports()->exists()) {
            return $this->error(
                'Cannot delete an area that is referenced by reports.',
                422
            );
        }

        if ($area->posts()->exists()) {
            return $this->error(
                'Cannot delete an area that is referenced by community posts.',
                422
            );
        }

        if ($area->children()->exists()) {
            return $this->error(
                'Cannot delete an area that has sub-areas. Delete or move them first.',
                422
            );
        }

        $area->delete();

        return $this->success(null, 'Area deleted successfully.', 204);
    }
}
