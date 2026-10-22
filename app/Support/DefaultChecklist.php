<?php

namespace App\Support;

use App\Models\ChecklistItem;
use App\Models\ChecklistVersion;

/** Checklist P2H kendaraan LV (sama dengan layar P2H di aplikasi mobile AKPSatu). [grup => [[label, kritis], ...]] */
class DefaultChecklist
{
    public const LV = [
        'DOKUMEN' => [
            ['STNK & kartu izin masuk tambang', false],
            ['SIMPER pengemudi berlaku', true],
        ],
        'EKSTERIOR' => [
            ['Ban & tekanan angin', true],
            ['Lampu utama, sein, rem', true],
            ['Kaca & wiper', false],
            ['Bendera & lampu rotary', true],
        ],
        'MESIN' => [
            ['Oli mesin', false],
            ['Air radiator', false],
            ['Tidak ada kebocoran oli / air', false],
        ],
        'KESELAMATAN' => [
            ['Rem & rem tangan', true],
            ['Sabuk pengaman', true],
            ['APAR terisi & tidak kedaluwarsa', true],
            ['Klakson & alarm mundur', true],
            ['Kotak P3K', false],
            ['Radio komunikasi', true],
            ['Ganjal roda (wheel chock)', false],
        ],
    ];

    /** Membuat versi aktif bila kategori ini belum punya checklist apa pun. */
    public static function seed(string $category, string $name): ?ChecklistVersion
    {
        if (ChecklistVersion::where('category', $category)->exists()) {
            return null;
        }

        $version = ChecklistVersion::create([
            'category' => $category, 'name' => $name, 'is_active' => true, 'activated_at' => now(), 'created_by_name' => 'Bawaan sistem',
        ]);

        $order = 0;
        foreach (self::LV as $group => $items) {
            foreach ($items as [$label, $critical]) {
                ChecklistItem::create([
                    'checklist_version_id' => $version->id, 'group_name' => $group, 'label' => $label,
                    'is_critical' => $critical, 'sort_order' => ++$order,
                ]);
            }
        }

        return $version;
    }
}
