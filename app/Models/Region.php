<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
use HasFactory;

protected $fillable = [  'name' ,'region_manager_id'];

public function manager()
{
return $this->belongsTo(User::class, 'region_manager_id')->where('type', 'region-manager');
}

public function branches()
{
return $this->hasMany(Branch::class);
}
}
