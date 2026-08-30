<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Throwable;

final class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse|Response
    {
        $checks = [];

        foreach ([
            'database' => static fn (): mixed => DB::connection()->select('select 1'),
            'cache' => static function (): bool {
                $cache = Cache::store();
                $key = '__application_health_check__';

                $cache->put($key, true, 1);

                try {
                    if ($cache->get($key) !== true) {
                        throw new RuntimeException('Cache health check could not be read.');
                    }
                } finally {
                    $cache->forget($key);
                }

                return true;
            },
            'queue' => static fn (): mixed => Queue::connection()->size(),
        ] as $name => $check) {
            try {
                $check();
                $checks[$name] = 'up';
            } catch (Throwable $exception) {
                $checks[$name] = 'down';
                Log::warning('Application health check failed.', [
                    'check' => $name,
                    'exception' => $exception::class,
                ]);
            }
        }

        $healthy = ! in_array('down', $checks, true);
        $status = $healthy ? 'up' : 'down';
        $statusCode = $healthy ? 200 : 503;
        $payload = compact('status', 'checks');

        $response = $request->expectsJson()
            ? response()->json($payload, $statusCode)
            : response()->view('health', $payload, $statusCode);

        return $response->header('Cache-Control', 'no-store');
    }
}
