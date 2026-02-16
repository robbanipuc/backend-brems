<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Punishment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'order_date' => 'date',
    ];

    public const TYPES = [
        'dismissal' => 'Dismissal (চাকরি থেকে বরখাস্তকরণ)',
        'removal' => 'Removal (অপসারণ)',
        'reduction_to_lower_grade' => 'Reduction to a lower grade (পদাবনতি)',
        'compulsory_retirement' => 'Compulsory Retirement (বাধ্যতামূলক অবসর)',
        'censure' => 'Censure (তিরস্কার)',
        'withholding_increment' => 'Withholding of increment (বেতনবৃদ্ধি স্থগিতকরণ)',
        'withholding_promotion' => 'Withholding of promotion (পদোন্নতি স্থগিতকরণ)',
        'recovery_from_pay' => 'Recovery from pay (আর্থিক ক্ষতিপূরণ)',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->punishment_type] ?? $this->punishment_type;
    }
}
