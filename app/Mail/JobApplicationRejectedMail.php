<?php

namespace App\Mail;

use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class JobApplicationRejectedMail extends Mailable implements ShouldQueue
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
            subject: __('Update on your application for :title', [
                'title' => $this->jobApplication->jobPosting->title,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.job-application-rejected',
            with: [
                'jobApplication' => $this->jobApplication,
            ],
        );
    }
}
