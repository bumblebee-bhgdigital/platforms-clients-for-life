<?php

namespace Platform\ClientsForLife\Exceptions;

use RuntimeException;

/** Fachlicher Fehler im Clients-for-Life-Service (Validierung, ungültiger Übergang). */
class ClientsForLifeException extends RuntimeException
{
    public function __construct(string $message, public readonly string $errorCode = 'VALIDATION_ERROR')
    {
        parent::__construct($message);
    }
}
