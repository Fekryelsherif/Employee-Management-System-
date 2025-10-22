<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewNoteNotification extends Notification
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
            'title' => 'New Note Submitted',
            'message' => $this->note->employee->fname . ' sent a note: ' . $this->note->title,
            'note_id' => $this->note->id,
        ];
    }
}