<label class="form-label">Foto de perfil</label>

<div class="d-flex flex-wrap align-items-center gap-3 mb-3">
    <x-user-avatar :user="$user" :size="96" class="border" id="profile-avatar-preview" />
    <div class="flex-grow-1 min-w-0">
        <input type="file"
               id="avatar"
               name="avatar"
               accept="image/jpeg,image/png,image/webp"
               class="form-control @error('avatar', 'updateProfileInformation') is-invalid @enderror">
        <div class="form-text">Escolha uma imagem (JPG, PNG ou WebP). Você poderá recortar em quadrado; o sistema reduz a qualidade automaticamente para até 2 MB.</div>
        @error('avatar', 'updateProfileInformation')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
</div>

@if ($user->avatar_path)
    <div class="form-check mb-4">
        <input type="checkbox"
               class="form-check-input"
               id="remove_avatar"
               name="remove_avatar"
               value="1"
               @checked(old('remove_avatar'))>
        <label class="form-check-label" for="remove_avatar">Remover foto atual</label>
    </div>
@endif
