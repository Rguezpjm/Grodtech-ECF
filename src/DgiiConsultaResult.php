<?php declare(strict_types=1);

namespace Grodtech\Ecf;

/**
 * Resultado de consulta tipo `consultaresultado` / trackId (respuesta DGII).
 *
 * @phpstan-type MensajeShape array{valor?: string, codigo?: int|string}|mixed
 */
final class DgiiConsultaResult
{
    /**
     * @param list<string|array<string, mixed>> $mensajesTexto
     */
    public function __construct(
        public readonly DgiiOutcome $outcome,
        public readonly string $estado,
        public readonly string $codigo,
        public readonly string $trackId,
        public readonly array $mensajesTexto,
        public readonly string $rawBody,
        /** JSON decodificado cuando fue posible */
        public readonly array|null $parsed
    ) {
    }
}
