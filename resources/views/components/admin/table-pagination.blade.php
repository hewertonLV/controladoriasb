@props([
    'paginator',
    'onEachSide' => 1,
])

@php
    $isPaginator = $paginator instanceof \Illuminate\Contracts\Pagination\Paginator;
@endphp

@if ($isPaginator && $paginator->hasPages())
    <div {{ $attributes->merge(['class' => 'admin-table-pagination']) }}>
        {{ $paginator->onEachSide($onEachSide)->links() }}
    </div>
@endif
