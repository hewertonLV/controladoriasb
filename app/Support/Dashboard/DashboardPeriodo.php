<?php

namespace App\Support\Dashboard;

use Illuminate\Support\Carbon;

final class DashboardPeriodo
{
    public readonly Carbon $inicio;

    public readonly Carbon $fim;

    public readonly string $mes;

    public readonly string $label;

    private function __construct(Carbon $inicio, Carbon $fim, string $mes, string $label)
    {
        $this->inicio = $inicio;
        $this->fim = $fim;
        $this->mes = $mes;
        $this->label = $label;
    }

    public static function resolver(?string $mes = null): self
    {
        $referencia = self::parseMes($mes ?? now()->format('Y-m'));

        $inicio = $referencia->copy()->startOfMonth()->startOfDay();
        $fim = $referencia->isSameMonth(now())
            ? now()->endOfDay()
            : $referencia->copy()->endOfMonth()->endOfDay();

        $label = $inicio->translatedFormat('F/Y');
        if ($referencia->isSameMonth(now())) {
            $label .= ' (01 a '.$fim->format('d/m').')';
        } else {
            $label .= ' (01 a '.$referencia->copy()->endOfMonth()->format('d/m').')';
        }

        return new self($inicio, $fim, $inicio->format('Y-m'), $label);
    }

    /**
     * @return array{inicio: string, fim: string, mes: string, label: string}
     */
    public function toArray(): array
    {
        return [
            'inicio' => $this->inicio->toDateString(),
            'fim' => $this->fim->toDateString(),
            'mes' => $this->mes,
            'label' => $this->label,
        ];
    }

    private static function parseMes(string $mes): Carbon
    {
        $normalizado = trim($mes);

        if (preg_match('/^\d{4}-\d{2}$/', $normalizado) !== 1) {
            return now()->startOfMonth();
        }

        return Carbon::createFromFormat('Y-m', $normalizado)->startOfMonth();
    }
}
