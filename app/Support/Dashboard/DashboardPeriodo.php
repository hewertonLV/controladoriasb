<?php

namespace App\Support\Dashboard;

use Illuminate\Support\Carbon;

final class DashboardPeriodo
{
    public const TIPO_MES = 'mes';

    public const TIPO_DIA = 'dia';

    public readonly Carbon $inicio;

    public readonly Carbon $fim;

    public readonly string $mes;

    public readonly string $tipo;

    public readonly ?string $dia;

    public readonly string $label;

    private function __construct(
        Carbon $inicio,
        Carbon $fim,
        string $mes,
        string $tipo,
        ?string $dia,
        string $label,
    ) {
        $this->inicio = $inicio;
        $this->fim = $fim;
        $this->mes = $mes;
        $this->tipo = $tipo;
        $this->dia = $dia;
        $this->label = $label;
    }

    public static function resolver(?string $mes = null, ?string $dia = null): self
    {
        $diaCarbon = self::parseDia($dia);
        if ($diaCarbon !== null) {
            return self::fromDia($diaCarbon);
        }

        return self::fromMes($mes);
    }

    /**
     * @return array{inicio: string, fim: string, mes: string, tipo: string, dia: string|null, label: string}
     */
    public function toArray(): array
    {
        return [
            'inicio' => $this->inicio->toDateString(),
            'fim' => $this->fim->toDateString(),
            'mes' => $this->mes,
            'tipo' => $this->tipo,
            'dia' => $this->dia,
            'label' => $this->label,
        ];
    }

    private static function fromDia(Carbon $dia): self
    {
        $inicio = $dia->copy()->startOfDay();
        $fim = $dia->copy()->endOfDay();

        return new self(
            $inicio,
            $fim,
            $inicio->format('Y-m'),
            self::TIPO_DIA,
            $inicio->toDateString(),
            $dia->translatedFormat('d/m/Y'),
        );
    }

    private static function fromMes(?string $mes): self
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

        return new self(
            $inicio,
            $fim,
            $inicio->format('Y-m'),
            self::TIPO_MES,
            null,
            $label,
        );
    }

    private static function parseMes(string $mes): Carbon
    {
        $normalizado = trim($mes);

        if (preg_match('/^\d{4}-\d{2}$/', $normalizado) !== 1) {
            return now()->startOfMonth();
        }

        return Carbon::createFromFormat('Y-m', $normalizado)->startOfMonth();
    }

    private static function parseDia(?string $dia): ?Carbon
    {
        if ($dia === null) {
            return null;
        }

        $normalizado = trim($dia);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalizado) !== 1) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $normalizado)->startOfDay();
    }
}
