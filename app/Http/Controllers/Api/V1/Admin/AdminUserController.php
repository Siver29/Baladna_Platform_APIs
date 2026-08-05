<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    use ApiResponse;

    /**
     * List users with optional role/agency filters.
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->with('area', 'agency')
            ->when($request->has('role'), fn ($q) => $q->where('role', $request->role))
            ->when($request->has('agency_id'), fn ($q) => $q->where('agency_id', $request->agency_id))
            ->when($request->has('search'), fn ($q) => $q->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%")))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return $this->successCollection(
            UserResource::collection($users),
            200,
            [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ]
        );
    }

    /**
     * Create a new user (any role).
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['role'] = Role::from($data['role']);

        // Employees must belong to an agency; citizens/admins must not have one.
        if ($data['role'] !== Role::Employee) {
            $data['agency_id'] = null;
        }

        if ($data['role'] !== Role::Citizen) {
            $data['area_id'] = null;
        }

        $user = User::create($data);

        return $this->success(new UserResource($user->load('area', 'agency')), 'User created successfully.', 201);
    }

    /**
     * Show a single user.
     */
    public function show(User $user): JsonResponse
    {
        $user->load('area', 'agency');

        return $this->success(new UserResource($user));
    }

    /**
     * Update a user.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('role', $data)) {
            $role = Role::from($data['role']);
            $data['role'] = $role;

            if ($role !== Role::Employee) {
                $data['agency_id'] = null;
            }

            if ($role !== Role::Citizen) {
                $data['area_id'] = null;
            }
        }

        $user->update($data);

        return $this->success(new UserResource($user->load('area', 'agency')), 'User updated successfully.');
    }

    /**
     * Delete a user, or refuse if they own reports.
     */
    public function destroy(User $user): JsonResponse
    {
        if ($user->reports()->exists()) {
            return $this->error('Cannot delete a user who owns reports. Deactivate them instead.', 422);
        }

        $user->delete();

        return $this->success(null, 'User deleted successfully.', 204);
    }
}
