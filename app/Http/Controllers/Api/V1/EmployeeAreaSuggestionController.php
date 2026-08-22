<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AreaStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AreaResource;
use App\Http\Responses\ApiResponse;
use App\Models\Area;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Enum;

class EmployeeAreaSuggestionController extends Controller
{
    use ApiResponse;

    /**
     * List citizen area suggestions with their approval state.
     *
     * Employees get a read-only view; only admins can approve or reject.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['sometimes', new Enum(AreaStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $areas = Area::query()
            ->with(['parent', 'user'])
            ->whereNotNull('user_id')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
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

    /**
     * Show a single area suggestion and whether it was approved.
     */
    public function show(Area $area): JsonResponse
    {
        $area->load(['parent', 'user']);

        return $this->success(new AreaResource($area));
    }
}
