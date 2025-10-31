<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrder extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'transaction_id',
        'supplier_id',
        'supplier_address',
        'purchase_request_id',
        'particulars',
        'fund_type_id',
        'total_cost',
        'date_of_po',
        'delivery_date',
        'delivery_term',
        'payment_term',
        'amount_in_words',
        'mode_of_procurement',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_po' => 'date',
            'delivery_date' => 'date',
            'total_cost' => 'decimal:2',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}

