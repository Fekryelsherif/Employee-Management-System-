<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TargetService extends Model
{
    use HasFactory;

    protected $fillable = ['target_id', 'service_id', 'target_quantity', 'sold'];

    public function target()
    {
        return $this->belongsTo(Target::class, 'target_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
