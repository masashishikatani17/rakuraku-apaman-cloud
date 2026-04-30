<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryTemplateLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_template_id',
        'line_no',
        'side',
        'account_title_id',
        'sub_account_title_id',
        'department_id',
        'property_id',
        'default_amount',
        'line_note',
    ];

    protected $casts = [
        'journal_entry_template_id' => 'integer',
        'line_no' => 'integer',
        'account_title_id' => 'integer',
        'sub_account_title_id' => 'integer',
        'department_id' => 'integer',
        'property_id' => 'integer',
        'default_amount' => 'decimal:2',
    ];

    public function journalEntryTemplate(): BelongsTo
    {
        return $this->belongsTo(JournalEntryTemplate::class);
    }

    public function accountTitle(): BelongsTo
    {
        return $this->belongsTo(AccountTitle::class);
    }

    public function subAccountTitle(): BelongsTo
    {
        return $this->belongsTo(SubAccountTitle::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}