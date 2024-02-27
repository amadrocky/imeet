<?php

namespace App\Service;

use Exception;
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
        ;

        $email
            ->getHeaders()
            ->addTextHeader('templateId', $templateId)
            ->addParameterizedHeader('params', 'params', $params)
        ;

        try {
            $this->mailer->send($email);
        } catch (Exception $e) {
            echo 'Exception when sending Email: ', $e->getMessage(), PHP_EOL;
        }
    }
}
