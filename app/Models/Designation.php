<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Designation extends Model
{
    protected $fillable = [
        'office_id',
        'title',
        'title_bn',
        'grade',
        'salary_min',
        'salary_max',
        'method_of_recruitment',
        'qualifications',
    ];

    protected $casts = [
        'salary_min' => 'decimal:2',
        'salary_max' => 'decimal:2',
    ];

    // ==================== ACCESSORS ====================

    /**
     * Get formatted salary range
     */
    public function getSalaryRangeAttribute(): string
    {
        if ($this->salary_min == $this->salary_max) {
            return number_format($this->salary_min, 0);
        }
        return number_format($this->salary_min, 0) . ' - ' . number_format($this->salary_max, 0);
    }

    // ==================== RELATIONSHIPS ====================

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function promotionHistories(): HasMany
    {
        return $this->hasMany(PromotionHistory::class, 'new_designation_id');
    }
}