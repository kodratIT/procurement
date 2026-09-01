@php
    $quotations = $comparison['quotations'];
@endphp

<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b text-left">
                <th class="px-3 py-2">Item PR</th>
                @foreach ($quotations as $quotation)
                    <th class="px-3 py-2">
                        {{ $quotation['vendor_name'] }}<br>
                        <span class="text-xs font-normal">{{ $quotation['quotation_number'] }}</span>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($comparison['lines'] as $line)
                <tr class="border-b">
                    <td class="px-3 py-2">
                        {{ $line['item_name'] }}<br>
                        <span class="text-xs text-gray-500">{{ $line['quantity'] }} {{ $line['unit_name'] }}</span>
                    </td>
                    @foreach ($quotations as $quotation)
                        @php($price = $line['quotations'][$quotation['id']] ?? null)
                        <td class="px-3 py-2">
                            @if ($price)
                                {{ number_format((float) $price['line_total'], 2, ',', '.') }}<br>
                                <span class="text-xs text-gray-500">{{ number_format((float) $price['unit_price'], 2, ',', '.') }} / unit</span>
                            @else
                                <span class="text-danger-600">Tidak tercakup</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="font-semibold">
                <td class="px-3 py-2">Total sebanding</td>
                @foreach ($quotations as $quotation)
                    <td class="px-3 py-2">
                        {{ number_format((float) $quotation['total_amount'], 2, ',', '.') }}
                        @if (! $quotation['coverage']['complete'])
                            <span class="block text-xs text-danger-600">Line belum lengkap</span>
                        @endif
                    </td>
                @endforeach
            </tr>
        </tfoot>
    </table>
</div>
