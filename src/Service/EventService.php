<?php

namespace App\Service;

use App\Entity\Event;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class EventService extends AbstractController
{
    public const PDF_FOLDER_PATH = '/var/PDF/';

    public function exportTickets(Event $event): void
    {
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($pdfOptions);
        
        $html = $this->renderView('PDF/tickets.html.twig', [
            'event' => $event,
            'tickets' => $event->getTickets()
        ]);

        $dompdf->loadHtml(($html));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $fileName = ucfirst($event->getName()).'.pdf';

        $output = $dompdf->output();

        file_put_contents(self::PDF_FOLDER_PATH.$fileName, $output);
    }

    public function getTicketsFile(Event $event): Response
    {
        $file = new File($this->getParameter('kernel.project_dir').self::PDF_FOLDER_PATH.ucfirst($event->getName()).'.pdf');

        $response = new Response(file_get_contents($file->getPathname()), Response::HTTP_OK, ['Content-Type' => $file->getMimeType()]);
        $response->headers->add([
            'Content-Disposition' => $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $file->getBasename()),
        ]);

        return $response;
    }
}