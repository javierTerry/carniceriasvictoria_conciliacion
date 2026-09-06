<?php

namespace App\Services\SiNube;

use App\DTOs\SiNube\SiNubePagoDTO;
use App\DTOs\SiNube\SiNubeReceptorDTO;
use App\DTOs\SiNube\SiNubeDocumentoRelacionadoDTO;
use InvalidArgumentException;

class SiNubePagoFactory
{
    /**
     * Construye un SiNubePagoDTO a partir de un arreglo asociativo.
     */
    public static function fromArray(array $data): SiNubePagoDTO
    {
        if (empty($data['receptor']) || !is_array($data['receptor'])) {
            throw new InvalidArgumentException('Los datos del receptor son obligatorios.');
        }

        if (empty($data['documentos']) || !is_array($data['documentos'])) {
            throw new InvalidArgumentException('Debe incluir al menos un documento relacionado.');
        }

        // 1. Instanciar Receptor DTO
        $rec = $data['receptor'];
        $esFisica = '0';
        if (isset($rec['esPersonaFisica'])) {
            $esFisica = in_array((string)$rec['esPersonaFisica'], ['1', 'true', 'TRUE'], true) ? '1' : '0';
        }

        $domicilio = !empty($rec['domicilioFiscal']) 
            ? str_pad(substr(preg_replace('/[^0-9]/', '', (string)$rec['domicilioFiscal']), 0, 5), 5, '0', STR_PAD_LEFT)
            : '';

        $receptorDTO = new SiNubeReceptorDTO(
            cliente: (string)($rec['cliente'] ?? $rec['cust_id'] ?? '1'),
            rfc: mb_strtoupper(trim((string)($rec['rfc'] ?? '')), 'UTF-8'),
            razonSocial: trim((string)($rec['razonSocial'] ?? $rec['name'] ?? '')),
            esPersonaFisica: $esFisica,
            domicilioFiscal: $domicilio,
            regimenFiscal: (string)($rec['regimenFiscal'] ?? $rec['regimen_fiscal'] ?? ''),
            nombre: (string)($rec['nombre'] ?? ''),
            apellidoPaterno: (string)($rec['apellidoPaterno'] ?? ''),
            apellidoMaterno: (string)($rec['apellidoMaterno'] ?? ''),
            residenciaFiscal: (string)($rec['residenciaFiscal'] ?? ''),
            numRegIdTrib: (string)($rec['numRegIdTrib'] ?? '')
        );

        // 2. Instanciar Documentos Relacionados DTO
        $documentosDTO = [];
        $totalCalculado = 0.0;

        foreach ($data['documentos'] as $doc) {
            $impPagado = (float)($doc['impPagado'] ?? $doc['monto_pagado'] ?? 0.0);
            $totalCalculado += $impPagado;

            $saldoAnt = isset($doc['impSaldoAnt']) ? (float)$doc['impSaldoAnt'] : (float)($doc['saldo_anterior'] ?? 0.0);
            $saldoInsoluto = isset($doc['impSaldoInsoluto']) && $doc['impSaldoInsoluto'] !== '' 
                ? (float)$doc['impSaldoInsoluto'] 
                : (isset($doc['saldo_insoluto']) ? (float)$doc['saldo_insoluto'] : null);

            $documentosDTO[] = new SiNubeDocumentoRelacionadoDTO(
                idDocumento: trim((string)($doc['idDocumento'] ?? $doc['uuid'] ?? '')),
                impPagado: $impPagado,
                monedaDR: (string)($doc['monedaDR'] ?? 'MXN'),
                metodoDePagoDR: (string)($doc['metodoDePagoDR'] ?? 'PPD'),
                serie: (string)($doc['serie'] ?? ''),
                folio: (string)($doc['folio'] ?? ''),
                tipoCambioDR: (string)($doc['tipoCambioDR'] ?? ''),
                numParcialidad: (int)($doc['numParcialidad'] ?? $doc['parcialidad'] ?? 1),
                impSaldoAnt: $saldoAnt,
                impSaldoInsoluto: $saldoInsoluto,
                montoIVA: (float)($doc['montoIVA'] ?? 0.0),
                montoBaseIVA: (float)($doc['montoBaseIVA'] ?? 0.0),
                tipoIVA: (string)($doc['tipoIVA'] ?? '1'),
                porcentajeIVA: (string)($doc['porcentajeIVA'] ?? '16'),
                montoIEPS: (float)($doc['montoIEPS'] ?? 0.0),
                montoBaseIEPS: (float)($doc['montoBaseIEPS'] ?? 0.0),
                porcentajeCuotaIEPS: (string)($doc['porcentajeCuotaIEPS'] ?? '0'),
                esPorcentajeIEPS: (bool)($doc['esPorcentajeIEPS'] ?? false),
                montoRetencionIVA: (float)($doc['montoRetencionIVA'] ?? 0.0),
                montoBaseRetencionIVA: (float)($doc['montoBaseRetencionIVA'] ?? 0.0),
                porcentajeRetencionIVA: (string)($doc['porcentajeRetencionIVA'] ?? '0'),
                montoRetencionISR: (float)($doc['montoRetencionISR'] ?? 0.0),
                montoBaseRetencionISR: (float)($doc['montoBaseRetencionISR'] ?? 0.0),
                porcentajeRetencionISR: (string)($doc['porcentajeRetencionISR'] ?? '0')
            );
        }

        $montoFinal = isset($data['monto']) ? (float)$data['monto'] : $totalCalculado;
        $serie = (string)($data['serie'] ?? 'RP');
        $folio = (string)($data['folio'] ?? '1');
        $nomDescarga = (string)($data['nomArchivoDescarga'] ?? ($data['rfcEmisor'] ?? 'CFDI') . "_{$serie}{$folio}");

        // 3. Instanciar DTO Principal de Pago
        return new SiNubePagoDTO(
            serie: $serie,
            folio: $folio,
            monto: $montoFinal,
            formaDePagoP: (string)($data['formaDePagoP'] ?? $data['forma_pago'] ?? '04'),
            fechaPago: (string)($data['fechaPago'] ?? date('Y-m-d\TH:i:s')),
            receptor: $receptorDTO,
            documentos: $documentosDTO,
            uuidsRelacionados: $data['uuidsRelacionados'] ?? [],
            formatoReporte: (string)($data['formatoReporte'] ?? 'CFDI 4.0 PAGO'),
            sistema: (string)($data['sistema'] ?? 'SegunRFC'),
            noCertificado: (string)($data['noCertificado'] ?? ''),
            difZonaHoraria: (string)($data['difZonaHoraria'] ?? '-6'),
            nomArchivoDescarga: $nomDescarga,
            monedaSinube: (string)($data['monedaSinube'] ?? 'MXN'),
            tipoCambioP: (string)($data['tipoCambioP'] ?? '1'),
            numOperacion: (string)($data['numOperacion'] ?? ''),
            tipoCadPago: $data['tipoCadPago'] ?? '',
            certPago: $data['certPago'] ?? '',
            cadPago: $data['cadPago'] ?? '',
            selloPago: $data['selloPago'] ?? '',
            ctaOrdenante: $data['ctaOrdenante'] ?? '',
            ctaBeneficiario: $data['ctaBeneficiario'] ?? '',
            rfcEmisorCtaOrd: $data['rfcEmisorCtaOrd'] ?? '',
            nomBancoOrdExt: $data['nomBancoOrdExt'] ?? ''
        );
    }
}
