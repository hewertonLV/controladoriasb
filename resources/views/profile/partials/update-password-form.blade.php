<form method="post" action="{{ route('password.update') }}">
    @csrf
    @method('put')

    <div class="mb-3">
        <label for="update_password_current_password" class="form-label">Senha atual</label>
        <input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password"
               class="form-control @error('current_password', 'updatePassword') is-invalid @enderror">
        @error('current_password', 'updatePassword')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="update_password_password" class="form-label">Nova senha</label>
        <input id="update_password_password" name="password" type="password" autocomplete="new-password"
               class="form-control @error('password', 'updatePassword') is-invalid @enderror">
        @error('password', 'updatePassword')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="update_password_password_confirmation" class="form-label">Confirmar nova senha</label>
        <input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
               class="form-control @error('password_confirmation', 'updatePassword') is-invalid @enderror">
        @error('password_confirmation', 'updatePassword')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="d-flex align-items-center gap-3">
        <button type="submit" class="btn btn-primary">Salvar</button>

        @if (session('status') === 'password-updated')
            <span class="text-muted small">Senha atualizada.</span>
        @endif
    </div>
</form>
