<?php declare(strict_types=1);

namespace Grodtech\Ecf;

/**
 * Interpreta respuestas JSON de la pasarela GRODTECH y de consultas estilo DGII (estado/codigo/mensajes).
 */
final class ResultInterpreter
{
    /**
     * Respuesta documentada: ok, queued, trackId, raw, error, http, curl_error.
     *
     * @param array<string, mixed> $json
     */
    public static function gatewayRecepcion(array $json): GatewayRecepcionResult
    {
        $ok = !empty($json['ok']);
        $queued = !empty($json['queued']);
        $trackId = trim((string) ($json['trackId'] ?? ''));
        $error = isset($json['error']) && ($json['error'] !== null && (string) $json['error'] !== '')
            ? (string) $json['error']
            : null;
        $http = isset($json['http']) ? (int) $json['http'] : null;
        $curl = isset($json['curl_error']) && $json['curl_error'] !== null && (string) $json['curl_error'] !== ''
            ? (string) $json['curl_error']
            : null;
        $raw = null;
        if (isset($json['raw'])) {
            $raw = is_array($json['raw']) ? $json['raw'] : null;
        }

        if ($curl !== null && $curl !== '') {
            $outcome = DgiiOutcome::Error;
            $summary = 'Error de transporte: ' . $curl;
        } elseif ($queued) {
            $outcome = DgiiOutcome::Pending;
            $summary = 'Contingencia: documento en cola de reintento en la pasarela.';
        } elseif ($ok) {
            $outcome = DgiiOutcome::Approved;
            $summary = $trackId !== ''
                ? 'Aceptado inicialmente por la pasarela (trackId para consulta DGII).'
                : 'Aceptado por la pasarela (sin trackId en esta respuesta).';
        } elseif ($http !== null && $http >= 500) {
            $outcome = DgiiOutcome::Error;
            $summary = $error ?? 'Error HTTP al procesar en pasarela o DGII.';
        } elseif (!$ok) {
            $outcome = DgiiOutcome::Rejected;
            $summary = $error ?? 'Rechazado o error reportado por la pasarela.';
        } else {
            $outcome = DgiiOutcome::Unknown;
            $summary = 'Respuesta no clasificada.';
        }

        return new GatewayRecepcionResult(
            $outcome,
            $ok,
            $queued,
            $trackId,
            $error,
            $http,
            $curl,
            $raw,
            $summary
        );
    }

    /**
     * Interpreta JSON de consulta de resultado DGII (ej. trackId) o cuerpo crudo para parsear.
     */
    public static function dgiiConsultaFromBody(string $body): DgiiConsultaResult
    {
        $parsed = json_decode($body, true);
        if (!is_array($parsed)) {
            return new DgiiConsultaResult(
                DgiiOutcome::Error,
                '',
                '',
                '',
                [],
                $body,
                null
            );
        }

        return self::dgiiConsultaFromArray($parsed, $body);
    }

    /**
     * @param array<string, mixed> $json
     */
    public static function dgiiConsultaFromArray(array $json, string $rawBody = ''): DgiiConsultaResult
    {
        $estado = trim((string) ($json['estado'] ?? ''));
        $codigo = trim((string) ($json['codigo'] ?? ''));
        $track = trim((string) ($json['trackId'] ?? ''));
        $mensajesTexto = self::flattenMensajes($json['mensajes'] ?? null);

        $estadoLow = mb_strtolower($estado);
        $joinedMsgs = mb_strtolower(implode(' ', $mensajesTexto));

        $outcome = DgiiOutcome::Unknown;

        if ($estadoLow !== '' && (str_contains($estadoLow, 'proces') || str_contains($estadoLow, 'pendiente'))) {
            $outcome = DgiiOutcome::Pending;
        } elseif (
            str_contains($estadoLow, 'acept')
            || str_contains($estadoLow, 'aprob')
            || str_contains($joinedMsgs, 'acept')
        ) {
            $hasWarn = self::hasNonZeroMensajes($json['mensajes'] ?? null);
            $outcome = $hasWarn ? DgiiOutcome::Partial : DgiiOutcome::Approved;
        } elseif (str_contains($estadoLow, 'rechaz') || str_contains($joinedMsgs, 'rechaz')) {
            $outcome = DgiiOutcome::Rejected;
        } elseif (
            str_contains($estadoLow, 'condicional')
            || str_contains($estadoLow, 'parcial')
            || str_contains($estadoLow, 'observ')
        ) {
            $outcome = DgiiOutcome::Partial;
        }

        if ($outcome === DgiiOutcome::Unknown && $estado === '' && $track === '' && $mensajesTexto === []) {
            $outcome = DgiiOutcome::Error;
        }

        return new DgiiConsultaResult(
            $outcome,
            $estado,
            $codigo,
            $track,
            $mensajesTexto,
            $rawBody,
            $json
        );
    }

    /**
     * @param mixed $mensajes
     * @return list<string>
     */
    private static function flattenMensajes(mixed $mensajes): array
    {
        if ($mensajes === null) {
            return [];
        }
        if (is_string($mensajes)) {
            return $mensajes !== '' ? [trim($mensajes)] : [];
        }
        if (!is_array($mensajes)) {
            return [];
        }
        $out = [];
        foreach ($mensajes as $m) {
            if (is_string($m)) {
                $out[] = trim($m);
            } elseif (is_array($m)) {
                $v = $m['valor'] ?? $m['mensaje'] ?? $m['texto'] ?? null;
                if (is_string($v) && $v !== '') {
                    $out[] = trim($v);
                } elseif (isset($m['codigo'])) {
                    $out[] = 'codigo=' . (string) $m['codigo'];
                }
            }
        }

        return array_values(array_filter($out, static fn (string $s): bool => $s !== ''));
    }

    /** @param mixed $mensajes */
    private static function hasNonZeroMensajes(mixed $mensajes): bool
    {
        if (!is_array($mensajes)) {
            return false;
        }
        foreach ($mensajes as $m) {
            if (is_array($m) && isset($m['codigo']) && (int) $m['codigo'] !== 0) {
                return true;
            }
        }

        return false;
    }
}
