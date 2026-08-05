<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * List public active categories with optional agency_id/active filters.
     */
    public function index(Request $request)
    {
        $categories = Category::query()
            ->with('agency')
            ->when($request->has('agency_id'), fn ($q) => $q->where('agency_id', $request->agency_id))
            ->when($request->has('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => CategoryResource::collection($categories),
        ]);
    }

    /**
     * Show a single public category.
     */
    public function show(Category $category)
    {
        if (! $category->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
            ], 404);
        }

        $category->load('agency');

        return response()->json([
            'success' => true,
            'data' => new CategoryResource($category),
        ]);
    }
}
