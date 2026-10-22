{{--
    Sidebar mengikuti pola HRIS: Dashboard tunggal di atas, lalu grup yang bisa dilipat.
    Grup yang berisi halaman aktif terbuka; grup lain tertutup.
--}}
@php
    $u = portal_user();
    $canFill = (bool) $u?->hasRole('admin', 'pengawas', 'operator');
    $sees = (bool) $u?->hasRole('admin', 'pengawas', 'viewer');

    $item = fn (string $title, string $icon, string $route, array $match) => [
        'title' => $title, 'icon' => $icon, 'href' => route($route), 'active' => request()->routeIs(...$match),
    ];

    $openCount = $sees ? \App\Models\Inspection::openFindings()->count() : 0;

    $menus = [
        $item('Dashboard', 'bi bi-grid-1x2-fill', 'p2h.home', ['p2h.home']),
        [
            'title' => 'P2H',
            'children' => array_values(array_filter([
                $canFill ? $item('Isi P2H', 'bi bi-clipboard2-check', 'p2h.fill', ['p2h.fill']) : null,
                $item($sees ? 'Riwayat P2H' : 'P2H Saya', 'bi bi-journal-text', 'p2h.index', ['p2h.index', 'p2h.show', 'p2h.print']),
                $sees ? $item('Antrian Perbaikan', 'bi bi-tools', 'p2h.repairs', ['p2h.repairs']) + ['badge' => $openCount] : null,
                $sees ? $item('Laporan', 'bi bi-file-earmark-bar-graph', 'p2h.report', ['p2h.report*']) : null,
            ])),
        ],
    ];

    if ($sees) {
        $menus[] = [
            'title' => 'Data Master',
            'children' => [
                $item('Unit', 'bi bi-truck-front', 'p2h.units', ['p2h.units*']),
                $item('Checklist', 'bi bi-ui-checks', 'p2h.checklist', ['p2h.checklist*']),
            ],
        ];
    }

    foreach ($menus as &$menu) {
        if (isset($menu['children'])) {
            $menu['active'] = collect($menu['children'])->contains('active', true);
        }
    }
    unset($menu);
@endphp

<div class="sidebar-section">
    @foreach ($menus as $menu)
        @if (isset($menu['children']))
            <div class="sidebar-group mt-2">
                <div class="sidebar-group-toggle {{ $menu['active'] ? 'has-active' : 'collapsed' }}" data-sidebar-toggle>
                    <span>{{ $menu['title'] }}</span>
                    <i class="bi bi-chevron-down sidebar-chevron"></i>
                </div>
                <div class="sidebar-collapse-content {{ $menu['active'] ? '' : 'closed' }}">
                    <div class="sidebar-collapse-inner">
                        @foreach ($menu['children'] as $child)
                            @include('partials.sidebar-link', ['item' => $child])
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            @include('partials.sidebar-link', ['item' => $menu])
        @endif
    @endforeach
</div>
