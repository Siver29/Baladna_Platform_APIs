<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AreaStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AreaResource;
use App\Http\Responses\ApiResponse;
use App\Models\Area;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    use ApiResponse;

    /**
     * List public (approved) areas with an optional parent_id filter.
     */
    public function index(Request $request): JsonResponse
    {
        $areas = Area::query()
            ->with('parent')
            ->where('status', AreaStatus::APPROVED)
            ->when($request->has('parent_id'), fn ($q) => $request->parent_id === ''
                ? $q->whereNull('parent_id')
                : $q->where('parent_id', $request->parent_id))
            ->orderBy('name')
            ->get();

        return $this->success(AreaResource::collection($areas));
    }

    /**
     * Show a single public area.
     */
    public function show(Area $area): JsonResponse
    {
        if ($area->status !== AreaStatus::APPROVED) {
            return $this->notFound();
        }

        $area->load('parent', 'children');

        return $this->success(new AreaResource($area));
    }
}
