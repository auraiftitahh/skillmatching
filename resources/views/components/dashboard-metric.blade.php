@props(['label','value','url','action'=>'Lihat detail'])
<article class="card dashboard-metric"><h2>{{ $label }}</h2><strong>{{ $value }}</strong><a href="{{ $url }}">{{ $action }} →</a></article>
