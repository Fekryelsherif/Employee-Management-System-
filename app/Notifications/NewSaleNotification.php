<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewSaleNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected $sale;
   public function __construct($sale)
{
    $this->sale = $sale;
}

public function via($notifiable)
{
    return ['database']; // أو mail + database
}

public function toArray($notifiable)
{
    return [
        'title' => 'عملية بيع جديدة',
        'body' => 'تم تسجيل عملية بيع جديدة من قبل الموظف ' . $this->sale->employee->fname,
        'sale_id' => $this->sale->id,
        'amount' => $this->sale->total_price,
    ];
}

}