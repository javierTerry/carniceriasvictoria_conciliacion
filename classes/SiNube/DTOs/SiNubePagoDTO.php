<?php

namespace App\DTOs\SiNube;

readonly class SiNubePagoDTO
{
    /**
     * @param SiNubeDocumentoRelacionadoDTO[] $documentos
     * @param string[] $uuidsRelacionados
     */
    public function __construct(
        public string $serie,
        public string $folio,
        public float $monto,
        public string $formaDePagoP,
        public string $fechaPago,
        public SiNubeReceptorDTO $receptor,
        public array $documentos,
        public array $uuidsRelacionados = [],
        public string $formatoReporte = 'CFDI 4.0 PAGO',
        public string $sistema = 'SegunRFC',
        public string $noCertificado = '',
        public string $difZonaHoraria = '-6',
        public string $nomArchivoDescarga = '',
        public string $monedaSinube = 'MXN',
        public string $tipoCambioP = '1',
        public string $numOperacion = '',
        // SPEI opcionales
        public ?string $tipoCadPago = '',
        public ?string $certPago = '',
        public ?string $cadPago = '',
        public ?string $selloPago = '',
        public ?string $ctaOrdenante = '',
        public ?string $ctaBeneficiario = '',
        public ?string $rfcEmisorCtaOrd = '',
        public ?string $nomBancoOrdExt = '',
    ) {}
}
