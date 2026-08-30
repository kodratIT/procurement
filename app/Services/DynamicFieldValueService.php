<?php

namespace App\Services;

use App\Models\ProcurementCategoryField;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestFieldValue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DynamicFieldValueService
{
    /** Validate and normalize a dynamic value before it is persisted. */
    public function validate(ProcurementCategoryField $field, mixed $value): mixed
    {
        $validated = Validator::make(['value' => $value], ['value' => $this->rulesFor($field)])->validate();

        return $validated['value'] ?? null;
    }

    /** Return the complete Laravel rule set for a field definition. */
    public function rulesFor(ProcurementCategoryField $field): array
    {
        $rules = match ($field->type) {
            'text' => ['string', 'max:10000'],
            'number' => ['numeric'],
            'date' => ['date_format:Y-m-d'],
            'select' => ['string', 'max:255', Rule::in($this->selectOptions($field))],
            'file' => [fn (string $attribute, mixed $value, \Closure $fail) =>
                $value instanceof UploadedFile || (is_string($value) && $this->isSafePath($value))
                    ? null : $fail('The file value must be an uploaded file or a safe storage path.')],
            'relation' => ['integer', 'min:1'],
            default => throw ValidationException::withMessages(['value' => 'Unsupported dynamic field type.']),
        };

        array_unshift($rules, $field->is_required ? 'required' : 'nullable');

        return $rules;
    }

    /** Validate and atomically upsert a request value. Files are stored privately. */
    public function save(PurchaseRequest $request, ProcurementCategoryField $field, mixed $value): PurchaseRequestFieldValue
    {
        $normalized = $this->validate($field, $value);
        $filePath = null;

        if ($field->type === 'file' && $normalized !== null) {
            $filePath = $normalized instanceof UploadedFile
                ? $normalized->store('purchase-request-fields', ['disk' => 'local'])
                : $normalized;
            $normalized = null;
        }

        return DB::transaction(fn () => PurchaseRequestFieldValue::updateOrCreate(
            ['purchase_request_id' => $request->getKey(), 'field_id' => $field->getKey()],
            [
                'value' => $normalized === null ? null : json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'file_path' => $filePath,
            ],
        ));
    }

    private function selectOptions(ProcurementCategoryField $field): array
    {
        return collect($field->options ?? [])
            ->map(fn ($option) => is_array($option) ? ($option['value'] ?? null) : $option)
            ->filter(fn ($option) => is_string($option) && $option !== '')
            ->values()->all();
    }

    private function isSafePath(string $path): bool
    {
        return $path !== '' && ! str_starts_with($path, '/') && ! str_contains($path, '..')
            && preg_match('/^[A-Za-z0-9_\/.\\-]+$/', $path) === 1
            && ! str_starts_with(strtolower($path), 'http');
    }
}
