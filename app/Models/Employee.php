<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_verified' => 'boolean',
        'dob' => 'date',
        'joining_date' => 'date',
        'released_at' => 'date',
    ];

    // ==================== CORE RELATIONSHIPS ====================

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'current_office_id');
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    // ==================== PIMS RELATIONSHIPS ====================

    public function family(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function academics(): HasMany
    {
        return $this->hasMany(AcademicRecord::class);
    }

    // ==================== HISTORY RELATIONSHIPS ====================

    public function transfers(): HasMany
    {
        return $this->hasMany(TransferHistory::class)->orderBy('transfer_date', 'desc');
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(PromotionHistory::class)->orderBy('promotion_date', 'desc');
    }

    public function profileRequests(): HasMany
    {
        return $this->hasMany(ProfileRequest::class);
    }

    // ==================== FAMILY HELPER RELATIONS ====================

    public function father(): HasOne
    {
        return $this->hasOne(FamilyMember::class)->where('relation', 'father');
    }

    public function mother(): HasOne
    {
        return $this->hasOne(FamilyMember::class)->where('relation', 'mother');
    }

    public function spouses(): HasMany
    {
        return $this->hasMany(FamilyMember::class)->where('relation', 'spouse');
    }

    public function activeSpouses(): HasMany
    {
        return $this->hasMany(FamilyMember::class)
            ->where('relation', 'spouse')
            ->where('is_active_marriage', true);
    }

    public function children(): HasMany
    {
        return $this->hasMany(FamilyMember::class)->where('relation', 'child');
    }

    // ==================== ADDRESS HELPERS ====================

    public function presentAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('type', 'present');
    }

    public function permanentAddress(): HasOne
    {
        return $this->hasOne(Address::class)->where('type', 'permanent');
    }

    // ==================== COMPUTED ATTRIBUTES ====================

    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getCurrentSalaryAttribute()
    {
        return $this->designation ? (float) ($this->designation->salary_min ?? 0) : 0;
    }

    // ==================== HELPER METHODS ====================

    /**
     * Check if employee can add more spouses
     */
    public function canAddSpouse(): bool
    {
        $activeCount = $this->activeSpouses()->count();
        
        if ($this->gender === 'male') {
            return $activeCount < 4;
        }
        
        return $activeCount < 1; // Female can have only 1
    }

    /**
     * Get max allowed spouses based on gender
     */
    public function getMaxSpouses(): int
    {
        return $this->gender === 'male' ? 4 : 1;
    }

    /**
     * Get expected spouse gender based on employee gender
     */
    public function getExpectedSpouseGender(): string
    {
        return $this->gender === 'male' ? 'female' : 'male';
    }

    /**
     * Check if employee is released and available for transfer
     */
    public function isReleasedForTransfer(): bool
    {
        return $this->status === 'released' && $this->released_at !== null;
    }
}