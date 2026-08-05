<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgencyResource;
use App\Models\Agency;
use Illuminate\Http\Request;

class AgencyController extends Controller
{
    /**
     * List public active agencies.
     */
    public function index(Request $request)
    {
        $agencies = Agency::query()
            ->withCount('categories')
            ->when($request->has('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => AgencyResource::collection($agencies),
        ]);
    }

    /**
     * Show a single public agency.
     */
    public function show(Agency $agency)
    {
        if (! $agency->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
            ], 404);
        }

        $agency->loadCount('categories');

        return response()->json([
            'success' => true,
            'data' => new AgencyResource($agency),
        ]);
    }
}
