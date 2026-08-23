<?php

namespace App\Models;

use Database\Factories\ReportStatusHistoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportStatusHistory extends Model
{
    /** @use HasFactory<ReportStatusHistoryFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'report_id',
        'user_id',
        'old_status',
        'new_status',
        'note',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Limit the history to the events a user should be notified about.
     *
     * A user hears about a report they filed or a report assigned to them,
     * except for the events they triggered themselves.
     */
    public function scopeForRecipient(Builder $query, User $user): Builder
    {
        return $query
            ->whereHas('report', fn (Builder $report) => $report->where(
                fn (Builder $owned) => $owned
                    ->where('user_id', $user->id)
                    ->orWhere('assigned_employee_id', $user->id)
            ))
            ->where(fn (Builder $actor) => $actor
                ->whereNull('report_status_histories.user_id')
                ->orWhere('report_status_histories.user_id', '!=', $user->id));
    }

    /**
     * Limit the history to events the user has not seen yet.
     */
    public function scopeUnreadFor(Builder $query, User $user): Builder
    {
        return $query->when(
            $user->notifications_read_at,
            fn (Builder $q, $readAt) => $q->where('report_status_histories.created_at', '>', $readAt)
        );
    }
}
