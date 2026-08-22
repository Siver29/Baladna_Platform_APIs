<?php

namespace App\Http\Resources;

use App\Enums\AreaStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AreaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'parent_id' => $this->parent_id,
            'status' => $this->status?->value,
            'is_approved' => $this->status === AreaStatus::APPROVED,
            'is_pending' => $this->status === AreaStatus::PENDING,
            'is_rejected' => $this->status === AreaStatus::REJECTED,
            // Null for areas created directly by an administrator.
            'suggested_by_id' => $this->user_id,
            'suggested_by' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null),
            'parent' => $this->whenLoaded('parent', fn () => $this->parent ? [
                'id' => $this->parent->id,
                'name' => $this->parent->name,
            ] : null),
            'children' => $this->whenLoaded('children', fn () => AreaResource::collection($this->children)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
