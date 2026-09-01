<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProcurementFieldType;
use App\Models\ProcurementField;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class DynamicFieldValidator
{
    /**
     * Compile active, visible field definitions into Laravel validation rules.
     *
     * @param  iterable<ProcurementField>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, array<int, mixed>>
     */
    public function rules(iterable $fields, array $values = []): array
    {
        $fieldValues = is_array($values['fields'] ?? null) ? $values['fields'] : $values;
        $prefix = $fieldValues === $values ? '' : 'fields.';
        $conditionValues = $prefix === '' ? $fieldValues : [...$values, ...$fieldValues];

        return $this->compile($fields, $conditionValues, $prefix);
    }

    /**
     * @param  iterable<ProcurementField>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, array<int, mixed>>
     */
    public function compile(iterable $fields, array $values = [], string $prefix = ''): array
    {
        $rules = [];

        foreach ($fields as $field) {
            if (! $field->is_active || ! $this->isVisible($field, $values)) {
                continue;
            }

            $key = $prefix.$field->key;
            $fieldRules = $field->is_required ? ['required'] : ['nullable'];
            $rules[$key] = [...$fieldRules, ...$this->typeRules($field)];

            $type = $field->field_type instanceof ProcurementFieldType
                ? $field->field_type
                : ProcurementFieldType::from((string) $field->field_type);

            if ($type === ProcurementFieldType::DateRange) {
                $rules[$key.'.0'] = ['date', ...$this->dateRules($field)];
                $rules[$key.'.1'] = ['date', ...$this->dateRules($field)];
                $rules[$key.'.0'][] = 'before_or_equal:'.$key.'.1';
            }

            if ($type === ProcurementFieldType::Upload && ($field->options['multiple'] ?? false) === true) {
                $rules[$key.'.*'] = ['file'];
            }
        }

        return $rules;
    }

    /**
     * Validate submitted dynamic values and return only validated values.
     *
     * @param  iterable<ProcurementField>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function validate(iterable $fields, array $values): array
    {
        $fieldValues = is_array($values['fields'] ?? null) ? $values['fields'] : $values;
        $prefix = $fieldValues === $values ? '' : 'fields.';
        $conditionValues = $prefix === '' ? $fieldValues : [...$values, ...$fieldValues];

        return Validator::make($values, $this->compile($fields, $conditionValues, $prefix))->validate();
    }

    /**
     * @param  iterable<ProcurementField>  $fields
     * @param  array<string, mixed>  $values
     */
    public function validator(iterable $fields, array $values): ValidatorContract
    {
        $fieldValues = is_array($values['fields'] ?? null) ? $values['fields'] : $values;
        $prefix = $fieldValues === $values ? '' : 'fields.';
        $conditionValues = $prefix === '' ? $fieldValues : [...$values, ...$fieldValues];

        return Validator::make($values, $this->compile($fields, $conditionValues, $prefix));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function isVisible(ProcurementField $field, array $values): bool
    {
        $conditions = $field->visibility_conditions;

        if ($conditions === null || $conditions === []) {
            return true;
        }

        if (isset($conditions['conditions']) && is_array($conditions['conditions'])) {
            $logic = (string) ($conditions['logic'] ?? 'all');
            $conditions = $conditions['conditions'];
        } elseif (isset($conditions['field'])) {
            $conditions = [$conditions];
            $logic = 'all';
        } else {
            $logic = 'all';
        }

        if (! is_array($conditions) || $conditions === []) {
            return true;
        }

        $results = array_map(
            fn (mixed $condition): bool => is_array($condition) && $this->conditionMatches($condition, $values),
            $conditions,
        );

        return $logic === 'any' ? in_array(true, $results, true) : ! in_array(false, $results, true);
    }

    /**
     * @param  iterable<ProcurementField>  $fields
     * @param  array<string, mixed>  $values
     * @return list<ProcurementField>
     */
    public function visibleFields(iterable $fields, array $values = []): array
    {
        $visible = [];

        foreach ($fields as $field) {
            if ($field->is_active && $this->isVisible($field, $values)) {
                $visible[] = $field;
            }
        }

        return $visible;
    }

    /**
     * @return list<mixed>
     */
    private function typeRules(ProcurementField $field): array
    {
        $type = $field->field_type instanceof ProcurementFieldType
            ? $field->field_type
            : ProcurementFieldType::from((string) $field->field_type);

        return match ($type) {
            ProcurementFieldType::Text, ProcurementFieldType::Textarea => [
                'string',
                ...$this->lengthRules($field),
            ],
            ProcurementFieldType::Number, ProcurementFieldType::Currency => [
                'numeric',
                ...$this->numericRules($field),
            ],
            ProcurementFieldType::Date => [
                'date',
                ...$this->dateRules($field),
            ],
            ProcurementFieldType::DateRange => ['array', 'size:2'],
            ProcurementFieldType::Dropdown, ProcurementFieldType::Radio => [
                'string',
                ...$this->optionRules($field),
            ],
            ProcurementFieldType::Checkbox => ['boolean'],
            ProcurementFieldType::Upload => $this->uploadRules($field),
            ProcurementFieldType::Relation, ProcurementFieldType::Variant => [
                'integer',
                ...$this->optionRules($field),
            ],
        };
    }

    /** @return list<string> */
    private function lengthRules(ProcurementField $field): array
    {
        return [
            ...($field->min_value !== null ? ['min:'.$field->min_value] : []),
            ...($field->max_value !== null ? ['max:'.$field->max_value] : []),
        ];
    }

    /** @return list<string> */
    private function numericRules(ProcurementField $field): array
    {
        return [
            ...($field->min_value !== null ? ['min:'.$field->min_value] : []),
            ...($field->max_value !== null ? ['max:'.$field->max_value] : []),
        ];
    }

    /** @return list<string> */
    private function dateRules(ProcurementField $field): array
    {
        return [
            ...($field->min_value !== null ? ['after_or_equal:'.$field->min_value] : []),
            ...($field->max_value !== null ? ['before_or_equal:'.$field->max_value] : []),
        ];
    }

    /** @return list<mixed> */
    private function optionRules(ProcurementField $field): array
    {
        $options = $this->optionValues($field->options);

        return $options === [] ? [] : [Rule::in($options)];
    }

    /** @return list<string> */
    private function uploadRules(ProcurementField $field): array
    {
        if (($field->options['multiple'] ?? false) === true) {
            return ['array', 'min:1'];
        }

        return ['file'];
    }

    /**
     * @param  array<string, mixed>|null  $options
     * @return list<mixed>
     */
    private function optionValues(?array $options): array
    {
        if ($options === null || $options === []) {
            return [];
        }

        if (array_keys($options) === range(0, count($options) - 1)) {
            return array_values($options);
        }

        return array_keys($options);
    }

    /** @param array<string, mixed> $condition */
    private function conditionMatches(array $condition, array $values): bool
    {
        $actual = data_get($values, $condition['field'] ?? null);
        $operator = (string) ($condition['operator'] ?? 'equals');
        $expected = $condition['value'] ?? null;

        return match ($operator) {
            'equals', '=', '==' => $actual == $expected,
            'not_equals', '!=', '<>' => $actual != $expected,
            'in' => in_array($actual, (array) $expected, true),
            'not_in' => ! in_array($actual, (array) $expected, true),
            'contains' => is_string($actual) && str_contains($actual, (string) $expected),
            'greater_than', '>' => is_numeric($actual) && $actual > $expected,
            'less_than', '<' => is_numeric($actual) && $actual < $expected,
            'greater_or_equal', '>=' => is_numeric($actual) && $actual >= $expected,
            'less_or_equal', '<=' => is_numeric($actual) && $actual <= $expected,
            'is_empty' => blank($actual),
            'is_not_empty' => filled($actual),
            default => false,
        };
    }
}
