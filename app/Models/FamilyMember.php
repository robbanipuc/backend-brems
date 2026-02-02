<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyMember extends Model
{
    protected $guarded = [];

    protected $casts = [
        'dob' => 'date',
        'is_alive' => 'boolean',
        'is_active_marriage' => 'boolean',
    ];

    // ==================== RELATIONSHIPS ====================

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // ==================== AUTO GENDER LOGIC ====================

    /**
     * Boot method to auto-set gender based on relation
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($familyMember) {
            $familyMember->autoSetGender();
            $familyMember->setMarriageDefault();
        });

        static::updating(function ($familyMember) {
            $familyMember->autoSetGender();
        });
    }

    /**
     * Auto-set gender based on relation type
     */
    protected function autoSetGender(): void
    {
        switch ($this->relation) {
            case 'father':
                $this->gender = 'male';
                break;
            case 'mother':
                $this->gender = 'female';
                break;
            case 'spouse':
                // Get employee's gender and set opposite
                $employee = $this->employee ?? Employee::find($this->employee_id);
                if ($employee && $employee->gender) {
                    $this->gender = $employee->gender === 'male' ? 'female' : 'male';
                }
                break;
            // 'child' - gender must be specified by user
        }
    }

    /**
     * Set default marriage status for spouses
     */
    protected function setMarriageDefault(): void
    {
        if ($this->relation === 'spouse' && $this->is_active_marriage === null) {
            $this->is_active_marriage = true;
        }
    }

    // ==================== HELPER METHODS ====================

    public function isSpouse(): bool
    {
        return $this->relation === 'spouse';
    }

    public function isChild(): bool
    {
        return $this->relation === 'child';
    }

    public function isParent(): bool
    {
        return in_array($this->relation, ['father', 'mother']);
    }
}