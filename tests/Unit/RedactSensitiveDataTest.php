<?php

namespace Tests\Unit;

use App\Logging\RedactSensitiveData;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class RedactSensitiveDataTest extends TestCase
{
    public function test_sensitive_context_and_message_values_are_redacted(): void
    {
        $record = new LogRecord(
            datetime: new \DateTimeImmutable,
            channel: 'test',
            level: Level::Warning,
            message: 'access_token=message-token authorization: bearer-token',
            context: [
                'client_secret' => 'client-secret',
                'nested' => ['password' => 'password-value'],
                'safe' => 'retained',
            ],
        );

        $redacted = (new RedactSensitiveData)($record);

        $this->assertSame('access_token=[REDACTED] authorization=[REDACTED]', $redacted->message);
        $this->assertSame('[REDACTED]', $redacted->context['client_secret']);
        $this->assertSame('[REDACTED]', $redacted->context['nested']['password']);
        $this->assertSame('retained', $redacted->context['safe']);
    }
}
