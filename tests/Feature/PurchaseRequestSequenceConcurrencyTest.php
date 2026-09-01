<?php

namespace Tests\Feature;

use App\Services\PurchaseRequestNumberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurchaseRequestSequenceConcurrencyTest extends TestCase
{
    public function test_monthly_sequence_allocates_unique_numbers_under_parallel_contention(): void
    {
        if (DB::getDriverName() !== 'sqlite' || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Parallel SQLite regression requires pcntl and sqlite.');
        }

        $path = tempnam(sys_get_temp_dir(), 'purchase-request-sequence-');
        $month = now()->format('Ym');
        $workerCount = 4;
        $readyDir = $path.'-ready';
        $resultDir = $path.'-results';
        mkdir($readyDir);
        mkdir($resultDir);

        $originalDatabase = config('database.connections.sqlite.database');
        config(['database.connections.sqlite.database' => $path]);
        DB::purge('sqlite');

        try {
            Schema::connection('sqlite')->create('purchase_request_number_sequences', function ($table): void {
                $table->string('month', 6)->primary();
                $table->unsignedInteger('next_sequence')->default(1);
                $table->timestamps();
            });
            DB::connection('sqlite')->table('purchase_request_number_sequences')->insert([
                'month' => $month,
                'next_sequence' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $children = [];
            for ($worker = 0; $worker < $workerCount; $worker++) {
                $pid = pcntl_fork();
                $this->assertNotSame(-1, $pid, 'Unable to fork sequence worker.');

                if ($pid === 0) {
                    DB::purge('sqlite');
                    file_put_contents($readyDir.'/'.$worker, 'ready');
                    while (! file_exists($path.'-start')) {
                        usleep(1_000);
                    }

                    try {
                        $number = app(PurchaseRequestNumberService::class)->next(now());
                        file_put_contents($resultDir.'/'.$worker, json_encode(['number' => $number]));
                        exit(0);
                    } catch (\Throwable $exception) {
                        file_put_contents($resultDir.'/'.$worker, json_encode(['error' => $exception->getMessage()]));
                        exit(1);
                    }
                }

                $children[] = $pid;
            }

            $deadline = microtime(true) + 5;
            while (count(glob($readyDir.'/*')) < $workerCount && microtime(true) < $deadline) {
                usleep(1_000);
            }
            $this->assertCount($workerCount, glob($readyDir.'/*'), 'Parallel workers did not reach the barrier.');
            touch($path.'-start');

            foreach ($children as $pid) {
                $status = 0;
                pcntl_waitpid($pid, $status);
                $this->assertTrue(pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0, 'Sequence worker failed.');
            }

            $numbers = collect(range(0, $workerCount - 1))->map(function (int $worker) use ($resultDir): string {
                $result = json_decode((string) file_get_contents($resultDir.'/'.$worker), true, flags: JSON_THROW_ON_ERROR);
                $this->assertArrayHasKey('number', $result, $result['error'] ?? 'Worker returned no number.');

                return $result['number'];
            });

            $this->assertCount($workerCount, $numbers->unique());
            $this->assertSame(range(1, $workerCount), $numbers->map(fn (string $number): int => (int) substr($number, -4))->sort()->values()->all());
            $this->assertSame($workerCount + 1, DB::connection('sqlite')->table('purchase_request_number_sequences')->where('month', $month)->value('next_sequence'));
        } finally {
            DB::purge('sqlite');
            config(['database.connections.sqlite.database' => $originalDatabase]);
            DB::purge('sqlite');
            @unlink($path);
            @unlink($path.'-start');
            foreach ([$readyDir, $resultDir] as $directory) {
                foreach (glob($directory.'/*') ?: [] as $file) {
                    @unlink($file);
                }
                @rmdir($directory);
            }
        }
    }
}
