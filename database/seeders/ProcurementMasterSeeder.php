<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DepartureBatch;
use App\Models\ProcurementCategory;
use App\Models\ProcurementItem;
use App\Models\ProcurementUnit;
use App\Models\ProcurementVariant;
use App\Models\Vendor;
use App\Models\VendorItem;
use Illuminate\Database\Seeder;

class ProcurementMasterSeeder extends Seeder
{
    public function run(): void
    {
        $units = [];
        foreach ([['PCS', 'Pieces', 'pcs'], ['SET', 'Set', 'set'], ['BOX', 'Box', 'box'], ['PACK', 'Paket', 'pkt']] as [$code,$name,$symbol]) {
            $units[$code] = ProcurementUnit::updateOrCreate(['code' => $code], ['name' => $name, 'symbol' => $symbol, 'is_active' => true]);
        }
        $categories = [];
        $categoryConfigs = [
            'PAKAIAN' => [
                'name' => 'Pakaian Jamaah',
                'type' => 'goods',
                'requires_batch' => true,
                'requires_jamaah' => true,
                'requires_vendor' => true,
                'requires_quotation' => true,
                'requires_receipt' => true,
                'requires_invoice' => true,
                'requires_po' => true,
                'workflow_reference' => 'standard-procurement',
                'number_template' => 'purchase_request',
            ],
            'IBADAH' => [
                'name' => 'Perlengkapan Ibadah',
                'type' => 'goods',
                'requires_batch' => true,
                'requires_jamaah' => true,
                'requires_vendor' => true,
                'requires_quotation' => true,
                'requires_receipt' => true,
                'requires_invoice' => true,
                'requires_po' => true,
                'workflow_reference' => 'standard-procurement',
                'number_template' => 'purchase_request',
            ],
            'KESEHATAN' => [
                'name' => 'Kesehatan',
                'type' => 'goods',
                'requires_batch' => false,
                'requires_jamaah' => false,
                'requires_vendor' => true,
                'requires_quotation' => true,
                'requires_receipt' => true,
                'requires_invoice' => true,
                'requires_po' => true,
                'workflow_reference' => 'standard-procurement',
                'number_template' => 'purchase_request',
            ],
            'PERJALANAN' => [
                'name' => 'Perlengkapan Perjalanan',
                'type' => 'mixed',
                'requires_batch' => true,
                'requires_jamaah' => true,
                'requires_vendor' => true,
                'requires_quotation' => true,
                'requires_receipt' => true,
                'requires_invoice' => true,
                'requires_po' => true,
                'workflow_reference' => 'standard-procurement',
                'number_template' => 'purchase_request',
            ],
            'JAMAAH' => [
                'name' => 'Perlengkapan Jamaah',
                'type' => 'goods',
                'requires_batch' => true,
                'requires_jamaah' => true,
                'requires_vendor' => true,
                'requires_quotation' => true,
                'requires_receipt' => true,
                'requires_invoice' => true,
                'requires_po' => true,
                'workflow_reference' => 'jamaah-supplies',
                'number_template' => 'purchase_request_jamaah',
            ],
            'HOTEL' => [
                'name' => 'Hotel',
                'type' => 'service',
                'requires_batch' => true,
                'requires_jamaah' => false,
                'requires_vendor' => true,
                'requires_quotation' => true,
                'requires_receipt' => true,
                'requires_invoice' => true,
                'requires_po' => true,
                'workflow_reference' => 'hotel-procurement',
                'number_template' => 'purchase_request_hotel',
            ],
            'TRANSPORT' => [
                'name' => 'Transportasi',
                'type' => 'service',
                'requires_batch' => true,
                'requires_jamaah' => false,
                'requires_vendor' => true,
                'requires_quotation' => true,
                'requires_receipt' => true,
                'requires_invoice' => true,
                'requires_po' => true,
                'workflow_reference' => 'transport-procurement',
                'number_template' => 'purchase_request_transport',
            ],
        ];
        foreach ($categoryConfigs as $code => $attributes) {
            $categories[$code] = ProcurementCategory::updateOrCreate(
                ['code' => $code],
                [...$attributes, 'is_active' => true],
            );
        }
        $items = [
            ['SERAGAM', 'Seragam Jamaah', 'PAKAIAN', 'SET', 325000, ['Bahan' => 'Drill premium', 'Warna' => 'Putih / Abu-abu']],
            ['KAIN-IHRAM', 'Kain Ihram', 'PAKAIAN', 'SET', 175000, ['Bahan' => 'Katun 100%', 'Panjang' => '2,5 m', 'Jenis' => 'Tanpa jahit']],
            ['KOPER', 'Koper Jamaah', 'PERJALANAN', 'PCS', 425000, ['Bahan' => 'ABS', 'Ukuran' => '20 inci', 'Berat' => '3,2 kg']],
            ['MUKENA', 'Mukena', 'IBADAH', 'PCS', 210000, ['Bahan' => 'Rayon premium', 'Ukuran' => 'All size', 'Paket' => 'Mukena + tas']],
            ['ID-CARD', 'ID Card Jamaah', 'JAMAAH', 'PCS', 15000, ['Bahan' => 'PVC', 'Ukuran' => '86 x 54 mm']],
            ['LABEL-KOPER', 'Label Koper', 'JAMAAH', 'PCS', 12000, ['Bahan' => 'PVC', 'Tali' => 'Kulit sintetis']],
            ['TAS-PASPOR', 'Tas Paspor', 'JAMAAH', 'PCS', 45000, ['Bahan' => 'Kanvas', 'Kompartemen' => '2']],
        ];
        foreach ($items as [$code, $name, $category, $unit, $price, $specifications]) {
            $item = ProcurementItem::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'category_id' => $categories[$category]->id,
                    'unit_id' => $units[$unit]->id,
                    'reference_price' => $price,
                    'reference_currency' => 'IDR',
                    'specifications' => $specifications,
                    'is_active' => true,
                ],
            );

