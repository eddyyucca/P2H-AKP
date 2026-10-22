<a href="{{ $item['href'] }}" class="sidebar-link {{ $item['active'] ? 'active' : '' }}">
    <span class="sidebar-link-icon"><i class="{{ $item['icon'] ?: 'bi bi-circle' }}"></i></span>
    <span class="sidebar-link-text">{{ $item['title'] }}</span>
    @if (! empty($item['badge']))<span class="badge rounded-pill bg-warning text-dark ms-auto">{{ $item['badge'] }}</span>@endif
</a>
