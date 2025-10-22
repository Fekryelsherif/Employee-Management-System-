<?php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SaleMadeNotification extends Notification
{
    use Queueable;

    protected $sale;

    public function __construct($sale)
    {
        $this->sale = $sale;
    }

    public function via($notifiable) { return ['database']; }

    public function toDatabase($notifiable)
    {
        return [
            'type' => 'sale_made',
            'sale_id' => $this->sale->id,
            'message' => "{$this->sale->employee->fname} باع {$this->sale->quantity} x {$this->sale->service->name} لعميل {$this->sale->client->name}",
            'employee_id' => $this->sale->employee_id,
            'service_id' => $this->sale->service_id,
        ];
    }
}