            $variantDefinitions = match ($code) {
                'SERAGAM', 'KAIN-IHRAM' => [
                    ['S', 'Kecil'],
                    ['M', 'Sedang'],
                    ['L', 'Besar'],
                    ['XL', 'Ekstra Besar'],
                ],
                'MUKENA' => [
                    ['PUTIH', 'Putih'],
                    ['NAVY', 'Navy'],
                    ['ROSE', 'Rose'],
                ],
                'KOPER' => [
                    ['20', '20 inci'],
                    ['24', '24 inci'],
                ],
                default => [],
            };

            foreach ($variantDefinitions as [$variantCode, $variantValue]) {
                $variationType = in_array($code, ['MUKENA', 'KOPER'], true)
                    ? ($code === 'MUKENA' ? ProcurementVariant::TYPE_WARNA : ProcurementVariant::TYPE_UKURAN)
                    : ProcurementVariant::TYPE_UKURAN;

                ProcurementVariant::updateOrCreate(
                    ['item_id' => $item->id, 'code' => $variantCode],
                    [
                        'variation_type' => $variationType,
                        'name' => ucfirst($variationType).' '.$variantValue,
                        'value' => $variantValue,
                        'is_active' => true,
                    ],
                );
            }
        }
        $vendors = [
            'VND-UMROH-001' => Vendor::updateOrCreate(
                ['code' => 'VND-UMROH-001'],
                [
                    'name' => 'Al Madinah Supplies',
                    'vendor_type' => Vendor::TYPE_GOODS,
                    'contact_name' => 'Tim Sales',
                    'phone' => '021-555-0101',
                    'email' => 'sales@almadinah.example',
                    'tax_number' => '01.234.567.8-901.000',
                    'is_active' => true,
                ],
            ),
            'VND-UMROH-002' => Vendor::updateOrCreate(
                ['code' => 'VND-UMROH-002'],
                [
                    'name' => 'Nusantara Travel Gear',
                    'vendor_type' => Vendor::TYPE_GOODS,
                    'contact_name' => 'Customer Service',
                    'phone' => '021-555-0102',
                    'email' => 'order@nusantaragear.example',
                    'tax_number' => '02.345.678.9-012.000',
                    'is_active' => true,
                ],
            ),
        ];

        $vendorItems = [
            'VND-UMROH-001' => [
                ['SERAGAM', 315000],
                ['KOPER', 410000],
                ['MUKENA', 200000],
            ],
            'VND-UMROH-002' => [
                ['KOPER', 405000],
                ['KAIN-IHRAM', 165000],
                ['TAS-PASPOR', 40000],
            ],
        ];

        foreach ($vendorItems as $vendorCode => $itemsForVendor) {
            foreach ($itemsForVendor as [$itemCode, $referencePrice]) {
                VendorItem::updateOrCreate(
                    [
                        'vendor_id' => $vendors[$vendorCode]->id,
                        'item_id' => ProcurementItem::query()->where('code', $itemCode)->value('id'),
                    ],
                    [
                        'reference_price' => $referencePrice,
                        'currency' => 'IDR',
                        'is_active' => true,
                    ],
                );
            }
        }
        DepartureBatch::updateOrCreate(['code' => 'UMR-2026-01'], ['name' => 'Umroh Januari 2026', 'departure_date' => '2026-01-15', 'return_date' => '2026-01-27', 'capacity' => 45, 'status' => 'closed', 'is_active' => true]);
        DepartureBatch::updateOrCreate(['code' => 'UMR-2026-02'], ['name' => 'Umroh Februari 2026', 'departure_date' => '2026-02-12', 'return_date' => '2026-02-24', 'capacity' => 50, 'status' => 'open', 'is_active' => true]);
    }
}
