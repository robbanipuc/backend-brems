<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademicRecord extends Model
{
    protected $guarded = [];

    // Valid exam names (must match ENUM in migration)
    public const EXAM_NAMES = [
        'SSC / Dakhil',
        'HSC / Alim',
        'Bachelor (Honors)',
        'Masters',
        'Diploma'
    ];

    // ==================== RELATIONSHIPS ====================

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // ==================== VALIDATION HELPER ====================

    public static function isValidExamName(string $name): bool
    {
        return in_array($name, self::EXAM_NAMES);
    }
}