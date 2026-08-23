<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A report status history row presented as an in-app notification.
 *
 * @mixin \App\Models\ReportStatusHistory
 */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $readAt = $request->user()?->notifications_read_at;

        return [
            'id' => $this->id,
            'type' => $this->type(),
            'old_status' => $this->old_status,
            'new_status' => $this->new_status,
            'note' => $this->note,
            'report' => $this->whenLoaded('report', fn () => [
                'id' => $this->report->id,
                'reference_number' => $this->report->reference_number,
                'title' => $this->report->title,
                'status' => $this->report->status->value,
            ]),
            // Null when the platform itself produced the event.
            'actor' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null),
            'is_read' => $readAt !== null && $this->created_at !== null && $this->created_at <= $readAt,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Classify the event so the client can pick wording and an icon.
     *
     * Creation leaves no previous status, and the assignment service records a
     * row whose status does not move - that is how the two are told apart from
     * an ordinary workflow transition.
     */
    protected function type(): string
    {
        return match (true) {
            $this->old_status === null => 'report_created',
            $this->old_status === $this->new_status => 'report_assigned',
            default => 'report_status_changed',
        };
    }
}
