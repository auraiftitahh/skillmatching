@props(['type' => 'info'])
<div {{ $attributes->class(['alert', 'alert-success' => $type === 'success', 'alert-error' => $type === 'error']) }} role="{{ $type === 'error' ? 'alert' : 'status' }}">
    <x-icon :name="$type === 'success' ? 'check' : 'info'" />
    <div>{{ $slot }}</div>
</div>
