<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCategoryController extends Controller
{
    use ApiResponse;

    /**
     * List all categories (including inactive).
     */
    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->with('agency', 'responsibleEmployee')
            ->when($request->has('agency_id'), fn ($q) => $q->where('agency_id', $request->agency_id))
            ->when($request->has('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return $this->successCollection(
            CategoryResource::collection($categories),
            200,
            [
                'current_page' => $categories->currentPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
                'last_page' => $categories->lastPage(),
            ]
        );
    }

    /**
     * Create a new category.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return $this->success(new CategoryResource($category->load('agency', 'responsibleEmployee')), 'Category created successfully.', 201);
    }

    /**
     * Show a single category.
     */
    public function show(Category $category): JsonResponse
    {
        $category->load('agency', 'responsibleEmployee');

        return $this->success(new CategoryResource($category));
    }

    /**
     * Update a category.
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category->update($request->validated());

        return $this->success(new CategoryResource($category->load('agency', 'responsibleEmployee')), 'Category updated successfully.');
    }

    /**
     * Delete a category, or refuse if it has reports.
     */
    public function destroy(Category $category): JsonResponse
    {
        if ($category->reports()->exists()) {
            return $this->error('Cannot delete a category that has reports. Deactivate it instead.', 422);
        }

        $category->delete();

        return $this->success(null, 'Category deleted successfully.', 204);
    }
}
