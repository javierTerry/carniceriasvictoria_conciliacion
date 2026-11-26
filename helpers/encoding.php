<?php
/**
 * helpers/encoding.php
 * Helper versionado en Git para normalización de codificación desde BDs POS heredadas.
 */

if (!function_exists('normalizarTextoPOS')) {
    /**
     * Normaliza textos provenientes de BDs POS heredadas (latin1_swedish_ci),
     * reparando doble codificación UTF-8 (ej. "CAÃ‘AS" -> "CAÑAS") o latin1 puro sin alterar UTF-8 válido.
     */
    function normalizarTextoPOS(?string $texto): string
    {
        if ($texto === null || $texto === '') {
            return '';
        }

        if (!mb_check_encoding($texto, 'UTF-8')) {
            $convertido = @mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
            return $convertido !== false ? $convertido : $texto;
        }

        // Detectar mojibake clásico de UTF-8 almacenado en columnas latin1/Windows-1252
        if (preg_match('/Ã[\x{0080}-\x{00BF}\x{2018}-\x{2022}\x{0152}\x{0153}\x{0160}\x{0161}\x{0178}\x{017D}\x{017E}\x{0192}\x{02C6}\x{02DC}\x{20AC}]|Â[\x{0080}-\x{00BF}]/u', $texto)) {
            $reparado = @mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
            if ($reparado !== false && $reparado !== '' && mb_check_encoding($reparado, 'UTF-8')) {
                return $reparado;
            }
        }

        return $texto;
    }
}

