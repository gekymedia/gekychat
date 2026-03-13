<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin-side expense records for GekyChat (real money).
 * external_transaction_id links to Priority Bank for 2-way reconciliation.
 */
class Expense extends Model
{
    protected $fillable = [
        'category',
        'vendor',
        'description',
        'amount',
        'spent_at',
        'reference',
        'external_transaction_id',
    ];

    protected $casts = [
        'spent_at' => 'date',
        'amount' => 'decimal:2',
    ];
}
