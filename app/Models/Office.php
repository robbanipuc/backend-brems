<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Office extends Model
{
    // ==================== ZONE CONSTANTS ====================
    
    public const ZONE_CENTER = 'center';
    public const ZONE_EAST = 'east';
    public const ZONE_WEST = 'west';

    public const ZONES = [
        self::ZONE_CENTER => 'Center (Headquarters)',
        self::ZONE_EAST => 'East Zone',
        self::ZONE_WEST => 'West Zone, Rajshahi',
    ];

    // ==================== FILLABLE ====================

    protected $fillable = [
        'parent_id',
        'name',
        'zone',
        'code',
        'location',
    ];

    // ==================== ACCESSORS ====================

    /**
     * Get zone display name
     */
    public function getZoneLabelAttribute(): ?string
    {
        return self::ZONES[$this->zone] ?? $this->zone;
    }

    /**
     * Get all available zones (for dropdowns)
     */
    public static function getZones(): array
    {
        return self::ZONES;
    }

    /**
     * Get zones as array for API response
     */
    public static function getZonesForApi(): array
    {
        return collect(self::ZONES)->map(function ($label, $value) {
            return [
                'value' => $value,
                'label' => $label,
            ];
        })->values()->toArray();
    }

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

    public function designations(): HasMany
    {
        return $this->hasMany(Designation::class);
    }

    public function designationPosts(): HasMany
    {
        return $this->hasMany(OfficeDesignationPost::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // ==================== SCOPES ====================

    /**
     * Filter by zone
     */
    public function scopeInZone($query, string $zone)
    {
        return $query->where('zone', $zone);
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