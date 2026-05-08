<?php declare(strict_types=1);

namespace Grodtech\Ecf;

/**
 * Marcadores sustituidos en servidor por ECF GRODTECH (documentación API).
 * Use estas constantes al armar plantillas JSON para no equivocarse en el nombre.
 */
final class StandardPlaceholders
{
    public const RNC = 'RNC';
    public const RAZON_SOCIAL = 'RAZON_SOCIAL';
    public const NOMBRE_COMERCIAL = 'NOMBRE_COMERCIAL';
    public const DIRECCION_EMISOR = 'DIRECCION_EMISOR';
    public const FECHA_EMISION = 'FECHA_EMISION';
    public const FECHA_LIMITE_PAGO = 'FECHA_LIMITE_PAGO';
    public const FECHA_VENC_SEQ = 'FECHA_VENC_SEQ';
    public const NCF_MODIFICADO = 'NCF_MODIFICADO';
    public const CODIGO_SEGURIDAD_RFCE = 'CODIGO_SEGURIDAD_RFCE';
    public const FECHA_GENERACION_ISO = 'FECHA_GENERACION_ISO';

    public static function wrap(string $key): string
    {
        return '{{' . $key . '}}';
    }
}
