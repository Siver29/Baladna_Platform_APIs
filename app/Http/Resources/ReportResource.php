<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
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
            'reference_number' => $this->reference_number,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'public_note' => $this->public_note,
            'rejection_reason' => $this->rejection_reason,
            'resolution_note' => $this->resolution_note,
            'resolved_at' => $this->resolved_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'area' => $this->whenLoaded('area', fn () => [
                'id' => $this->area->id,
                'name' => $this->area->name,
            ]),
            'agency' => $this->whenLoaded('agency', fn () => [
                'id' => $this->agency->id,
                'name' => $this->agency->name,
            ]),
            'reporter' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'assigned_employee' => $this->whenLoaded('assignedEmployee', fn () => $this->assignedEmployee ? [
                'id' => $this->assignedEmployee->id,
                'name' => $this->assignedEmployee->name,
            ] : null),
            'images' => ReportImageResource::collection($this->whenLoaded('images')),
            'confirmations_count' => $this->whenCounted('confirmations'),
            'confirmed_by_me' => $this->when(isset($this->confirmed_by_me), $this->confirmed_by_me),
            'review' => $this->whenLoaded('review', fn () => $this->review ? new ReportReviewResource($this->review) : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
