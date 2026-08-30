<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DepartureBatch;
use App\Models\ProcurementCategory;
use App\Models\ProcurementItem;
use App\Models\ProcurementUnit;
use App\Models\ProcurementVariant;
use App\Models\Vendor;
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
            ['KAIN-IHRAM', 'Kain Ihram', 'PAKAIAN', 'SET'], ['MUKENA', 'Mukena', 'IBADAH', 'PCS'],
            ['TAS-SANDANG', 'Tas Sandang', 'PERJALANAN', 'PCS'], ['KOPER', 'Koper Jamaah', 'PERJALANAN', 'PCS'],
            ['MASKER', 'Masker Medis', 'KESEHATAN', 'BOX'], ['OBAT-PRIBADI', 'Paket Obat Dasar', 'KESEHATAN', 'PACK'],
        ];
        foreach ($items as [$code,$name,$category,$unit]) {
            $item = ProcurementItem::updateOrCreate(['code' => $code], ['name' => $name, 'category_id' => $categories[$category]->id, 'unit_id' => $units[$unit]->id, 'is_active' => true]);
            if ($code === 'KAIN-IHRAM') {
                foreach ([['S', 'Kecil'], ['M', 'Sedang'], ['L', 'Besar'], ['XL', 'Ekstra Besar']] as [$variant,$label]) {
                    ProcurementVariant::updateOrCreate(['item_id' => $item->id, 'code' => $variant], ['name' => 'Ukuran '.$label, 'value' => $variant, 'is_active' => true]);
                }
            }
        }
        Vendor::updateOrCreate(['code' => 'VND-UMROH-001'], ['name' => 'Al Madinah Supplies', 'contact_name' => 'Tim Sales', 'phone' => '021-555-0101', 'email' => 'sales@almadinah.example', 'is_active' => true]);
        Vendor::updateOrCreate(['code' => 'VND-UMROH-002'], ['name' => 'Nusantara Travel Gear', 'contact_name' => 'Customer Service', 'phone' => '021-555-0102', 'email' => 'order@nusantaragear.example', 'is_active' => true]);
        DepartureBatch::updateOrCreate(['code' => 'UMR-2026-01'], ['name' => 'Umroh Januari 2026', 'departure_date' => '2026-01-15', 'return_date' => '2026-01-27', 'capacity' => 45, 'status' => 'closed', 'is_active' => true]);
        DepartureBatch::updateOrCreate(['code' => 'UMR-2026-02'], ['name' => 'Umroh Februari 2026', 'departure_date' => '2026-02-12', 'return_date' => '2026-02-24', 'capacity' => 50, 'status' => 'open', 'is_active' => true]);
    }
}
