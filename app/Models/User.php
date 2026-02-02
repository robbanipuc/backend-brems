<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'office_id',
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    // ==================== RELATIONSHIPS ====================

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    // ==================== ROLE CHECKS ====================

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isOfficeAdmin(): bool
    {
        return $this->role === 'office_admin';
    }

    public function isVerifiedUser(): bool
    {
        return $this->role === 'verified_user';
    }

    // ==================== PERMISSION HELPERS ====================

    /**
     * Get all office IDs this user can manage
     */
    public function getManagedOfficeIds(): array
    {
        if ($this->isSuperAdmin()) {
            return Office::pluck('id')->toArray();
        }

        if ($this->isOfficeAdmin()) {
            return $this->office->getManagedOfficeIds();
        }

        return [];
    }

    /**
     * Check if user can manage a specific office
     */
    public function canManageOffice(int $officeId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($officeId, $this->getManagedOfficeIds());
    }

    /**
     * Check if user can manage a specific employee
     */
    public function canManageEmployee(Employee $employee): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->isOfficeAdmin()) {
            return $this->canManageOffice($employee->current_office_id);
        }

        // Verified users can only view their own
        return $this->employee_id === $employee->id;
    }

    /**
     * Check if user can view a specific employee
     */
    public function canViewEmployee(Employee $employee): bool
    {
        if ($this->isSuperAdmin() || $this->isOfficeAdmin()) {
            return $this->canManageEmployee($employee);
        }

        // Verified user can view only self
        return $this->employee_id === $employee->id;
    }

    /**
     * Get the office ID that should review this user's profile requests
     * (Parent office admin reviews office admin requests)
     */
    public function getReviewerOfficeId(): ?int
    {
        if ($this->isOfficeAdmin() && $this->office?->parent_id) {
            return $this->office->parent_id;
        }

        return null;
    }
}