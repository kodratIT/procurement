<?php

declare(strict_types=1);

namespace App\Enums;

enum ProcurementFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Currency = 'currency';
    case Date = 'date';
    case DateRange = 'date_range';
    case Dropdown = 'dropdown';
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case Upload = 'upload';
    case Relation = 'relation';
    case Variant = 'variant';

    public const TEXT = self::Text;

    public const TEXTAREA = self::Textarea;

    public const NUMBER = self::Number;

    public const CURRENCY = self::Currency;

    public const DATE = self::Date;

    public const DATE_RANGE = self::DateRange;

    public const DROPDOWN = self::Dropdown;

    public const RADIO = self::Radio;

    public const CHECKBOX = self::Checkbox;

    public const UPLOAD = self::Upload;

    public const RELATION = self::Relation;

    public const VARIANT = self::Variant;

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Textarea => 'Textarea',
            self::Number => 'Angka',
            self::Currency => 'Mata uang',
            self::Date => 'Tanggal',
            self::DateRange => 'Rentang tanggal',
            self::Dropdown => 'Dropdown',
            self::Radio => 'Radio',
            self::Checkbox => 'Checkbox',
            self::Upload => 'Upload',
            self::Relation => 'Relasi',
            self::Variant => 'Varian',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
