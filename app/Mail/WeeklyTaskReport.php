<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;


class WeeklyTaskReport extends Mailable
{
    use Queueable, SerializesModels;

    public $pdfAttachments;

    public function __construct($pdfAttachments)
    {
        $this->pdfAttachments = $pdfAttachments;
    }

    public function build()
    {
        $email = $this->view('emails.task_pdf');

        // Attach all PDFs
        foreach ($this->pdfAttachments as $attachment) {
            $email->attachData($attachment['data'], $attachment['name'], [
                'mime' => 'application/pdf',
            ]);
        }

        return $email->subject('Weekly Tasks Report')
        ->from('Washghana@washghana.com');
    }
}
