<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'address', 'city_id', 'branch_manager_id', 'region_id', 'region_manager_id'];

  // 🔹 مدير الفرع
    public function manager()
    {
        return $this->belongsTo(User::class, 'branch_manager_id')
                    ->where('type', 'branch-manager');
    }

    // 🔹 الموظفين التابعين للفرع
    public function employees()
    {
        return $this->hasMany(User::class, 'branch_id')
                    ->where('type', 'employee');
    }

    // 🔹 التارجتس المرتبطة بالفرع
    public function targets()
    {
        return $this->hasMany(Target::class);
    }

    // 🔹 المدينة
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function region()
{
    return $this->belongsTo(Region::class);
}

public function users()
{
    return $this->hasMany(User::class, 'branch_id');
}


public function regionManager()
{
    return $this->belongsTo(User::class, 'region_manager_id');
}


}
