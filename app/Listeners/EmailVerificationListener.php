<?php

namespace App\Listeners;

use App\Events\EmailVerificationEvent;
use App\Traits\EmailTemplateTrait;

class EmailVerificationListener
{
    use EmailTemplateTrait;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(EmailVerificationEvent $event): void
    {
        $this->sendMail($event);
    }

    private function sendMail(EmailVerificationEvent $event): void
    {
        $email = $event->email;
        $data = $event->data;
        $sent = $this->sendingMail(sendMailTo: $email, userType: $data['userType'], templateName: $data['templateName'], data: $data, sendSync: true);

        if (! $sent) {
            throw new \RuntimeException(translate('Token_sent_failed'));
        }
    }
}
