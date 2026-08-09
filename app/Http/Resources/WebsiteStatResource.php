<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteStatResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_reports' => $this->total_reports,
            'resolved_reports' => $this->resolved_reports,
            'pending_reports' => $this->pending_reports,
            'anonymous_reports' => $this->anonymous_reports,
            'active_categories' => $this->active_categories,
            'active_areas' => $this->active_areas,
            'active_agencies' => $this->active_agencies,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
