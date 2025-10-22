<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Target extends Model
{
    use HasFactory;

    protected $fillable = [
       'branch_id',
       'region_manager_id',
        'title',
        'start_date',
        'end_date',
        'notes',
        'status',
        'employee_id',
        'recreated_from_target_id',
        'recreated_goal_amount',
        'target_services',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // 🔹 العلاقة مع الخدمات (Pivot Table: target_services)
    public function services()
    {
        return $this->hasMany(TargetService::class, 'target_id');
    }

    // 🔹 المشاركين في التارجت
    public function participants()
    {
        return $this->hasMany(TargetParticipant::class, 'target_id');
    }

    public function recreatedFrom()
{
    return $this->belongsTo(Target::class, 'recreated_from_target_id');
}

public function recreatedTargets()
{
    return $this->hasMany(Target::class, 'recreated_from_target_id');
}

    public function regionManager()
    {
        return $this->belongsTo(User::class, 'region_manager_id');
    }

    public function sales() {
    return $this->hasMany(Sale::class, 'source_id')->where('source_type', 'target');
  }



}
