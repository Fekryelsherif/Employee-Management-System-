<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChallengeParticipantService extends Model
{
    use HasFactory;

    protected $fillable = [
        'challenge_participant_id',
        'service_id',
        'target_quantity',
        'sold',
    ];

    public function participant() {
        return $this->belongsTo(ChallengeParticipant::class, 'challenge_participant_id');
    }

    public function service() {
        return $this->belongsTo(Service::class);
    }
}
