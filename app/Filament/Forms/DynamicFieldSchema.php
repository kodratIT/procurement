<?php

declare(strict_types=1);

namespace App\Filament\Forms;

use App\Enums\ProcurementFieldType;
use App\Models\ProcurementField;
use App\Services\AttachmentService;
use App\Services\DynamicFieldValidator;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Collection;

final class DynamicFieldSchema
{
    public function __construct(private readonly DynamicFieldValidator $validator) {}

    /**
     * @return list<Component>
     */
    public function components(?int $categoryId, string $stage = ProcurementField::EDITABLE_STAGE_DRAFT): array
    {
        if ($categoryId === null) {
            return [];
        }

        $fields = ProcurementField::query()
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->ordered()
            ->get();
        $conditionalKeys = $this->conditionalFieldKeys($fields);

        return $fields
            ->map(fn (ProcurementField $field): Component => $this->componentForField($field, $stage, $conditionalKeys))
            ->all();
    }

    /**
     * @param  Collection<int, ProcurementField>  $fields
     * @return array<string, true>
     */
    private function conditionalFieldKeys(Collection $fields): array
    {
        $keys = [];

        foreach ($fields as $field) {
            $conditions = $field->visibility_conditions;
            if (is_array($conditions) && isset($conditions['conditions']) && is_array($conditions['conditions'])) {
                $conditions = $conditions['conditions'];
            } elseif (is_array($conditions) && isset($conditions['field'])) {
                $conditions = [$conditions];
            }

            if (! is_array($conditions)) {
                continue;
            }

            foreach ($conditions as $condition) {
                if (is_array($condition) && is_string($condition['field'] ?? null)) {
                    $source = $condition['field'];
                    $key = str_starts_with($source, 'fields.') ? substr($source, 7) : $source;
                    $keys[$key] = true;
                }
            }
        }

        return $keys;
    }

    /** @param array<string, true> $conditionalKeys */
    private function componentForField(ProcurementField $field, string $stage, array $conditionalKeys): Component
    {
        $name = 'fields.'.$field->key;
        $type = $field->field_type instanceof ProcurementFieldType
            ? $field->field_type
            : ProcurementFieldType::from((string) $field->field_type);
        $isEditable = $field->editable_stage === $stage;

        if ($type === ProcurementFieldType::DateRange) {
            $defaults = is_array($field->default_value) ? array_values($field->default_value) : [];
            $component = Fieldset::make($field->label)
                ->schema([
                    $this->dateRangeComponent($name.'.0', $field, 'Mulai', $defaults[0] ?? null, $isEditable, $conditionalKeys),
                    $this->dateRangeComponent($name.'.1', $field, 'Selesai', $defaults[1] ?? null, $isEditable, $conditionalKeys),
                ])
                ->visible(fn (Get $get): bool => $this->isVisible($field, $get))
                ->disabled(! $isEditable);

            return $component;
        }

        $component = match ($type) {
            ProcurementFieldType::Text => TextInput::make($name),
            ProcurementFieldType::Textarea => Textarea::make($name),
            ProcurementFieldType::Number, ProcurementFieldType::Currency => TextInput::make($name)->numeric(),
            ProcurementFieldType::Date => DatePicker::make($name),
            ProcurementFieldType::Dropdown => Select::make($name)->options($this->options($field)),
            ProcurementFieldType::Radio => Radio::make($name)->options($this->options($field)),
            ProcurementFieldType::Checkbox => Checkbox::make($name),
            ProcurementFieldType::Upload => FileUpload::make($name)
                ->multiple(($field->options['multiple'] ?? false) === true)
                ->storeFiles(false)
                ->visibility('private')
                ->acceptedFileTypes($field->options['accepted_file_types'] ?? app(AttachmentService::class)->allowedMimeTypes()),
            ProcurementFieldType::Relation, ProcurementFieldType::Variant => Select::make($name)
                ->options($this->options($field))
                ->searchable(),
        };

        $component
            ->label($field->label)
            ->required($field->is_required)
            ->default($field->default_value)
            ->visible(fn (Get $get): bool => $this->isVisible($field, $get))
            ->disabled(! $isEditable);
        $this->applyBounds($component, $field, $type);

        if (isset($conditionalKeys[$field->key])) {
            $component->live();
        }

        return $component;
    }

    /** @param array<string, true> $conditionalKeys */
    private function dateRangeComponent(
        string $name,
        ProcurementField $field,
        string $label,
        mixed $default,
        bool $isEditable,
        array $conditionalKeys,
    ): DatePicker {
        $component = DatePicker::make($name)
            ->label($label)
            ->required($field->is_required)
            ->default($default)
            ->disabled(! $isEditable);
        $this->applyBounds($component, $field, ProcurementFieldType::Date);
        if (isset($conditionalKeys[$field->key])) {
            $component->live();
        }

        return $component;
    }

    private function applyBounds(Field $component, ProcurementField $field, ProcurementFieldType $type): void
    {
        if ($type === ProcurementFieldType::Date) {
            if ($field->min_value !== null) {
                $component->minDate((string) $field->min_value);
            }

            if ($field->max_value !== null) {
                $component->maxDate((string) $field->max_value);
            }

            return;
        }

        if ($type === ProcurementFieldType::Text || $type === ProcurementFieldType::Textarea) {
            if ($field->min_value !== null) {
                $component->minLength((int) $field->min_value);
            }

            if ($field->max_value !== null) {
                $component->maxLength((int) $field->max_value);
            }

            return;
        }

        if ($type === ProcurementFieldType::Number || $type === ProcurementFieldType::Currency) {
            if ($field->min_value !== null) {
                $component->minValue($field->min_value);
            }

            if ($field->max_value !== null) {
                $component->maxValue($field->max_value);
            }
        }
    }

    /** @return array<int|string, string> */
    private function options(ProcurementField $field): array
    {
        $options = $field->options ?? [];
        if ($options === [] || array_keys($options) !== range(0, count($options) - 1)) {
            return $options;
        }

        return array_combine(array_values($options), array_values($options));
    }

    private function isVisible(ProcurementField $field, Get $get): bool
    {
        $conditions = $field->visibility_conditions;
        if ($conditions === null || $conditions === []) {
            return true;
        }

        $conditionList = $conditions;
        if (isset($conditions['conditions']) && is_array($conditions['conditions'])) {
            $conditionList = $conditions['conditions'];
        } elseif (isset($conditions['field'])) {
            $conditionList = [$conditions];
        }

        if (! is_array($conditionList)) {
            return true;
        }

        $values = [];
        foreach ($conditionList as $condition) {
            if (! is_array($condition) || ! is_string($condition['field'] ?? null)) {
                continue;
            }

            $source = $condition['field'];
            $sourcePath = str_starts_with($source, 'fields.') ? $source : 'fields.'.$source;
            $value = $get($sourcePath, true);
            data_set($values, $source, $value);
            data_set($values, $sourcePath, $value);
        }

        return $this->validator->isVisible($field, $values);
    }
}
