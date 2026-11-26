<?php

namespace App\Enums;

enum StatusBolsa: string
{
    case EmTransito = 'em_transito';
    case Disponivel = 'disponivel';
    case Reservada = 'reservada';
    case Utilizada = 'utilizada';
    case Descartada = 'descartada';

    public function label(): string
    {
        return match ($this) {
            self::EmTransito => 'Em trânsito',
            self::Disponivel => 'Disponível',
            self::Reservada => 'Reservada',
            self::Utilizada => 'Utilizada',
            self::Descartada => 'Descartada',
        };
    }

    public function ehFinal(): bool
    {
        return in_array($this, [self::Utilizada, self::Descartada], true);
    }

    public function requerPaciente(): bool
    {
        return in_array($this, [self::Reservada, self::Utilizada], true);
    }

    /**
     * @return list<self>
     */
    public function transicoesPermitidas(bool $vencida = false): array
    {
        if ($this->ehFinal()) {
            return [];
        }

        if ($vencida) {
            return [self::Descartada];
        }

        return match ($this) {
            self::EmTransito => [self::Disponivel],
            self::Disponivel => [self::Reservada, self::Utilizada, self::Descartada],
            self::Reservada => [self::Disponivel, self::Utilizada, self::Descartada],
            self::Utilizada, self::Descartada => [],
        };
    }

    /**
     * @return list<array{valor: string, rotulo: string}>
     */
    public function statusPermitidos(bool $vencida = false): array
    {
        return array_map(
            fn (self $status): array => ['valor' => $status->value, 'rotulo' => $status->label()],
            $this->transicoesPermitidas($vencida),
        );
    }

    public function podeTransicionarPara(self $destino, bool $vencida = false): bool
    {
        return in_array($destino, $this->transicoesPermitidas($vencida), true);
    }

    public function mensagemTransicaoInvalida(self $destino, bool $vencida = false): string
    {
        if ($this->ehFinal()) {
            return 'Bolsas utilizadas ou descartadas são finais e não podem ser alteradas.';
        }

        if ($vencida && $destino !== self::Descartada) {
            return 'Bolsa vencida só pode ser descartada.';
        }

        return sprintf(
            'Transição de %s para %s não é permitida.',
            $this->label(),
            $destino->label(),
        );
    }
}
