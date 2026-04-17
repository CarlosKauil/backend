<?php

namespace App\Notifications;

use App\Models\Auction;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class SubastaGanadaNotification extends Notification
{
    public function __construct(public Auction $auction) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('¡Ganaste la subasta!')
            ->greeting("¡Hola {$notifiable->name}!")
            ->line("Ganaste la subasta: {$this->auction->obra->titulo}")
            ->line("Tu puja ganadora: $" . number_format($this->auction->monto_ganador, 2))
            ->action('Pagar ahora', url("/subastas/{$this->auction->id}/pagar"))
            ->line("Tienes hasta: {$this->auction->payment_deadline->format('d/m/Y H:i')} para completar el pago.");
    }

    public function toArray($notifiable): array
    {
        return [
            'auction_id'       => $this->auction->id,
            'titulo'           => $this->auction->obra->titulo,
            'monto_ganador'    => $this->auction->monto_ganador,
            'payment_deadline' => $this->auction->payment_deadline,
            'tipo'             => 'subasta_ganada',
        ];
    }
}