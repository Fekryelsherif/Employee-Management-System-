<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    use HasFactory;
    protected $fillable = [
        'employee_id', 'branch_manager_id', 'amount', 'reason', 'status'
    ];

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function branch_manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}
