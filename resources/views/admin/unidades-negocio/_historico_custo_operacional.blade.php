@php
    /** @var \App\Models\UnidadeNegocio $unidadeNegocio */
@endphp

<div class="card mt-4">
    <div class="card-header">
        <h4 class="header-title mb-0">Histórico de custo operacional</h4>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-centered table-hover mb-0">
                <thead class="bg-light bg-opacity-50">
                    <tr>
                        <th>Custo</th>
                        <th>Vigência</th>
                        <th>Criado em</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($unidadeNegocio->historicosCustoOperacional as $h)
                        <tr class="{{ $h->status_position ? '' : 'text-muted bg-light bg-opacity-25' }}">
                            <td><code>{{ number_format((float) $h->custo_operacional, 2, ',', '.') }}</code></td>
                            <td>
                                @if ($h->status_position)
                                    <span class="badge bg-success-subtle text-success">
                                        <i class="ri-checkbox-circle-line me-1"></i> Vigente
                                    </span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary">
                                        <i class="ri-time-line me-1"></i> Histórico
                                    </span>
                                @endif
                            </td>
                            <td>{{ optional($h->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center text-muted py-4">
                                Nenhum histórico registrado para esta unidade.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

