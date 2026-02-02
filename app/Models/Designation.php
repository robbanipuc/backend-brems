<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Designation extends Model
{
    protected $fillable = [
        'title',
        'title_bn',
        'grade',
        'basic_salary'
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2'
    ];

    // ==================== RELATIONSHIPS ====================

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function promotionHistories(): HasMany
    {
        return $this->hasMany(PromotionHistory::class, 'new_designation_id');
    }
}