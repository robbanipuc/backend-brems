<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Office extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'code',
        'location'
    ];

    // ==================== RELATIONSHIPS ====================

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Office::class, 'parent_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'current_office_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Get all descendant office IDs (recursive)
     */
    public function getAllChildIds(): array
    {
        $ids = [];
        
        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getAllChildIds());
        }
        
        return $ids;
    }

    /**
     * Check if this office has an active admin
     */
    public function hasAdmin(): bool
    {
        return $this->users()
            ->where('role', 'office_admin')
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get all child offices without their own admin
     */
    public function getAdminlessChildOffices()
    {
        return $this->children->filter(function ($child) {
            return !$child->hasAdmin();
        });
    }

    /**
     * Get IDs of offices this admin can manage (own + adminless children)
     */
    public function getManagedOfficeIds(): array
    {
        $ids = [$this->id];
        
        foreach ($this->children as $child) {
            if (!$child->hasAdmin()) {
                $ids[] = $child->id;
                // Recursively add adminless descendants
                $ids = array_merge($ids, $this->getAdminlessDescendantIds($child));
            }
        }
        
        return $ids;
    }

    /**
     * Recursively get adminless descendant IDs
     */
    private function getAdminlessDescendantIds(Office $office): array
    {
        $ids = [];
        
        foreach ($office->children as $child) {
            if (!$child->hasAdmin()) {
                $ids[] = $child->id;
                $ids = array_merge($ids, $this->getAdminlessDescendantIds($child));
            }
        }
        
        return $ids;
    }
}