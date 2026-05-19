@props([
    'id',
    'name',
    'label' => null,
    'required' => false,
    'autocomplete' => null,
    'placeholder' => null,
    'value' => null,
    'errorBag' => null,
    'wrapperClass' => 'mb-3',
])

@php
    $inputId = $id ?? $name;
    $errorField = $name;
@endphp

<div class="{{ $wrapperClass }}">
    @if ($label)
        <label class="form-label" for="{{ $inputId }}">{!! $label !!}</label>
    @endif

    <div class="input-group">
        <input type="password"
               id="{{ $inputId }}"
               name="{{ $name }}"
               @if ($required) required @endif
               @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
               @if ($placeholder) placeholder="{{ $placeholder }}" @endif
               @if ($value !== null) value="{{ $value }}" @endif
               {{ $attributes->class([
                   'form-control',
                   'is-invalid' => $errorBag ? $errors->getBag($errorBag)->has($errorField) : $errors->has($errorField),
               ]) }}>

        <button type="button"
                class="btn btn-outline-secondary"
                data-password-toggle
                data-target="#{{ $inputId }}"
                aria-label="Mostrar senha"
                aria-pressed="false">
            <i class="ri-eye-line" data-icon-show></i>
            <i class="ri-eye-off-line d-none" data-icon-hide></i>
        </button>
    </div>

    @if ($errorBag)
        @error($errorField, $errorBag)
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    @else
        @error($errorField)
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    @endif
</div>
