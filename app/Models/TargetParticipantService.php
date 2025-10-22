<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TargetParticipantService extends Model
{
    use HasFactory;

    protected $fillable = ['target_participant_id', 'service_id', 'target_quantity', 'sold'];

    public function participant()
    {
        return $this->belongsTo(TargetParticipant::class, 'target_participant_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
