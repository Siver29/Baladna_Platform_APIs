<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AreaStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AreaResource;
use App\Models\Area;
use App\Http\Responses\ApiResponse;

class AdminAreaSuggestionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return new ApiResponse(AreaResource::collection(Area::where('status', AreaStatus::PENDING)->paginate()));
    }

    /**
     * Approve a newly created resource in storage.
     */
    public function approve(Area $area)
    {
        $area->update(['status' => AreaStatus::APPROVED]);

        return new ApiResponse(new AreaResource($area));
    }

    /**
     * Reject a newly created resource in storage.
     */
    public function reject(Area $area)
    {
        $area->update(['status' => AreaStatus::REJECTED]);

        return new ApiResponse(new AreaResource($area));
    }
}
