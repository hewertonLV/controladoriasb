<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForcePasswordChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ForcePasswordChangeController extends Controller
{
    /**
     * Exibe o formulário de troca obrigatória de senha.
     */
    public function show(Request $request): View|RedirectResponse
    {
        if (! (bool) $request->user()->must_change_password) {
            return redirect()->route('modulos.index');
        }

        return view('auth.force-password-change');
    }

    /**
     * Persiste a nova senha e libera o acesso ao restante do sistema.
     */
    public function update(ForcePasswordChangeRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make($request->validated()['password']),
            'must_change_password' => false,
        ])->save();

        $request->session()->regenerate();

        return redirect()
            ->route('modulos.index')
            ->with('success', 'Senha alterada com sucesso. Bem-vindo(a)!');
    }
}
