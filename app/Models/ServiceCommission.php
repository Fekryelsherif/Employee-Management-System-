<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceCommission extends Model
{
    use HasFactory;
    protected $fillable = ['branch_manager_id', 'service_id', 'commission_rate', 'employee_id'];

    public function branchManager()
    {
        return $this->belongsTo(User::class, 'branch_manager_id');
    }
    public function service()
   {
    return $this->belongsTo(Service::class, 'service_id');
  }
}
