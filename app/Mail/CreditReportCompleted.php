<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CreditReportCompleted extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Message body or extra details.
     *
     * @var string|null
     */
    public array $summary;

    /**
     * Create a new message instance.
     */
    public function __construct(array $summary)
    {
        $this->summary = $summary;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $bureau = $this->summary['bureau'] ?? 'Credit Bureau';
        return new Envelope(
            subject: "{$bureau} - Credit Report Batch Completed Successfully",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.credit_report_completed',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments(): array
    {
        return [];
    }
}
