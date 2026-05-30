@props([
    'placeholder' => 'Selecione ou pesquise…',
])

<select {{ $attributes->merge([
    'data-search-select' => true,
    'data-placeholder' => $placeholder,
]) }}>
    {{ $slot }}
</select>

@once
    @push('scripts')
        @include('partials.admin.search-select-init')
    @endpush
@endonce
