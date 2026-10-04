<?php

namespace App\Notifications;

use App\Models\Inventory;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockAlert extends Notification
{
    use Queueable;

    public function __construct(public Inventory $inventory)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $v = $this->inventory->variant;

        return (new MailMessage)
            ->subject("Low stock: {$v->display_name} at {$this->inventory->location->name}")
            ->line("{$v->display_name} ({$v->sku}) is down to {$this->inventory->quantity} at {$this->inventory->location->name}.")
            ->line("Low-stock threshold: {$v->low_stock_threshold}.");
    }

    /** Stored in the format the admin panel's notification bell understands; staff screens read title/body. */
    public function toDatabase(object $notifiable): array
    {
        $v = $this->inventory->variant;

        return [
            ...FilamentNotification::make()
                ->title('Low stock')
                ->body("{$v->display_name} ({$v->sku}) is down to {$this->inventory->quantity} at {$this->inventory->location->name}.")
                ->warning()
                ->getDatabaseMessage(),
            'product_variant_id' => $v->id,
            'location_id' => $this->inventory->location_id,
            'quantity' => $this->inventory->quantity,
            'threshold' => $v->low_stock_threshold,
        ];
    }
}
