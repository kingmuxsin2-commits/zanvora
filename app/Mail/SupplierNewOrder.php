<?php

namespace App\Mail;

use App\Models\Fulfillment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupplierNewOrder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Fulfillment $fulfillment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New Order #{$this->fulfillment->order->order_number} Ready to Fulfill",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.supplier-new-order',
            with: [
                'fulfillment' => $this->fulfillment,
                'order' => $this->fulfillment->order,
                'supplier' => $this->fulfillment->supplier,
                'items' => $this->fulfillment->order->items()
                    ->where('supplier_id', $this->fulfillment->supplier_id)
                    ->with('product')
                    ->get(),
                'frontendUrl' => config('app.frontend_url'),
            ]
        );
    }
}