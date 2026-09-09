@props(['icon' => 'bi-circle', 'label', 'value', 'sub' => null, 'subHtml' => null, 'tone' => '', 'href' => null])
<div class="card h-100">
    <div class="card-body">
        <div class="stat">
            <div class="stat-icon {{ $tone }}"><i class="bi {{ $icon }}" aria-hidden="true"></i></div>
            <div class="min-w-0">
                <div class="stat-label">{{ $label }}</div>
                <div class="stat-value">{{ $value }}</div>
                @if($subHtml)<div class="stat-sub">{!! $subHtml !!}</div>@elseif($sub)<div class="stat-sub">{{ $sub }}</div>@endif
                @if($href)<a href="{{ $href }}" class="stretched-link"><span class="visually-hidden">Open {{ $label }}</span></a>@endif
            </div>
        </div>
    </div>
</div>
