<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormField extends Model
{
    protected $guarded = [];

    protected $casts = [
        'options' => 'array',
        'required' => 'boolean',
    ];

    // Valid field types
    public const TYPES = [
        'text',
        'number',
        'date',
        'select',
        'file',
        'textarea',
        'checkbox',
        'radio'
    ];

    // ==================== RELATIONSHIPS ====================

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}