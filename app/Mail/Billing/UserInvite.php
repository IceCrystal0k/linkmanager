<?php

namespace App\Mail\Billing;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserInvite extends Mailable
{
    use Queueable, SerializesModels;
    public $message;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $markdownParams = ['name' => $this->message->name, 'company' => $this->message->company, 'acceptLink' => $this->message->acceptLink];
        $markdown = 'emails.billing.user-invite';

        return $this->subject($this->message->subject)->markdown($markdown, $markdownParams);
    }
}