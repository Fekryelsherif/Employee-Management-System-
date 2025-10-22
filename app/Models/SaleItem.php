<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model {
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'service_id',
        'quantity',
        'price',
        'total',
        'commission_value',
        'commission_rate',
        'total_commission',
        'total_amount',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
