<?php declare(strict_types=1);

namespace Grodtech\Ecf;

/**
 * Resultado normalizado para UI y lógica de negocio (alineado a textos habituales DGII / pasarela).
 */
enum DgiiOutcome: string
{
    /** Aceptado / aprobado / sin observaciones críticas */
    case Approved = 'approved';

    /** Rechazado explícito por DGII o error de negocio */
    case Rejected = 'rejected';

    /**
     * Aceptado con observaciones, condicional, o mensajes de validación no bloqueantes según codigo/mensajes.
     */
    case Partial = 'partial';

    /** Fallo de transporte, HTTP no exitoso, cuerpo inválido, autenticación API, etc. */
    case Error = 'error';

    /** Encolado en pasarela o "En proceso" en consulta DGII */
    case Pending = 'pending';

    case Unknown = 'unknown';

    /** Etiqueta corta para pantallas (Español). */
    public function labelEs(): string
    {
        return match ($this) {
            self::Approved => 'Aprobado',
            self::Rejected => 'Rechazado',
            self::Partial => 'Parcial / condicional',
            self::Error => 'Error',
            self::Pending => 'En proceso',
            self::Unknown => 'Desconocido',
        };
    }
}
