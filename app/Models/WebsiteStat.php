<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteStat extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'total_reports',
        'resolved_reports',
        'pending_reports',
        'anonymous_reports',
        'active_categories',
        'active_areas',
        'active_agencies',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_reports' => 'integer',
            'resolved_reports' => 'integer',
            'pending_reports' => 'integer',
            'anonymous_reports' => 'integer',
            'active_categories' => 'integer',
            'active_areas' => 'integer',
            'active_agencies' => 'integer',
        ];
    }
}
