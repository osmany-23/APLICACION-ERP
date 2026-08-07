<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    protected $table = 'journal_entry_lines';

    protected $fillable = [
        'journal_entry_id',
        'accounting_account_id',
        'cost_center_id',
        'debit',
        'credit',
        'description',
        'line_order',
    ];

    protected $casts = [
        'journal_entry_id' => 'integer',
        'accounting_account_id' => 'integer',
        'cost_center_id' => 'integer',
        'debit' => 'decimal:4',
        'credit' => 'decimal:4',
        'line_order' => 'integer',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(AccountingAccount::class, 'accounting_account_id');
    }
}
