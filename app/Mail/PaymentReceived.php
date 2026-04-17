<?php

namespace App\Mail;

use App\Models\Auction;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Auction $auction) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Pago recibido! Tu obra está confirmada',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.payment-received',
        );
    }
}