<?php declare(strict_types=1);

namespace Grodtech\Ecf;

/**
 * Vista unificada de la respuesta JSON de POST /fe/recepcion/api/ecf (y análogos).
 *
 * @phpstan-type RawShape array<string, mixed>|null
 */
final class GatewayRecepcionResult
{
    /**
     * @param RawShape $raw
     */
    public function __construct(
        public readonly DgiiOutcome $outcome,
        public readonly bool $ok,
        public readonly bool $queued,
        public readonly string $trackId,
        public readonly ?string $error,
        public readonly ?int $http,
        public readonly ?string $curlError,
        public readonly array|null $raw,
        public readonly string $summary
    ) {
    }
}
