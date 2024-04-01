<?php

namespace App\Service;

use App\Entity\Event;
use App\Entity\Ticket;
use App\Entity\User;
use App\Helpers\Constants;
use App\Repository\EventRepository;
use App\Repository\TicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class EventService extends AbstractController
{
    public function __construct(
        private readonly TicketRepository $ticketRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ChartBuilderInterface $chartBuilder,
    ) {
    }

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

    private function getFileName(Event $event): string
    {
        return sprintf('Imeet_%d.pdf', $event->getId());
    }

    public function isOwner(User $user, Event $event): void
    {
        if ($user->getEmail() !== $event->getEmail()) {
            throw new Exception('The actual user doesn\'t own this event.');
        }
    }

    public function checkTicket(Event $event, string $url): JsonResponse
    {
        $ticket = $this->getTicketFromUrl($url);
        
        if (!is_null($ticket) && $event->getTickets()->contains($ticket)) {
            $this->setScannedState($ticket);

            $lastScan = $ticket->getUpdatedAt()->format('d/m/Y - H:i:s');

            return new JsonResponse(
                [
                    'message' => "Ticket validé (dernier scan: {$lastScan})"
                ],
                Response::HTTP_OK
           );
        }

        return new JsonResponse([], Response::HTTP_NOT_FOUND);
    }

    private function getTicketFromUrl(string $url): Ticket|null
    {
        $dataUrl = explode('/', $url);
        $ticketNumber = array_pop($dataUrl);

        return $this->ticketRepository->findOneBy(['number' => $ticketNumber]);
    }

    private function setScannedState(Ticket $ticket): void
    {
        if ($ticket->getState() != Constants::TICKET_STATE_SCANNED) {
            $ticket->setState(Constants::TICKET_STATE_SCANNED);
            $this->entityManager->flush();
        }
    }

    public function getScannedTickets(Event $event): int
    {
        return $this->ticketRepository->getScannedTickets($event);
    }

    public function getPercentageOfTicketsScanned(Event $event): int
    {
        return round(($this->ticketRepository->getScannedTickets($event) / count($event->getTickets()->toArray())) * 100);
    }

    public function getScannedTicketsChart(Event $event): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_DOUGHNUT);

        $scannedTickets = $this->getScannedTickets($event);
        $ticketsLeft = count($event->getTickets()->toArray()) - $scannedTickets;
    
        $chart->setData([
            'labels' => ['Tickets scannés', 'Tickets restants'],
            'datasets' => [
                [
                    'label' => 'Total',
                    'backgroundColor' => [
                        'rgba(220, 53, 69, .8)',
                        'rgba(10, 28, 60, .9)'
                    ],
                    'data' => [$scannedTickets, $ticketsLeft],
                ],
            ],
        ]);

        $chart->setOptions([
            'responsive' => true,
            'plugins' => [
                'title' => [
                    'display' => true,
                    'text' => 'Statuts des tickets'
                ]
            ]
        ]);

        return $chart;
    }

    public function getScannedTicketsByHoursChart(Event $event): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_LINE);

        $hours = $this->getScannedCountForHours($event);

        $chart->setData([
            'labels' => array_keys($hours),
            'datasets' => [
                [
                    'label' => 'Total',
                    'backgroundColor' => 'rgba(220, 53, 69, .5)',
                    'data' => array_values($hours),
                    'borderColor' => 'rgba(220, 53, 69, .5)',
                ],
            ],
        ]);

        $chart->setOptions([
            'responsive' => true,
            'scales' => [
                'y' => [
                    'display' => false
                ],
            ],
            'plugins' => [
                'title' => [
                    'display' => true,
                    'text' => 'Scans par heures'
                ],
                'legend' => [
                    'display' => false
                ],
            ]
        ]);

        return $chart;
    }

    private function getCountOfScannedTicketsForHour(Event $event, int $hour): int
    {
        $tickets = $event->getTickets();
        $count = 0;

        foreach ($tickets as $ticket) {
            if ($ticket->getState() == Constants::TICKET_STATE_SCANNED && (int)$ticket->getUpdatedAt()->format('H') == $hour) {
                $count += 1;
            }
        }

        return $count;
    }

    private function getScannedCountForHours(Event $event): array
    {
        $hours = [];

        for ($i = 0; $i < 24; $i++) {
            $hours[$i] = $this->getCountOfScannedTicketsForHour($event, $i);
        }

        return $hours;
    }

    public function getRushHour(Event $event): int
    {
        $hours = $this->getScannedCountForHours($event);

        $maxValue = max($hours);

        $keys = array_keys($hours, $maxValue);

        return array_shift($keys);
    }

}