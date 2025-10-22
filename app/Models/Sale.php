<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'branch_id',
        'client_id',
        'source_type', // 'target' or 'challenge'
        'source_id',
        'total_amount',
        'total_commission',
    ];

   public function getTotalAmountAttribute($value)
    {
        return $value ?? 0;
    }

    public function getTotalCommissionAttribute($value)
    {
        return $value ?? 0;
    }

    // ✅ العلاقات

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id' , 'id');
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }


   public function target()
{
    return $this->belongsTo(Target::class, 'source_id')
                ->where('source_type', 'target');
}

public function user()
{
    return $this->belongsTo(User::class, 'user_id');
}






}
