<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;

class TargetCompletedNotification extends Notification
{
    public function __construct(public $target, public $participant) {}
    public function via($notifiable){ return ['database']; }
    public function toDatabase($notifiable){
        return [
            'type'=>'target_completed',
            'target_id'=>$this->target->id,
            'participant_id'=>$this->participant->id,
            'message'=>"تهانينا! الهدف {$this->target->title} اكتمل بواسطة {$this->participant->employee->fname} {$this->participant->employee->lname}."
        ];
    }
}
