<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;

class TargetProgressNotification extends Notification
{
    public function __construct(public $target, public $participant) {}
    public function via($notifiable){ return ['database']; }
    public function toDatabase($notifiable){
        return [
            'type'=>'target_progress',
            'target_id'=>$this->target->id,
            'participant_id'=>$this->participant->id,
            'progress' => $this->participant->progress,
            'message'=>"تقدّم جديد في الهدف {$this->target->title}: {$this->participant->progress}%"
        ];
    }
}
