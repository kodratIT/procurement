<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\DomainMutationException;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class DomainTransaction
{
    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function run(string $operation, Closure $callback): mixed
    {
        try {
            return DB::transaction($callback);
        } catch (Throwable $e) {
            if ($e instanceof DomainMutationException || $e instanceof ValidationException) {
                throw $e;
            }

            throw new DomainMutationException($operation, $e);
        }
    }
}
