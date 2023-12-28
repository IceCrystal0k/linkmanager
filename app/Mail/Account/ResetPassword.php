<?php

namespace App\Mail\Account;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPassword extends Mailable
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
        $markdownParams = ['email' => $this->message->email, 'website' => $this->message->website, 'resetLink' => $this->message->resetLink];
        $markdown = 'emails.account.reset-password';

        return $this->subject($this->message->subject)->markdown($markdown, $markdownParams);
    }
}
