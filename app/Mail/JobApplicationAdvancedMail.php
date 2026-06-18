<?php

namespace App\Mail;

use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class JobApplicationAdvancedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public JobApplication $jobApplication,
    ) {
        $this->jobApplication->loadMissing(['jobPosting:id,title']);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Application update: :stage — :title', [
                'stage' => $this->jobApplication->status->label(),
                'title' => $this->jobApplication->jobPosting->title,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.job-application-advanced',
            with: [
                'jobApplication' => $this->jobApplication,
            ],
        );
    }
}
