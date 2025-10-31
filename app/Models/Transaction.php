<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const CATEGORY_PURCHASE_REQUEST = 'PR';
    public const CATEGORY_PURCHASE_ORDER = 'PO';
    public const CATEGORY_VOUCHER = 'VCH';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'procurement_id',
        'category',
        'reference_number',
        'status',
        'workflow_id',
        'current_office_id',
        'current_user_id',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => 'string',
            'status' => 'string',
        ];
    }

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    public function purchaseRequest(): HasOne
    {
        return $this->hasOne(PurchaseRequest::class);
    }

    public function purchaseOrder(): HasOne
    {
        return $this->hasOne(PurchaseOrder::class);
    }

    public function voucher(): HasOne
    {
        return $this->hasOne(Voucher::class);
    }
}

