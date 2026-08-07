<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\ReportStatus;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'reference_number',
        'user_id',
        'reporter_name',
        'reporter_email',
        'reporter_phone',
        'category_id',
        'area_id',
        'agency_id',
        'assigned_employee_id',
        'title',
        'description',
        'address',
        'latitude',
        'longitude',
        'priority',
        'status',
        'public_note',
        'rejection_reason',
        'resolution_note',
        'resolved_at',
        'cancelled_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'priority' => Priority::class,
            'status' => ReportStatus::class,
            'resolved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function assignedEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_employee_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ReportImage::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ReportStatusHistory::class);
    }

    public function confirmations(): HasMany
    {
        return $this->hasMany(ReportConfirmation::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(ReportReview::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['area_id'] ?? null, fn ($q, $id) => $q->where('area_id', $id))
            ->when($filters['agency_id'] ?? null, fn ($q, $id) => $q->where('agency_id', $id))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('reference_number', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($filters['sort'] ?? null, function ($q, $sort) {
                match ($sort) {
                    'oldest' => $q->orderBy('created_at', 'asc'),
                    'most_confirmed' => $q->withCount('confirmations')->orderBy('confirmations_count', 'desc'),
                    default => $q->orderBy('created_at', 'desc'),
                };
            }, fn ($q) => $q->orderBy('created_at', 'desc'));
    }
}
