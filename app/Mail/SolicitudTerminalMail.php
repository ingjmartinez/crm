<?php

namespace App\Mail;

use App\Models\SolicitudTerminal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SolicitudTerminalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SolicitudTerminal $solicitud,
        private readonly string $pdf
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Solicitud de terminales {$this->solicitud->numero} - Grupo Joselito",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.solicitud-terminal',
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
            Attachment::fromData(
                fn (): string => $this->pdf,
                strtolower($this->solicitud->numero).'-solicitud-terminales.pdf'
            )->withMime('application/pdf'),
        ];
    }
}
