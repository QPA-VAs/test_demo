<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use \App\Models\Client;

class ClientTasksReport extends Mailable
{
    use Queueable, SerializesModels;

    public $client;
    public $pdfContent;
    public $fileName;
    public $formattedTime;

    public function __construct($client, $pdfContent, $fileName, $formattedTime)
    {
        $this->client = $client;
        $this->pdfContent = $pdfContent;
        $this->fileName = $fileName;
        $this->formattedTime = $formattedTime;
    }

    public function build()
    {
//        return $this->view('emails.task_pdf')
        return $this->view('emails.weekly_tasks_report')
            ->from('Washghana@washghana.com')
            ->attachData($this->pdfContent, $this->fileName, [
                'mime' => 'application/pdf',
            ])
            ->subject('Your Weekly Tasks Report');
    }
}
