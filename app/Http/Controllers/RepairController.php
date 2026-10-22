<?php

namespace App\Http\Controllers;

use App\Models\Inspection;
use Illuminate\Http\Request;

/** Antrian perbaikan: semua temuan P2H yang belum selesai, kritis dan paling lama di atas. */
class RepairController extends Controller
{
    public const STATUS_LABELS = ['none' => 'Tanpa temuan', 'open' => 'Menunggu', 'in_progress' => 'Dikerjakan', 'done' => 'Selesai'];

    public function index(Request $request)
    {
        $filter = in_array($request->query('filter'), ['menunggu', 'dikerjakan', 'terlambat', 'kritis'], true) ? $request->query('filter') : '';

        $base = fn () => Inspection::openFindings();

        $query = $base()->orderByRaw("CASE WHEN status = 'tidak_layak' THEN 0 ELSE 1 END")->oldest('inspected_at')->oldest('id');
        match ($filter) {
            'menunggu' => $query->where('follow_up_status', 'open'),
            'dikerjakan' => $query->where('follow_up_status', 'in_progress'),
            'terlambat' => $query->whereNotNull('follow_up_due')->where('follow_up_due', '<', today()->toDateString()),
            'kritis' => $query->where('status', 'tidak_layak'),
            default => null,
        };

        return view('p2h.repairs', [
            'items' => $query->paginate(25)->withQueryString(),
            'filter' => $filter,
            'counts' => [
                '' => $base()->count(),
                'menunggu' => $base()->where('follow_up_status', 'open')->count(),
                'dikerjakan' => $base()->where('follow_up_status', 'in_progress')->count(),
                'terlambat' => $base()->whereNotNull('follow_up_due')->where('follow_up_due', '<', today()->toDateString())->count(),
                'kritis' => $base()->where('status', 'tidak_layak')->count(),
            ],
        ]);
    }
}
