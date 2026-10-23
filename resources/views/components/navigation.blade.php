@props(['items', 'label' => 'Navigasi utama'])
<nav aria-label="{{ $label }}" class="navigation">
    @foreach ($items as $item)
        <a href="{{ $item['url'] }}" @class(['nav-link', 'is-active' => $item['active'] ?? false]) @if ($item['active'] ?? false) aria-current="page" @endif>
            <x-icon :name="$item['icon'] ?? 'home'" /><span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
