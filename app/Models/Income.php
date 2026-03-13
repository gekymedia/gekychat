<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin-side income records for GekyChat (real money).
 * external_transaction_id links to Priority Bank for 2-way reconciliation.
 */
class Income extends Model
{
    protected $fillable = [
        'category',
        'amount',
        'date',
        'description',
        'reference',
        'external_transaction_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];
}
