@props([
    'label',
    'sort',
    'filtros' => [],
    'class' => '',
])

@php
    $currentSort = (string) ($filtros['sort'] ?? '');
    $currentDirection = (string) ($filtros['direction'] ?? 'asc');
    $isActive = $currentSort === $sort;
    $nextDirection = $isActive && $currentDirection === 'asc' ? 'desc' : 'asc';
    $icon = $isActive
        ? ($currentDirection === 'asc' ? 'ri-arrow-up-line' : 'ri-arrow-down-line')
        : 'ri-arrow-up-down-line';

    $query = array_filter([
        'search' => $filtros['search'] ?? null,
        'per_page' => $filtros['per_page'] ?? null,
        'status' => $filtros['status'] ?? null,
        'sort' => $sort,
        'direction' => $nextDirection,
    ], fn ($value) => $value !== null && $value !== '');
@endphp

<th class="sortable-th {{ $isActive ? 'is-active' : '' }} {{ $class }}"
    aria-sort="{{ $isActive ? ($currentDirection === 'asc' ? 'ascending' : 'descending') : 'none' }}">
    <a href="{{ request()->url() . '?' . http_build_query($query) }}"
       data-table-link
       title="Ordenar por {{ $label }} ({{ $nextDirection === 'asc' ? 'crescente' : 'decrescente' }})">
        <span>{{ $label }}</span>
        <i class="{{ $icon }} sort-icon" aria-hidden="true"></i>
    </a>
</th>
