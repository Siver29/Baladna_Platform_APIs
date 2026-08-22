<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AreaStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StoreUserAreaRequest;
use App\Http\Resources\AreaResource;
use App\Http\Responses\ApiResponse;
use App\Models\Area;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserAreaController extends Controller
{
    use ApiResponse;

    /**
     * List the approved areas a citizen can pick from.
     */
    public function index(Request $request): JsonResponse
    {
        $areas = Area::query()
            ->with('parent')
            ->where('status', AreaStatus::APPROVED)
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
     * Suggest a new area, which stays pending until an admin decides on it.
     */
    public function store(StoreUserAreaRequest $request): JsonResponse
    {
        $area = Area::create(array_merge($request->validated(), [
            'status' => AreaStatus::PENDING,
            'user_id' => $request->user()->id,
        ]));

        $area->load('parent');

        return $this->success(
            new AreaResource($area),
            'Area suggested successfully. It is pending review by an administrator.',
            201
        );
    }

    /**
     * List the authenticated citizen's own suggestions with their approval state.
     */
    public function mySuggestions(Request $request): JsonResponse
    {
        $areas = Area::query()
            ->with('parent')
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
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
}
