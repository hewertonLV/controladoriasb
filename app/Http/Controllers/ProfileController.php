<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\Users\UserAvatarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(
        private readonly UserAvatarService $avatarService,
    ) {}
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->safe()->only(['name', 'email']);

        if ($request->boolean('remove_avatar')) {
            $this->avatarService->delete($user);
        }

        if ($request->hasFile('avatar')) {
            $this->avatarService->store($user, $request->file('avatar'));
        }

        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Exclusão de conta foi desabilitada neste sistema.
     *
     * Mantemos o método como barreira defensiva: mesmo que a rota seja
     * reativada manualmente, nenhum usuário será excluído do banco.
     */
    public function destroy(Request $request): RedirectResponse
    {
        return Redirect::route('profile.edit')
            ->with('error', 'Exclusão de conta não é permitida neste sistema.');
    }
}
