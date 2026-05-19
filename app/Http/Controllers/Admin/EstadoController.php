<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEstadoRequest;
use App\Http\Requests\Admin\UpdateEstadoRequest;
use App\Models\Estado;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EstadoController extends Controller
{
    public function index(): View
    {
        $estados = Estado::query()
            ->withTrashed()
            ->withCount(['unidadesNegocio', 'fornecedores', 'frutasIcms'])
            ->orderBy('nome')
            ->orderBy('id')
            ->get();

        return view('admin.estados.index', [
            'estados' => $estados,
        ]);
    }

    public function create(): View
    {
        return view('admin.estados.create', [
            'estado' => new Estado,
        ]);
    }

    public function store(StoreEstadoRequest $request): RedirectResponse
    {
        $estado = Estado::query()->create($request->validated());

        return redirect()
            ->route('admin.estados.index')
            ->with('success', "Estado \"{$estado->nome}\" ({$estado->abreviacao}) cadastrado com sucesso.");
    }

    public function edit(Estado $estado): View
    {
        return view('admin.estados.edit', [
            'estado' => $estado,
        ]);
    }

    public function update(UpdateEstadoRequest $request, Estado $estado): RedirectResponse
    {
        $estado->update($request->validated());

        return redirect()
            ->route('admin.estados.index')
            ->with('success', "Estado \"{$estado->nome}\" atualizado com sucesso.");
    }

    public function inativar(Estado $estado): RedirectResponse
    {
        if ($estado->trashed()) {
            return redirect()
                ->route('admin.estados.index')
                ->with('info', "Estado \"{$estado->nome}\" já estava inativo.");
        }

        if ($estado->possuiVinculosAtivos()) {
            return redirect()
                ->route('admin.estados.index')
                ->with('error', "Não é possível inativar \"{$estado->nome}\": existem unidades, fornecedores ou configurações de ICMS vinculados.");
        }

        $estado->delete();

        return redirect()
            ->route('admin.estados.index')
            ->with('success', "Estado \"{$estado->nome}\" inativado com sucesso.");
    }

    public function reativar(Estado $estado): RedirectResponse
    {
        if (! $estado->trashed()) {
            return redirect()
                ->route('admin.estados.index')
                ->with('info', "Estado \"{$estado->nome}\" já estava ativo.");
        }

        $estado->restore();

        return redirect()
            ->route('admin.estados.index')
            ->with('success', "Estado \"{$estado->nome}\" reativado com sucesso.");
    }
}
