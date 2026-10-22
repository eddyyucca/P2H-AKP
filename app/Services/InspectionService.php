<?php

namespace App\Services;

use Akp\PortalSso\PortalUser;
use App\Models\ChecklistVersion;
use App\Models\Inspection;
use App\Models\Unit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InspectionService
{
    /** Shift siang (D) atau malam (N) menurut jam sekarang di zona waktu site. */
    public static function shiftNow(?\DateTimeInterface $at = null): string
    {
        $hour = (int) ($at ?? now())->format('G');

        return ($hour >= config('p2h.day_shift_start_hour') && $hour < config('p2h.night_shift_start_hour')) ? 'D' : 'N';
    }

    /**
     * Menyimpan satu P2H beserta jawabannya dalam satu transaksi.
     *
     * Aturan hasil: ada item KRITIS yang rusak = TIDAK LAYAK; selain itu LAYAK (item tidak kritis yang rusak tetap
     * dicatat sebagai temuan dan menunggu tindak lanjut). Nomor P2H-{UNIT}-{TANGGAL}-{SHIFT}; pemeriksaan ulang pada
     * unit, tanggal, dan shift yang sama (mis. setelah perbaikan) diberi akhiran -2, -3, dan seterusnya.
     * Dua pengiriman bersamaan yang berebut nomor yang sama diulang; ulangan membaca nomor terbaru.
     * P2H tanpa temuan menutup otomatis temuan terbuka milik unit yang sama.
     *
     * @param  array{shift: string, odometer: ?int, fuel_level: ?string, notes: ?string, answers: array<int, string>, answer_notes: array<int, ?string>, photos?: array<int, UploadedFile>}  $data
     */
    public function submit(PortalUser $user, Unit $unit, ChecklistVersion $version, array $data): Inspection
    {
        $attempts = 5;
        for ($try = 1; ; $try++) {
            try {
                return DB::transaction(fn () => $this->store($user, $unit, $version, $data));
            } catch (UniqueConstraintViolationException $e) {
                if ($try >= $attempts) {
                    throw $e;
                }
            }
        }
    }

    private function store(PortalUser $user, Unit $unit, ChecklistVersion $version, array $data): Inspection
    {
        $items = $version->items()->get();
        $answers = [];
        $bad = 0;
        $criticalBad = 0;

        foreach ($items as $item) {
            $result = $data['answers'][$item->id] ?? null;
            $isBad = $result === 'bad';
            $bad += $isBad ? 1 : 0;
            $criticalBad += ($isBad && $item->is_critical) ? 1 : 0;

            $photo = $isBad ? ($data['photos'][$item->id] ?? null) : null;

            $answers[] = [
                'group_name' => $item->group_name, 'label' => $item->label, 'is_critical' => $item->is_critical,
                'result' => $isBad ? 'bad' : 'ok',
                'note' => $isBad ? trim((string) ($data['answer_notes'][$item->id] ?? '')) : null,
                'photo_path' => $photo instanceof UploadedFile ? $this->storePhoto($photo) : null,
                'sort_order' => $item->sort_order,
            ];
        }

        $at = now();
        $base = 'P2H-'.preg_replace('/[^A-Z0-9]/', '', strtoupper($unit->code)).'-'.$at->format('Ymd').'-'.$data['shift'];
        $number = $base;
        for ($n = 2; Inspection::where('number', $number)->exists(); $n++) {
            $number = $base.'-'.$n;
        }

        $inspection = Inspection::create([
            'number' => $number,
            'unit_id' => $unit->id, 'unit_code' => $unit->code, 'unit_name' => $unit->name, 'unit_plate' => $unit->plate,
            'checklist_version_id' => $version->id, 'checklist_version_name' => $version->name,
            'inspected_at' => $at, 'shift' => $data['shift'],
            'odometer' => $data['odometer'] ?? null, 'fuel_level' => $data['fuel_level'] ?? null,
            'status' => $criticalBad > 0 ? 'tidak_layak' : 'layak',
            'bad_count' => $bad, 'critical_bad_count' => $criticalBad,
            'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
            'declared_at' => ! empty($data['declared']) ? $at : null,
            'driver_portal_id' => (string) $user->id, 'driver_name' => $user->name, 'driver_nik' => $user->employee_no,
            'follow_up_status' => $bad > 0 ? 'open' : 'none',
        ]);

        $inspection->answers()->createMany($answers);

        if ($bad === 0) {
            app(FollowUpService::class)->autoResolveFor($inspection);
        }

        return $inspection;
    }

    /** Menyimpan foto kerusakan di disk privat; dikecilkan dan dikompres bila ekstensi GD tersedia. */
    private function storePhoto(UploadedFile $file): string
    {
        $disk = Storage::disk(config('p2h.photo_disk'));
        $name = 'p2h/photos/'.now()->format('Y/m').'/'.bin2hex(random_bytes(12)).'.jpg';

        $jpeg = $this->compress($file);
        $disk->put($name, $jpeg ?? $file->get());

        return $name;
    }

    private function compress(UploadedFile $file): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring($file->get());
        if (! $image) {
            return null;
        }

        $max = (int) config('p2h.photo_max_px');
        [$w, $h] = [imagesx($image), imagesy($image)];
        if (max($w, $h) > $max) {
            $scale = $max / max($w, $h);
            $image = imagescale($image, (int) round($w * $scale), (int) round($h * $scale)) ?: $image;
        }

        ob_start();
        imagejpeg($image, null, 80);

        return ob_get_clean() ?: null;
    }
}
