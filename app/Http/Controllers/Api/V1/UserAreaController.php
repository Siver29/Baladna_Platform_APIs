<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AreaStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StoreUserAreaRequest;
use App\Http\Resources\AreaResource;
use App\Models\Area;
use App\Http\Responses\ApiResponse;

class UserAreaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return new ApiResponse(AreaResource::collection(Area::where('status', AreaStatus::APPROVED)->paginate()));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserAreaRequest $request)
    {
        $area = Area::create(array_merge($request->validated(), [
            'status' => AreaStatus::PENDING,
            'user_id' => auth()->id(),
        ]));

        return new ApiResponse(new AreaResource($area), 201);
    }
}
