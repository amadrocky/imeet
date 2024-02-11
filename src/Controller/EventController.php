<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/app', name: 'app_event')]
class EventController extends AbstractController
{
    #[Route('/event/{id}', name: '_scan')]
    public function index(Event $event): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED');

        $this->isOwner($this->getUser(), $event);

        return $this->render('event/scan.html.twig', [
            'event' => $event
        ]);
    }

    #[Route('/event/{id}/tickets', name: '_tickets')]
    public function createAndExportTickets(Event $event)
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED');

        $this->isOwner($this->getUser(), $event);

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
        $dompdf->stream(ucfirst($event->getName()) . '.pdf', [
            "Attachment" => false
        ]);
    }

    public function isOwner(User $user, Event $event): void
    {
        if ($user->getEmail() !== $event->getEmail()) {
            throw new Exception('The actual user doesn\'t own this event.');
        }
    }
}
