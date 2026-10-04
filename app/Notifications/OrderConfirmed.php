<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmed extends Notification
{
    use Queueable;

    public function __construct(public Order $order)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $o = $this->order->loadMissing('items', 'marketDay.location');
        $mail = (new MailMessage)
            ->subject("NuttyLand order {$o->order_number} confirmed")
            ->greeting("Thanks {$o->customer->first_name}!")
            ->line("Your Click & Collect order {$o->order_number} is confirmed.");

        if ($o->marketDay) {
            $mail->line('Collect from: '.$o->marketDay->label);
        }
        foreach ($o->items as $item) {
            $mail->line("{$item->quantity} × {$item->product_name} {$item->weight_label} – ".Money::format($item->subtotal_cents));
        }

        return $mail->line('Total paid: '.Money::format($o->total_cents))
            ->action('View your order', route('orders.show', $o));
    }
}
