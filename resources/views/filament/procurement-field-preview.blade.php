@php
    $type = $field->field_type instanceof \App\Enums\ProcurementFieldType
        ? $field->field_type
        : \App\Enums\ProcurementFieldType::from((string) $field->field_type);
$options = $field->options ?? [];
$options = array_is_list($options) ? array_combine($options, $options) : $options;
$sampleValue = $field->default_value ?? match ($type) {
        \App\Enums\ProcurementFieldType::Checkbox => true,
        \App\Enums\ProcurementFieldType::Date => '2026-09-15',
        \App\Enums\ProcurementFieldType::DateRange => ['2026-09-15', '2026-09-20'],
        default => $options === [] ? 'Contoh nilai' : array_key_first($options),
    };
@endphp

<div class="grid gap-4">
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
        <div class="mb-3 text-sm font-medium text-gray-950 dark:text-white">{{ $field->label }}</div>

        @if ($type === \App\Enums\ProcurementFieldType::Checkbox)
            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" @checked($sampleValue) disabled class="rounded border-gray-300 text-primary-600 shadow-sm dark:border-gray-600 dark:bg-gray-800">
                {{ $sampleValue ? 'Ya' : 'Tidak' }}
            </label>
        @elseif ($type === \App\Enums\ProcurementFieldType::DateRange)
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($sampleValue as $date)
                    <input type="date" value="{{ $date }}" disabled class="rounded-lg border-gray-300 bg-white text-sm text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                @endforeach
            </div>
        @elseif (in_array($type, [\App\Enums\ProcurementFieldType::Dropdown, \App\Enums\ProcurementFieldType::Relation, \App\Enums\ProcurementFieldType::Variant], true))
            <select disabled class="w-full rounded-lg border-gray-300 bg-white text-sm text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
                @foreach ($options as $value => $label)
                    <option @selected((string) $value === (string) $sampleValue) value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        @elseif ($type === \App\Enums\ProcurementFieldType::Radio)
            <div class="flex flex-wrap gap-4">
                @foreach ($options as $value => $label)
                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="radio" @checked((string) $value === (string) $sampleValue) disabled class="border-gray-300 text-primary-600 dark:border-gray-600 dark:bg-gray-800">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        @elseif ($type === \App\Enums\ProcurementFieldType::Textarea)
            <textarea disabled rows="3" class="w-full rounded-lg border-gray-300 bg-white text-sm text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $sampleValue }}</textarea>
        @elseif ($type === \App\Enums\ProcurementFieldType::Upload)
            <div class="rounded-lg border border-dashed border-gray-300 bg-white p-4 text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400">Contoh area upload dokumen</div>
        @else
            <input type="{{ $type === \App\Enums\ProcurementFieldType::Date ? 'date' : 'text' }}" value="{{ is_array($sampleValue) ? json_encode($sampleValue) : $sampleValue }}" disabled class="w-full rounded-lg border-gray-300 bg-white text-sm text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300">
        @endif
    </div>

    <dl class="grid gap-2 text-sm sm:grid-cols-2">
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Key</dt>
            <dd class="font-mono text-gray-950 dark:text-white">{{ $field->key }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Tahap dapat diubah</dt>
            <dd class="text-gray-950 dark:text-white">{{ $field->editable_stage }}</dd>
        </div>
    </dl>
</div>
