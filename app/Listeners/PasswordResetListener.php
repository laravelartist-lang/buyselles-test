<?php

namespace App\Listeners;

use App\Events\PasswordResetEvent;
use App\Traits\EmailTemplateTrait;

class PasswordResetListener
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
    public function handle(PasswordResetEvent $event): void
    {
        $this->sendMail($event);
    }

    private function sendMail(PasswordResetEvent $event): void
    {
        $email = $event->email;
        $data = $event->data;
        $sent = $this->sendingMail(sendMailTo: $email, userType: $data['userType'], templateName: $data['templateName'], data: $data, sendSync: true);

        if (! $sent) {
            throw new \RuntimeException(translate('Unable_to_send_the_verification_code.'));
        }
    }
}
