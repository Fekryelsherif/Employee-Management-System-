<?php
// app/Notifications/ChallengeCompletedNotification.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ChallengeCompletedNotification extends Notification
{
    use Queueable;
    public $challenge;
    public $employee;

    public function __construct($challenge, $employee) {
        $this->challenge = $challenge;
        $this->employee = $employee;
    }

    public function via($notifiable) { return ['database']; }

    public function toArray($notifiable) {
        return [
            'type'=>'challenge_completed',
            'challenge_id'=>$this->challenge->id,
            'employee_id'=>$this->employee->id,
            'message'=>"{$this->employee->fname} أكمل التحدي {$this->challenge->title} وكسب المكافأة"
        ];
    }
}
