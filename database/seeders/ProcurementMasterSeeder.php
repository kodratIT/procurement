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
        foreach ([['PAKAIAN', 'Pakaian Jamaah'], ['IBADAH', 'Perlengkapan Ibadah'], ['KESEHATAN', 'Kesehatan'], ['PERJALANAN', 'Perlengkapan Perjalanan']] as [$code,$name]) {
            $categories[$code] = ProcurementCategory::updateOrCreate(['code' => $code], ['name' => $name, 'is_active' => true]);
        }
        $items = [
            ['KAIN-IHRAM', 'Kain Ihram', 'PAKAIAN', 'SET', 175000, ['Bahan' => 'Katun 100%', 'Panjang' => '2,5 m', 'Jenis' => 'Tanpa jahit']],
            ['MUKENA', 'Mukena', 'IBADAH', 'PCS', 210000, ['Bahan' => 'Rayon premium', 'Ukuran' => 'All size', 'Paket' => 'Mukena + tas']],
            ['TAS-SANDANG', 'Tas Sandang', 'PERJALANAN', 'PCS', 95000, ['Bahan' => 'Kanvas', 'Kapasitas' => '5 L', 'Warna' => 'Hitam / Cokelat']],
            ['KOPER', 'Koper Jamaah', 'PERJALANAN', 'PCS', 425000, ['Bahan' => 'ABS', 'Ukuran' => '20 inci', 'Berat' => '3,2 kg']],
            ['MASKER', 'Masker Medis', 'KESEHATAN', 'BOX', 45000, ['Tipe' => '3 ply', 'Isi' => '50 pcs / box']],
            ['OBAT-PRIBADI', 'Paket Obat Dasar', 'KESEHATAN', 'PACK', 60000, ['Isi' => 'Obat masuk angin, sakit kepala, maag', 'Kemasan' => 'Pouch ziplock']],
        ];
        foreach ($items as [$code,$name,$category,$unit,$price,$specs]) {
            $item = ProcurementItem::updateOrCreate(['code' => $code], [
                'name' => $name,
                'category_id' => $categories[$category]->id,
                'unit_id' => $units[$unit]->id,
                'reference_price' => $price,
                'reference_currency' => 'IDR',
                'specifications' => $specs,
                'is_active' => true,
            ]);
            if ($code === 'KAIN-IHRAM') {
                foreach ([['S', 'Kecil'], ['M', 'Sedang'], ['L', 'Besar'], ['XL', 'Ekstra Besar']] as [$variant,$label]) {
                    ProcurementVariant::updateOrCreate(['item_id' => $item->id, 'code' => $variant], ['name' => 'Ukuran '.$label, 'value' => $variant, 'variation_type' => ProcurementVariant::TYPE_UKURAN, 'is_active' => true]);
                }
            }
            if ($code === 'MUKENA') {
                foreach ([['PUTIH', 'Putih'], ['NAVY', 'Navy'], ['ROSE', 'Rose']] as [$variant,$label]) {
                    ProcurementVariant::updateOrCreate(['item_id' => $item->id, 'code' => $variant], ['name' => 'Warna '.$label, 'value' => $label, 'variation_type' => ProcurementVariant::TYPE_WARNA, 'is_active' => true]);
                }
            }
        }
        Vendor::updateOrCreate(['code' => 'VND-UMROH-001'], ['name' => 'Al Madinah Supplies', 'contact_name' => 'Tim Sales', 'phone' => '021-555-0101', 'email' => 'sales@almadinah.example', 'is_active' => true]);
        Vendor::updateOrCreate(['code' => 'VND-UMROH-002'], ['name' => 'Nusantara Travel Gear', 'contact_name' => 'Customer Service', 'phone' => '021-555-0102', 'email' => 'order@nusantaragear.example', 'is_active' => true]);

        DepartureBatch::updateOrCreate(['code' => 'UMR-2026-01'], ['name' => 'Umroh Januari 2026', 'departure_date' => '2026-01-15', 'return_date' => '2026-01-27', 'capacity' => 45, 'status' => 'closed', 'is_active' => true]);
        DepartureBatch::updateOrCreate(['code' => 'UMR-2026-02'], ['name' => 'Umroh Februari 2026', 'departure_date' => '2026-02-12', 'return_date' => '2026-02-24', 'capacity' => 50, 'status' => 'open', 'is_active' => true]);
    }
}
