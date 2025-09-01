<?php
namespace App\Controller;

use App\Dto\Extraction;
use App\Message\ExtractorMessage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;

class ExtractionController
{
    public function __construct(
        private MessageBusInterface $bus,
        private TransportInterface $asyncTransport // Transport korrekt injiziert
    ) {}

    public function __invoke(Request $request, SerializerInterface $serializer): JsonResponse
    {
        /** @var Extraction $data */
        $data = $serializer->deserialize($request->getContent(), Extraction::class, 'json');
        $this->bus->dispatch(new ExtractorMessage($data->getPath()));

        // get queue count
        $queueCount = method_exists($this->asyncTransport, 'getMessageCount')
            ? $this->asyncTransport->getMessageCount()
            : null;

        return new JsonResponse([
            'status' => 'queued',
            'queue_count' => $queueCount,
            'sent_path' => $data->getPath()
        ], 202);
    }
}
