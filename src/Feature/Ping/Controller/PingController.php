<?php

declare(strict_types=1);

namespace Nqphp\Feature\Ping\Controller;

use Nqphp\Core\Attribute\Route;
use Nqphp\Core\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class PingController extends AbstractController
{
    #[Route('/api/ping', name: 'api_ping', methods: ['GET'])]
    public function index(): Response
    {
        return new JsonResponse(['status' => 'ok', 'time' => time()]);
    }
}
