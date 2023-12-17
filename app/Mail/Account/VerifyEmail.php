<?php

namespace App\Mail\Account;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerifyEmail extends Mailable
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
        $markdownParams = ['name' => $this->message->name, 'website' => $this->message->website, 'verifyLink' => $this->message->verifyLink];
        $markdown = 'emails.account.verify-email';

        return $this->subject($this->message->subject)->markdown($markdown, $markdownParams);
    }
}
