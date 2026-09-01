<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Services\PurchaseRequestTotalCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PurchaseRequestTotalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_totals_are_deterministic_decimal_values(): void
    {
        $totals = app(PurchaseRequestTotalCalculator::class)->totals([
            ['quantity' => '3', 'unit_price' => '12500.50'],
            ['quantity' => '2.00', 'estimated_price' => '10000.25'],
        ]);

        $this->assertSame(['37501.50', '20000.50'], $totals['line_totals']);
        $this->assertSame('57502.00', $totals['header_total']);
    }

    #[DataProvider('nonPositiveQuantityProvider')]
    public function test_zero_and_negative_quantities_are_rejected(string $quantity): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(PurchaseRequestTotalCalculator::class)->lineTotal($quantity, '100.00');
    }

    /** @return array<string, array{string}> */
    public static function nonPositiveQuantityProvider(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-1'],
            'zero with scale' => ['0.00'],
        ];
    }

    public function test_item_total_and_header_ignore_client_supplied_totals(): void
    {
        $request = PurchaseRequest::factory()->create(['total_amount' => 999999]);
        $item = PurchaseRequestItem::factory()->create([
            'purchase_request_id' => $request->id,
            'quantity' => '2.00',
            'unit_price' => '100.50',
            'line_total' => '999999.99',
        ]);

        $request->syncTotals();

        $this->assertSame('201.00', $item->refresh()->line_total);
        $this->assertSame('201.00', $request->refresh()->total_amount);
    }

    public function test_negative_unit_prices_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(PurchaseRequestTotalCalculator::class)->lineTotal('1', '-0.01');
    }
}
