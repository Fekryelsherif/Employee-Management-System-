<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TargetParticipant extends Model
{
    use HasFactory;

    protected $fillable = ['target_id', 'employee_id', 'status', 'progress', 'target_services', 'achieved_services'];

    public function target()
    {
        return $this->belongsTo(Target::class, 'target_id');
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function services()
{
    return $this->hasMany(TargetParticipantService::class, 'target_participant_id');
}

}
