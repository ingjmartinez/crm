<?php

namespace App\Mail;

use App\Models\VolantePagoSocioDetalle;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VolantePagoSocioMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public VolantePagoSocioDetalle $detalle,
        private readonly string $pdf
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Volante de pago - {$this->detalle->nombre}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.volante-pago-socio',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdf, 'volante_pago_'.$this->detalle->id.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
