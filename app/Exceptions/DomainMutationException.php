<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class DomainMutationException extends RuntimeException
{
    public function __construct(string $operation, Throwable $previous)
    {
        parent::__construct("Domain mutation failed for {$operation}: {$previous->getMessage()}", 0, $previous);
    }
}
