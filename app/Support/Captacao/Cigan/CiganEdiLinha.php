<?php

namespace App\Support\Captacao\Cigan;

/**
 * Linha de largura fixa do EDI NF Cigam (posições 1-based do manual).
 */
final class CiganEdiLinha
{
    private string $buffer;

    public function __construct(private readonly int $comprimento)
    {
        $this->buffer = str_repeat(' ', $comprimento);
    }

    public function colocar(int $inicio, int $fim, string $valor, bool $numerico = false): self
    {
        $tamanho = $fim - $inicio + 1;
        $valor = $this->normalizar($valor, $tamanho, $numerico);

        return $this->escrever($inicio, $tamanho, $valor);
    }

    /**
     * Grava o valor sem trim — necessário para campos com espaços significativos à esquerda (ex.: série «  001»).
     */
    public function colocarExato(int $inicio, int $fim, string $valor): self
    {
        $tamanho = $fim - $inicio + 1;
        if (strlen($valor) > $tamanho) {
            $valor = substr($valor, 0, $tamanho);
        }
        $valor = str_pad($valor, $tamanho, ' ', STR_PAD_RIGHT);

        return $this->escrever($inicio, $tamanho, $valor);
    }

    private function escrever(int $inicio, int $tamanho, string $valor): self
    {
        if (strlen($valor) > $tamanho) {
            $valor = substr($valor, 0, $tamanho);
        }

        $offset = $inicio - 1;
        $base = str_pad(substr($this->buffer, 0, $this->comprimento), $this->comprimento, ' ', STR_PAD_RIGHT);
        $cauda = substr($base, $offset + $tamanho);
        $this->buffer = str_pad(substr($base, 0, $offset).$valor.$cauda, $this->comprimento, ' ', STR_PAD_RIGHT);

        return $this;
    }

    public function linha(): string
    {
        return str_pad(substr($this->buffer, 0, $this->comprimento), $this->comprimento, ' ', STR_PAD_RIGHT);
    }

    private function normalizar(string $valor, int $tamanho, bool $numerico): string
    {
        $valor = mb_strtoupper(trim($valor), 'UTF-8');

        if ($numerico) {
            $digitos = preg_replace('/\D/', '', $valor) ?? '';

            return str_pad(substr($digitos, -$tamanho), $tamanho, '0', STR_PAD_LEFT);
        }

        return CiganEdiEncoding::textoLatin1($valor, $tamanho);
    }
}
