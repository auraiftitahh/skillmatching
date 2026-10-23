@props(['name' => 'home'])
@php
    $paths = [
        'home' => 'm3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z',
        'user' => 'M20 21v-2a6 6 0 0 0-6-6h-4a6 6 0 0 0-6 6v2 M16 6a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
        'history' => 'M3 11a9 9 0 1 1 2 7 M3 3v8h8 M12 7v5l3 2',
        'spark' => 'm12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5Z',
        'briefcase' => 'M8 7V4h8v3 M3 7h18v14H3Z M3 12h18 M10 12v3h4v-3',
        'book' => 'M12 5C9 3 5 3 2 4v16c4-1 7-1 10 1 3-2 6-2 10-1V4c-3-1-7-1-10 1Z M12 5v16',
        'list' => 'M9 5h12 M9 12h12 M9 19h12 M3 5h1 M3 12h1 M3 19h1',
        'chart' => 'M3 3v18h18 M7 15l4-5 4 3 6-8',
        'badge' => 'm12 3 3 2 4 1 1 4-1 4-3 2-4 2-4-2-3-2-1-4 1-4 4-1Z m-4 8 3 3 5-6',
        'settings' => 'M4 6h16 M4 12h16 M4 18h16 M8 3v6 M16 9v6 M10 15v6',
        'arrow' => 'm14 6-6 6 6 6 M8 12h13',
        'menu' => 'M3 6h18 M3 12h18 M3 18h18',
        'check' => 'm5 12 4 4L19 6',
        'info' => 'M12 11v6 M12 7h.01 M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',
    ];
@endphp
<svg {{ $attributes->class(['icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="{{ $paths[$name] ?? $paths['home'] }}" /></svg>
