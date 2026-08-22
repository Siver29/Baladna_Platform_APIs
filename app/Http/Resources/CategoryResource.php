<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            'description' => $this->description,
            'agency_id' => $this->agency_id,
            'agency' => $this->whenLoaded('agency', fn () => [
                'id' => $this->agency->id,
                'name' => $this->agency->name,
            ]),
            'responsible_employee_id' => $this->responsible_employee_id,
            'responsible_employee' => $this->whenLoaded('responsibleEmployee', fn () => $this->responsibleEmployee ? [
                'id' => $this->responsibleEmployee->id,
                'name' => $this->responsibleEmployee->name,
            ] : null),
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
