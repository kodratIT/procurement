<?php

declare(strict_types=1);

namespace App\Enums;

enum ProcurementCategoryType: string
{
    case Goods = 'goods';
    case Service = 'service';
    case GoodsAndServices = 'mixed';

    public const GOODS = self::Goods;

    public const SERVICE = self::Service;

    public const MIXED = self::GoodsAndServices;

    public function label(): string
    {
        return match ($this) {
            self::Goods => 'Barang',
            self::Service => 'Jasa',
            self::GoodsAndServices => 'Barang dan Jasa',
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
