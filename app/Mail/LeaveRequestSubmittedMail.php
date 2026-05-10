<?php

namespace App\Mail;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeaveRequestSubmittedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public LeaveRequest $leaveRequest,
    ) {
        $this->leaveRequest->loadMissing(['user:id,name,email']);
    }

    public function envelope(): Envelope
    {
        $requester = $this->leaveRequest->user;

        return new Envelope(
            subject: __('New leave request from :name', ['name' => $requester?->name ?? __('Unknown')]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.leave-request-submitted',
            with: [
                'leaveRequest' => $this->leaveRequest,
                'manageUrl' => route('admin.leave-requests.manage'),
            ],
        );
    }
}
