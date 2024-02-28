<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailerService
{
    public function __construct(
        private readonly MailerInterface $mailer
    ) {
    }

    public function sendBrevoEmail(string $to, int $templateId, array $params): void
    {
        $email = (new Email())
            ->from('contact@imeet.fr')
            ->to($to)
            ->text('Welcome!')
        ;

        $email
            ->getHeaders()
            ->addTextHeader('templateId', $templateId)
            ->addParameterizedHeader('params', 'params', $params)
        ;

        $this->mailer->send($email);
    }
}
