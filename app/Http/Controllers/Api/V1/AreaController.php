<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AreaResource;
use App\Models\Area;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    /**
     * List public areas with optional parent_id filter.
     */
    public function index(Request $request)
    {
        $areas = Area::query()
            ->with('parent')
            ->when($request->has('parent_id'), fn ($q) => $request->parent_id === ''
                ? $q->whereNull('parent_id')
                : $q->where('parent_id', $request->parent_id))
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => AreaResource::collection($areas),
        ]);
    }

    /**
     * Show a single public area.
     */
    public function show(Area $area)
    {
        $area->load('parent', 'children');

        return response()->json([
            'success' => true,
            'data' => new AreaResource($area),
        ]);
    }
}
