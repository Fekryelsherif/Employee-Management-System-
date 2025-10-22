<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Service extends Model {
    protected $fillable = ['name', 'base_price'];
    public function targets(){ return $this->belongsToMany(Target::class,'target_services')->withPivot('target_quantity','sold')->withTimestamps(); }

    public function commissions()
    {
        return $this->hasMany(ServiceCommission::class);
    }

}