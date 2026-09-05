<?php

namespace App\DTOs\SiNube;

readonly class SiNubeReceptorDTO
{
    public function __construct(
        public string $cliente,
        public string $rfc,
        public string $razonSocial,
        public string $esPersonaFisica = '0',
        public ?string $domicilioFiscal = '',
        public ?string $regimenFiscal = '',
        public ?string $nombre = '',
        public ?string $apellidoPaterno = '',
        public ?string $apellidoMaterno = '',
        public ?string $residenciaFiscal = '',
        public ?string $numRegIdTrib = ''
    ) {}
}
