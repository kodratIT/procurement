<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class PurchaseRequestTotalCalculator
{
    public function lineTotal(mixed $quantity, mixed $unitPrice): string
    {
        $quantity = $this->decimal($quantity, 'quantity');
        $unitPrice = $this->decimal($unitPrice ?? 0, 'unit_price');

        if (bccomp($quantity, '0', 2) <= 0) {
            throw new InvalidArgumentException('quantity must be greater than zero.');
        }

        if (str_starts_with($unitPrice, '-')) {
            throw new InvalidArgumentException('unit_price must not be negative.');
        }

        return bcmul($quantity, $unitPrice, 2);
    }

    /**
     * @param  iterable<array<string, mixed>|PurchaseRequestItem>  $lines
     * @return array{line_totals: list<string>, header_total: string}
     */
    public function totals(iterable $lines): array
    {
        $lineTotals = [];
        $headerTotal = '0.00';

        foreach ($lines as $line) {
            $lineTotal = $line instanceof PurchaseRequestItem
                ? $this->lineTotal($line->quantity, $line->unit_price)
                : $this->lineTotal($line['quantity'] ?? null, $line['unit_price'] ?? $line['estimated_price'] ?? 0);

            $lineTotals[] = $lineTotal;
            $headerTotal = bcadd($headerTotal, $lineTotal, 2);
        }

        return [
            'line_totals' => $lineTotals,
            'header_total' => $headerTotal,
        ];
    }

    public function recalculateHeader(PurchaseRequest $request): string
    {
        $total = $this->totals($request->items()->get())['header_total'];

        DB::table($request->getTable())
            ->where($request->getKeyName(), $request->getKey())
            ->update(['total_amount' => $total, 'updated_at' => now()]);

        $request->forceFill(['total_amount' => $total]);

        return $total;
    }

    public function sync(PurchaseRequest $request): void
    {
        DB::transaction(function () use ($request): void {
            $request->items()->get()->each(function (PurchaseRequestItem $item): void {
                $item->line_total = $this->lineTotal($item->quantity, $item->unit_price);
                $item->saveQuietly();
            });

            $this->recalculateHeader($request);
        });
    }

    private function decimal(mixed $value, string $name): string
    {
        if (is_int($value)) {
            return (string) $value.'.00';
        }

        if (is_float($value)) {
            $value = number_format($value, 2, '.', '');
        }

        if (! is_string($value) || preg_match('/\A-?\d+(?:\.\d{1,2})?\z/', $value) !== 1) {
            throw new InvalidArgumentException("{$name} must be a decimal value.");
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return $whole.'.'.str_pad($fraction, 2, '0');
    }
}
