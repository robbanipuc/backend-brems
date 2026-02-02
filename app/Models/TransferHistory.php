<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferHistory extends Model
{
    protected $guarded = [];

    protected $casts = [
        'transfer_date' => 'date',
    ];

    // ==================== RELATIONSHIPS ====================

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function fromOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'from_office_id');
    }

    public function toOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'to_office_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ==================== HELPERS ====================

    public function isFirstPosting(): bool
    {
        return $this->from_office_id === null;
    }
}