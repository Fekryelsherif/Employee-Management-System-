<?php
// app/Notifications/ChallengeRespondedNotification.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ChallengeRespondedNotification extends Notification
{
    use Queueable;
    public $challenge;
    public $employee;
    public $status;

    public function __construct($challenge, $employee, $status) {
        $this->challenge = $challenge;
        $this->employee = $employee;
        $this->status = $status;
    }

    public function via($notifiable) { return ['database']; }

    public function toArray($notifiable) {
        return [
            'type' => 'challenge_responded',
            'challenge_id' => $this->challenge->id,
            'employee_id' => $this->employee->id,
            'status' => $this->status,
            'message' => "{$this->employee->fname} {$this->employee->lname} {$this->status} التحدي {$this->challenge->title}"
        ];
    }
}