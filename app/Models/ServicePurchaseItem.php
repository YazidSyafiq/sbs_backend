<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePurchaseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_purchase_id',
        'service_id',
        'technician_id',
        'cost_price',
        'selling_price',
    ];

    public function servicePurchase(): BelongsTo
    {
        return $this->belongsTo(ServicePurchase::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }
}
