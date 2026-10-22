<?php

namespace App\Http\Controllers;

use App\Models\ChecklistItem;
use App\Models\ChecklistVersion;
use Illuminate\Http\Request;

/** Checklist P2H berversi. Perubahan hanya berlaku untuk P2H berikutnya; riwayat menyimpan salinan isiannya sendiri. */
class ChecklistController extends Controller
{
    public function index(Request $request)
    {
        $versions = ChecklistVersion::withCount('items')->orderBy('category')->latest('id')->get();

        $selected = $versions->firstWhere('id', (int) $request->query('versi'))
            ?? $versions->firstWhere('is_active', true)
            ?? $versions->first();

        return view('p2h.checklist', [
            'versions' => $versions,
            'selected' => $selected,
            'groups' => $selected ? $selected->items()->get()->groupBy('group_name') : collect(),
            'canEdit' => portal_user()->hasRole('admin'),
        ]);
    }

    /** Versi baru: salinan versi yang dipilih (nonaktif), atau checklist kosong untuk kategori baru. */
    public function storeVersion(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'category' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9][A-Za-z0-9 \-_]*$/'],
            'copy_from' => ['nullable', 'integer', 'exists:checklist_versions,id'],
        ], ['name.required' => 'Nama versi wajib diisi.', 'category.regex' => 'Kategori hanya boleh huruf, angka, spasi, dan strip.']);

        $category = strtoupper(trim($data['category']));
        $by = portal_user()->name;

        $source = filled($data['copy_from'] ?? null) ? ChecklistVersion::find($data['copy_from']) : null;
        $version = $source && $source->category === $category
            ? $source->duplicate(trim($data['name']), $by)
            : ChecklistVersion::create(['category' => $category, 'name' => trim($data['name']), 'is_active' => false, 'created_by_name' => $by]);

        return redirect()->route('p2h.checklist', ['versi' => $version->id])->with('success', 'Versi dibuat (belum aktif). Sunting itemnya, lalu aktifkan.');
    }

    public function activate(ChecklistVersion $version)
    {
        if ($version->items()->count() === 0) {
            return back()->with('error', 'Versi tanpa item tidak bisa diaktifkan.');
        }

        $version->activate();

        return redirect()->route('p2h.checklist', ['versi' => $version->id])->with('success', "Versi \"{$version->name}\" kini dipakai untuk P2H kategori {$version->category}.");
    }

    private function itemRules(): array
    {
        return [
            'group_name' => ['required', 'string', 'max:60'],
            'label' => ['required', 'string', 'max:200'],
            'is_critical' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function storeItem(Request $request, ChecklistVersion $version)
    {
        $data = $request->validate($this->itemRules(), ['group_name.required' => 'Grup wajib diisi.', 'label.required' => 'Nama item wajib diisi.']);

        $version->items()->create([
            'group_name' => strtoupper(trim($data['group_name'])),
            'label' => trim($data['label']),
            'is_critical' => $request->boolean('is_critical'),
            'sort_order' => $data['sort_order'] ?? (($version->items()->max('sort_order') ?? 0) + 1),
        ]);

        return redirect()->route('p2h.checklist', ['versi' => $version->id])->with('success', 'Item ditambahkan.');
    }

    public function updateItem(Request $request, ChecklistItem $item)
    {
        $data = $request->validate($this->itemRules());

        $item->update([
            'group_name' => strtoupper(trim($data['group_name'])),
            'label' => trim($data['label']),
            'is_critical' => $request->boolean('is_critical'),
            'sort_order' => $data['sort_order'] ?? $item->sort_order,
        ]);

        return redirect()->route('p2h.checklist', ['versi' => $item->checklist_version_id])->with('success', 'Item diperbarui.');
    }

    public function destroyItem(ChecklistItem $item)
    {
        $version = $item->version;

        if ($version->is_active && $version->items()->count() <= 1) {
            return back()->with('error', 'Versi aktif harus punya minimal satu item.');
        }

        $item->delete();

        return redirect()->route('p2h.checklist', ['versi' => $version->id])->with('success', 'Item dihapus.');
    }
}
