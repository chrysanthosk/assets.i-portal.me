@props(['icon' => 'bi-circle', 'label', 'value', 'sub' => null, 'tone' => '', 'href' => null])
<div class="card h-100">
    <div class="card-body">
        <div class="stat">
            <div class="stat-icon {{ $tone }}"><i class="bi {{ $icon }}"></i></div>
            <div class="min-w-0">
                <div class="stat-label">{{ $label }}</div>
                <div class="stat-value text-truncate">{{ $value }}</div>
                @if($sub)<div class="stat-sub">{!! $sub !!}</div>@endif
                @if($href)<a href="{{ $href }}" class="small stretched-link"></a>@endif
            </div>
        </div>
    </div>
</div>
