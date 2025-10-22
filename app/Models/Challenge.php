<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Challenge extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'branch_manager_id',
        'title',
        'description',
        'reward',
        'start_date',
        'end_date',
        'status',
    ];

    public function branch() {
        return $this->belongsTo(Branch::class);
    }

    public function manager() {
        return $this->belongsTo(User::class, 'branch_manager_id');
    }

    public function services() {
        return $this->belongsToMany(Service::class, 'challenge_services')
                    ->withPivot('target_quantity')
                    ->withTimestamps();
    }

    public function participants() {
        return $this->hasMany(ChallengeParticipant::class, 'challenge_id');
    }
}