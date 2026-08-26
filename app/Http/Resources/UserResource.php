<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'is_active' => $this->is_active,
            'area_id' => $this->area_id,
            'agency_id' => $this->agency_id,
            'area' => $this->whenLoaded('area', fn () => $this->area ? [
                'id' => $this->area->id,
                'name' => $this->area->name,
                'parent_id' => $this->area->parent_id,
                'parent' => $this->area->relationLoaded('parent') && $this->area->parent ? [
                    'id' => $this->area->parent->id,
                    'name' => $this->area->parent->name,
                ] : null,
            ] : null),
            'agency' => $this->whenLoaded('agency', fn () => $this->agency ? [
                'id' => $this->agency->id,
                'name' => $this->agency->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
