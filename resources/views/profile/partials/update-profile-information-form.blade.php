<form id="send-verification" method="post" action="{{ route('verification.send') }}">
    @csrf
</form>

<form method="post" action="{{ route('profile.update') }}">
    @csrf
    @method('patch')

    <div class="mb-3">
        <label for="name" class="form-label">Nome</label>
        <input id="name" name="name" type="text" required autofocus autocomplete="name"
               value="{{ old('name', $user->name) }}"
               class="form-control @error('name', 'updateProfileInformation') is-invalid @enderror">
        @error('name', 'updateProfileInformation')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="email" class="form-label">E-mail</label>
        <input id="email" name="email" type="email" required autocomplete="username"
               value="{{ old('email', $user->email) }}"
               class="form-control @error('email', 'updateProfileInformation') is-invalid @enderror">
        @error('email', 'updateProfileInformation')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && is_null($user->email_verified_at))
            <div class="mt-2 text-warning small">
                Seu e-mail ainda não foi verificado.
                <button form="send-verification" class="btn btn-link p-0 align-baseline">Clique aqui para reenviar o e-mail de verificação.</button>
            </div>

            @if (session('status') === 'verification-link-sent')
                <div class="mt-2 text-success small">
                    Um novo link de verificação foi enviado para seu e-mail.
                </div>
            @endif
        @endif
    </div>

    <div class="d-flex align-items-center gap-3">
        <button type="submit" class="btn btn-primary">Salvar</button>

        @if (session('status') === 'profile-updated')
            <span class="text-muted small">Atualizado.</span>
        @endif
    </div>
</form>
