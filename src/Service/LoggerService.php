<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class LoggerService
{
    private LoggerInterface $logger;
    private LoggerInterface $securityLogger;

    public function __construct(
        LoggerInterface $logger,
        LoggerInterface $securityLogger
    ) {
        $this->logger = $logger;
        $this->securityLogger = $securityLogger;
    }

    /**
     * Логирование ошибок безопасности
     */
    public function logSecurityViolation(string $violation, array $context = []): void
    {
        $this->securityLogger->warning("Security violation: {$violation}", $context);
    }

    /**
     * Логирование ошибок приложения
     */
    public function logError(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    /**
     * Логирование информационных сообщений
     */
    public function logInfo(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    /**
     * Логирование отладочной информации
     */
    public function logDebug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }
}
