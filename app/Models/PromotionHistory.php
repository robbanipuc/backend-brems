<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionHistory extends Model
{
    protected $guarded = [];

    protected $casts = [
        'promotion_date' => 'date',
    ];

    // ==================== RELATIONSHIPS ====================

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function newDesignation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'new_designation_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ==================== HELPERS ====================

    /**
     * Get the salary associated with this promotion
     */
    public function getNewSalaryAttribute()
    {
        return $this->newDesignation ? (float) ($this->newDesignation->salary_min ?? 0) : 0;
    }
}