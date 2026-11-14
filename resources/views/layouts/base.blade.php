<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>{{ $title ?? 'Skill Matching' }} — Skill Matching</title><link rel="stylesheet" href="{{ asset('css/skill-matching.css') }}"><link rel="stylesheet" href="{{ asset('css/figma-improvements.css') }}"><link rel="stylesheet" href="{{ asset('css/figma-alignment.css') }}"><link rel="stylesheet" href="{{ asset('css/journey.css') }}?v={{ filemtime(public_path('css/journey.css')) }}"></head>
<body>
@yield('content')
<script>

 document.querySelectorAll('.alert-success').forEach(el=>setTimeout(()=>el.classList.add('faded'),6000));
</script>
<script src="{{ asset('js/data-controls.js') }}" defer></script>
<script src="{{ asset('js/experience.js') }}" defer></script><script src="{{ asset('js/conditional-fields.js') }}" defer></script><script src="{{ asset('js/ui-consistency.js') }}" defer></script></body></html>
