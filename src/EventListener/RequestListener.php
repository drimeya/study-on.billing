<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use App\Service\LoggerService;

class RequestListener
{
    private LoggerService $loggerService;

    public function __construct(LoggerService $loggerService)
    {
        $this->loggerService = $loggerService;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        // Логируем входящий запрос
        $this->loggerService->logInfo('Incoming request', [
            'method' => $request->getMethod(),
            'uri' => $request->getUri(),
            'client_ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'content_type' => $request->headers->get('Content-Type'),
            //'content' => $request->getContent()
        ]);
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();
        
        // Логируем исходящий ответ
        $this->loggerService->logInfo('Outgoing response', [
            'method' => $request->getMethod(),
            'uri' => $request->getUri(),
            'status_code' => $response->getStatusCode(),
            'content_type' => $response->headers->get('Content-Type'),
            //'content' => $response->getContent()
        ]);
    }
}
