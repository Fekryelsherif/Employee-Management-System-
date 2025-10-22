<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NoteReviewedNotification extends Notification
{
     use Queueable;

    protected $note;

    public function __construct($note)
    {
        $this->note = $note;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Note Reviewed',
            'message' => 'Your note "' . $this->note->title . '" has been reviewed by the manager.',
            'note_id' => $this->note->id,
        ];
    }
}