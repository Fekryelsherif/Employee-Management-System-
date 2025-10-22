<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'location',
        'notes',
        'city_id',
        'national_id',
        'created_by',

    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'client_service', 'client_id', 'service_id');
    }
}