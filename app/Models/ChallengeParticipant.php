<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChallengeParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'challenge_id',
        'employee_id',
        'progress',
        'status',
    ];

    public function challenge()
    {
        return $this->belongsTo(Challenge::class);
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    // ✅ العلاقة اللي كانت ناقصة
    public function serviceProgress()
    {
        return $this->hasMany(ChallengeParticipantService::class, 'challenge_participant_id');
    }

    public function services()
    {
        return $this->hasMany(ChallengeParticipantService::class, 'challenge_participant_id');
    }
}