<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TargetAssignedNotification extends Notification
{
    use Queueable;

    protected $target;

    public function __construct($target)
    {
        $this->target = $target;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'target_assigned',
            'target_id' => $this->target->id,
            'message' => "تم تعيين هدف لك من المدير {$this->target->branchManager->fname}.",
            'start_date' => $this->target->start_date->toDateString(),
            'end_date' => $this->target->end_date->toDateString(),
        ];
    }
}