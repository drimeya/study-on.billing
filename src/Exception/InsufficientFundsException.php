<?php

namespace App\Exception;

class InsufficientFundsException extends \RuntimeException
{
    public function __construct(string $message = 'На вашем счету недостаточно средств', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
