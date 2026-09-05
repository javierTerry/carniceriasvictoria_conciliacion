<?php

namespace App\DTOs\SiNube;

readonly class SiNubePagoResponseDTO
{
    public function __construct(
        public bool $success,
        public ?string $uuid = null,
        public ?string $xmlUrl = null,
        public ?string $pdfUrl = null,
        public ?string $serie = null,
        public ?string $folio = null,
        public ?string $mensaje = null,
        public ?string $rawResponse = null
    ) {}
}
