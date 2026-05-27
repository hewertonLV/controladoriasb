<?php

namespace App\Services\Captacao;

use App\Models\Captacao\CaptacaoLote;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class ArmazenarNfTransferenciaCiganLoteService
{
    public function executar(CaptacaoLote $lote, UploadedFile $arquivo, User $user): CaptacaoLote
    {
        if ($lote->arquivo_nf_transferencia_path !== null) {
            Storage::disk('local')->delete($lote->arquivo_nf_transferencia_path);
        }

        $extensao = strtolower($arquivo->getClientOriginalExtension() ?: $arquivo->extension() ?: 'bin');
        $path = sprintf(
            'captacao/cigan/nf-transferencia/lote-%d-%s.%s',
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
            'arquivo_nf_transferencia_path' => $path,
            'arquivo_nf_transferencia_nome' => $arquivo->getClientOriginalName(),
            'nf_transferencia_enviada_em' => now(),
            'nf_transferencia_user_id' => $user->id,
        ]);
        $lote->save();

        return $lote->fresh();
    }
}
