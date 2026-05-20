@props([
    'user' => null,
    'size' => 42,
    'class' => '',
    'alt' => null,
])

@php
    $user = $user ?? auth()->user();
    $size = (int) $size;
    $altText = $alt ?? ('Foto de '.($user?->name ?? 'usuário'));
@endphp

@if ($user)
    <img src="{{ $user->avatar_url }}"
         width="{{ $size }}"
         height="{{ $size }}"
         class="rounded-circle object-fit-cover {{ $class }}"
         alt="{{ $altText }}"
         {{ $attributes }}>
@endif
