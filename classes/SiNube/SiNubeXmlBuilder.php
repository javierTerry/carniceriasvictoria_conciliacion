<?php

namespace App\Services\SiNube;

use App\DTOs\SiNube\SiNubePagoDTO;
use SimpleXMLElement;

class SiNubeXmlBuilder
{
    public function build(SiNubePagoDTO $dto): string
    {
        $xml = new SimpleXMLElement('<Comprobante/>');

        // Atributos Encabezado Comprobante
        $xml->addAttribute('sistema', $dto->sistema);
        $xml->addAttribute('noCertificado', $dto->noCertificado);
        $xml->addAttribute('serie', $dto->serie);
        $xml->addAttribute('folio', (string)$dto->folio);
        $xml->addAttribute('difZonaHoraria', $dto->difZonaHoraria);
        $xml->addAttribute('nomArchivoDescarga', $dto->nomArchivoDescarga);
        $xml->addAttribute('formatoReporte', $dto->formatoReporte);
        $xml->addAttribute('monto', (string)$dto->monto);
        $xml->addAttribute('monedaSinube', $dto->monedaSinube);
        $xml->addAttribute('formaDePagoP', $dto->formaDePagoP);
        $xml->addAttribute('fechaPago', $dto->fechaPago);
        $xml->addAttribute('tipoCambioP', $dto->tipoCambioP);
        $xml->addAttribute('numOperacion', $dto->numOperacion);
        
        // SPEI opcionales
        $xml->addAttribute('tipoCadPago', $dto->tipoCadPago ?? '');
        $xml->addAttribute('certPago', $dto->certPago ?? '');
        $xml->addAttribute('cadPago', $dto->cadPago ?? '');
        $xml->addAttribute('selloPago', $dto->selloPago ?? '');
        $xml->addAttribute('ctaOrdenante', $dto->ctaOrdenante ?? '');
        $xml->addAttribute('ctaBeneficiario', $dto->ctaBeneficiario ?? '');
        $xml->addAttribute('rfcEmisorCtaOrd', $dto->rfcEmisorCtaOrd ?? '');
        $xml->addAttribute('nomBancoOrdExt', $dto->nomBancoOrdExt ?? '');

        // Nodo Receptor
        $rec = $dto->receptor;
        $receptorNode = $xml->addChild('Receptor');
        $receptorNode->addAttribute('cliente', $rec->cliente);
        $receptorNode->addAttribute('rfc', mb_strtoupper(trim($rec->rfc), 'UTF-8'));
        $receptorNode->addAttribute('razonSocial', trim($rec->razonSocial));
        $receptorNode->addAttribute('esPersonaFisica', (string)$rec->esPersonaFisica);
        $receptorNode->addAttribute('nombre', $rec->nombre ?? '');
        $receptorNode->addAttribute('apellidoPaterno', $rec->apellidoPaterno ?? '');
        $receptorNode->addAttribute('apellidoMaterno', $rec->apellidoMaterno ?? '');
        $receptorNode->addAttribute('residenciaFiscal', $rec->residenciaFiscal ?? '');
        $receptorNode->addAttribute('domicilioFiscal', $rec->domicilioFiscal ?? '');
        $receptorNode->addAttribute('regimenFiscal', $rec->regimenFiscal ?? '');
        $receptorNode->addAttribute('numRegIdTrib', $rec->numRegIdTrib ?? '');

        // Nodo Documentos
        $documentosNode = $xml->addChild('Documentos');
        foreach ($dto->documentos as $doc) {
            $docNode = $documentosNode->addChild('Documento');
            $docNode->addAttribute('serie', $doc->serie ?? '');
            $docNode->addAttribute('folio', (string)($doc->folio ?? ''));
            $docNode->addAttribute('idDocumento', trim($doc->idDocumento));
            $docNode->addAttribute('monedaDR', $doc->monedaDR);
            $docNode->addAttribute('tipoCambioDR', $doc->tipoCambioDR ?? '');
            $docNode->addAttribute('metodoDePagoDR', $doc->metodoDePagoDR);
            $docNode->addAttribute('impPagado', (string)$doc->impPagado);
            $docNode->addAttribute('numParcialidad', (string)($doc->numParcialidad ?? '1'));
            $docNode->addAttribute('impSaldoAnt', (string)($doc->impSaldoAnt ?? '0'));
            $docNode->addAttribute('impSaldoInsoluto', $doc->impSaldoInsoluto !== null ? (string)$doc->impSaldoInsoluto : '');
            
            // Atributos de Impuestos (REP 2.0 en SiNube)
            $docNode->addAttribute('montoIVA', (string)$doc->montoIVA);
            $docNode->addAttribute('montoBaseIVA', (string)$doc->montoBaseIVA);
            $docNode->addAttribute('tipoIVA', (string)$doc->tipoIVA);
            $docNode->addAttribute('porcentajeIVA', (string)$doc->porcentajeIVA);
            $docNode->addAttribute('montoIEPS', (string)$doc->montoIEPS);
            $docNode->addAttribute('montoBaseIEPS', (string)$doc->montoBaseIEPS);
            $docNode->addAttribute('porcentajeCuotaIEPS', (string)$doc->porcentajeCuotaIEPS);
            $docNode->addAttribute('esPorcentajeIEPS', $doc->esPorcentajeIEPS ? 'true' : 'false');
            $docNode->addAttribute('montoRetencionIVA', (string)$doc->montoRetencionIVA);
            $docNode->addAttribute('montoBaseRetencionIVA', (string)$doc->montoBaseRetencionIVA);
            $docNode->addAttribute('porcentajeRetencionIVA', (string)$doc->porcentajeRetencionIVA);
            $docNode->addAttribute('montoRetencionISR', (string)$doc->montoRetencionISR);
            $docNode->addAttribute('montoBaseRetencionISR', (string)$doc->montoBaseRetencionISR);
            $docNode->addAttribute('porcentajeRetencionISR', (string)$doc->porcentajeRetencionISR);
        }

        // Nodo UUIDs Relacionados
        $uuids = !empty($dto->uuidsRelacionados) 
            ? $dto->uuidsRelacionados 
            : array_map(fn($d) => $d->idDocumento, $dto->documentos);

        if (!empty($uuids)) {
            $uuidsNode = $xml->addChild('UuidsRelacionados');
            foreach ($uuids as $uuid) {
                $uuidNode = $uuidsNode->addChild('UuidRelacionado');
                $uuidNode->addAttribute('Uuid', trim($uuid));
            }
        }

        return $xml->asXML();
    }
}
