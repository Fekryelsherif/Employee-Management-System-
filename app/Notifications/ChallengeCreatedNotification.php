<?php
// app/Notifications/ChallengeCreatedNotification.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ChallengeCreatedNotification extends Notification
{
    use Queueable;
    public $challenge;

    public function __construct($challenge) {
        $this->challenge = $challenge;
    }

    public function via($notifiable) {
        return ['database'];
    }

    public function toArray($notifiable) {
        return [
            'type'=>'challenge_created',
            'challenge_id'=>$this->challenge->id,
            'message'=>"تم إرسال تحدي جديد: {$this->challenge->title}"
        ];
    }
}