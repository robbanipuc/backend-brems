<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    protected $guarded = [];

    // ==================== RELATIONSHIPS ====================

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // ==================== HELPERS ====================

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->house_no,
            $this->village_road,
            $this->post_office,
            $this->upazila,
            $this->district,
            $this->division
        ]);

        return implode(', ', $parts);
    }

    public function isPresentAddress(): bool
    {
        return $this->type === 'present';
    }

    public function isPermanentAddress(): bool
    {
        return $this->type === 'permanent';
    }
}