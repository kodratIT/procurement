<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final class RedactSensitiveData implements ProcessorInterface
{
    private const SENSITIVE_KEYS = [
        'access_token', 'api_key', 'authorization', 'client_secret', 'code',
        'cookie', 'password', 'refresh_token', 'secret', 'token',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->redactMessage($record->message),
            context: $this->redact($record->context),
            extra: $this->redact($record->extra),
        );
    }

    private function redactMessage(string $message): string
    {
        $keys = implode('|', self::SENSITIVE_KEYS);

        return (string) preg_replace(
            '/\\b('.$keys.')\\s*[:=]\\s*[^\\s,;]+/i',
            '$1=[REDACTED]',
            $message,
        );
    }

    private function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitive($key)) {
            return '[REDACTED]';
        }

        if (is_array($value)) {
            $redacted = [];
            foreach ($value as $childKey => $childValue) {
                $redacted[$childKey] = $this->redact($childValue, (string) $childKey);
            }

            return $redacted;
        }

        return $value;
    }

    private function isSensitive(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', ' '], '_', $key));

        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if ($normalized === $sensitiveKey || str_ends_with($normalized, '_'.$sensitiveKey)) {
                return true;
            }
        }

        return false;
    }
}
