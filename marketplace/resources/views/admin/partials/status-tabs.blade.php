{{-- $route, $current, $tabs: [değer => etiket] --}}
<div class="mb-4 flex flex-wrap gap-2">
    @foreach ($tabs as $value => $label)
        <a href="{{ route($route, array_filter(['durum' => $value])) }}" @class(['chip', 'border-ink bg-ink text-canvas' => (string) $current === (string) $value, 'hover:text-ink' => (string) $current !== (string) $value])>{{ $label }}</a>
    @endforeach
</div>
