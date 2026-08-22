<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportReviewResource extends JsonResource
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
            'rating' => $this->rating,
            'comment' => $this->comment,
            'report' => $this->whenLoaded('report', fn () => $this->report ? [
                'id' => $this->report->id,
                'reference_number' => $this->report->reference_number,
                'title' => $this->report->title,
            ] : null),
            'reviewer' => [
                'id' => $this->user_id,
                'name' => $this->whenLoaded('user', fn () => $this->user?->name),
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
