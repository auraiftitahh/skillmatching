@php
    $filterOptions = $filterOptions ?? [];
    $searchHint = $searchHint ?? 'Cari nama atau kata kunci';
@endphp
<form class="data-filters" method="get" action="{{ url()->current() }}" role="search">
    @if(request()->filled('training_id'))<input type="hidden" name="training_id" value="{{ request('training_id') }}">@endif
    <label class="filter-search">Cari
        <input type="search" name="q" value="{{ request('q', '') }}" placeholder="{{ $searchHint }}" maxlength="100" autocomplete="off">
    </label>
    @foreach($filterOptions as $filter)
        <label>{{ $filter['label'] }}
            <select name="{{ $filter['name'] }}" aria-label="Filter {{ $filter['label'] }}">
                @foreach($filter['options'] as $value=>$text)
                    <option value="{{ $value }}" @selected((string) request($filter['name'], '') === (string)$value)>{{ $text }}</option>
                @endforeach
            </select>
        </label>
    @endforeach
    <button type="submit" class="btn primary">Terapkan</button>
    <a class="btn outline" href="{{ url()->current() }}">Atur Ulang</a>
</form>
