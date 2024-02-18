<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\User;
use App\Helpers\Constants;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class EventService extends AbstractController
{
    public function exportTickets(Event $event): void
    {
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($pdfOptions);
        
        $html = $this->renderView('PDF/tickets.html.twig', [
            'event' => $event,
            'tickets' => $event->getTickets()->toArray()
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $fileName = $this->getFileName($event);

        $output = $dompdf->output();

        file_put_contents($this->getParameter('kernel.project_dir').Constants::PDF_FOLDER_PATH.$fileName, $output);
    }

    public function getTicketsFile(Event $event): Response
    {
        $file = new File($this->getParameter('kernel.project_dir').Constants::PDF_FOLDER_PATH.$this->getFileName($event));

        $response = new Response(file_get_contents($file->getPathname()), Response::HTTP_OK, ['Content-Type' => $file->getMimeType()]);
        $response->headers->add([
            'Content-Disposition' => $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $file->getBasename()),
        ]);

        return $response;
    }

    public function deleteQrCodes(Event $event): void
    {
        foreach ($event->getTickets() as $ticket) {
            unlink($this->getParameter('kernel.project_dir').Constants::QRCODES_FOLDER_PATH.$ticket->getQrCode());
        }
    }

    public function getFileName(Event $event): string
    {
        return sprintf('Imeet_%d.pdf', $event->getId());
    }

    public function isOwner(User $user, Event $event): void
    {
        if ($user->getEmail() !== $event->getEmail()) {
            throw new Exception('The actual user doesn\'t own this event.');
        }
    }
}