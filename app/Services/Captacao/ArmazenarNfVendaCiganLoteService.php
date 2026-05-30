<?php

namespace App\Services\Captacao;

use App\Models\Captacao\CaptacaoLote;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class ArmazenarNfVendaCiganLoteService
{
    public function executar(CaptacaoLote $lote, UploadedFile $arquivo, User $user): CaptacaoLote
    {
        if ($lote->arquivo_nf_venda_path !== null) {
            Storage::disk('local')->delete($lote->arquivo_nf_venda_path);
        }

        $extensao = strtolower($arquivo->getClientOriginalExtension() ?: $arquivo->extension() ?: 'bin');
        $path = sprintf(
            'captacao/cigan/nf-venda/lote-%d-%s.%s',
            $lote->id,
            now()->format('YmdHis'),
            $extensao,
        );

        Storage::disk('local')->putFileAs(
            dirname($path),
            $arquivo,
            basename($path),
        );

        $lote->fill([
            'arquivo_nf_venda_path' => $path,
            'arquivo_nf_venda_nome' => $arquivo->getClientOriginalName(),
            'nf_venda_enviada_em' => now(),
            'nf_venda_user_id' => $user->id,
        ]);
        $lote->save();

        return $lote->fresh();
    }
}
