<?php

namespace App\DTOs\SiNube;

readonly class SiNubeDocumentoRelacionadoDTO
{
    public function __construct(
        public string $idDocumento,
        public float $impPagado,
        public string $monedaDR = 'MXN',
        public string $metodoDePagoDR = 'PPD',
        public ?string $serie = '',
        public ?string $folio = '',
        public ?string $tipoCambioDR = '',
        public ?int $numParcialidad = 1,
        public ?float $impSaldoAnt = 0.00,
        public ?float $impSaldoInsoluto = null,

        // Atributos de Impuestos (REP 2.0 en SiNube)
        public float $montoIVA = 0.00,
        public float $montoBaseIVA = 0.00,
        public string $tipoIVA = '1',
        public string $porcentajeIVA = '16',
        public float $montoIEPS = 0.00,
        public float $montoBaseIEPS = 0.00,
        public string $porcentajeCuotaIEPS = '0',
        public bool $esPorcentajeIEPS = false,
        public float $montoRetencionIVA = 0.00,
        public float $montoBaseRetencionIVA = 0.00,
        public string $porcentajeRetencionIVA = '0',
        public float $montoRetencionISR = 0.00,
        public float $montoBaseRetencionISR = 0.00,
        public string $porcentajeRetencionISR = '0'
    ) {}
}
