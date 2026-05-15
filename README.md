# grodtech/ecf-php

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg)](https://www.php.net/)
[![License: Proprietary](https://img.shields.io/badge/license-proprietary-red.svg)](LICENSE)
[![PSR-4 Autoloading](https://img.shields.io/badge/autoload-PSR--4-brightgreen.svg)](https://www.php-fig.org/psr/psr-4/)
[![Zero runtime dependencies](https://img.shields.io/badge/dependencies-0-success.svg)](composer.json)

SDK PHP oficial para consumir la pasarela **ECF GRODTECH** (facturación electrónica DGII República Dominicana).

El SDK se encarga de:

- abrir la conexión HTTPS contra la pasarela con TLS estricto,
- enviar el comprobante (en JSON o XML),
- mantener la API Key fuera de los logs,
- normalizar la respuesta heterogénea de DGII a un enum `DgiiOutcome`.

> **Solo servidor.** Nunca incruste la API Key en aplicaciones front‑end (navegador, móvil, escritorio) ni en repositorios públicos.

---

## Índice

- [Cómo encaja en tu arquitectura](#cómo-encaja-en-tu-arquitectura)
- [Requisitos](#requisitos)
- [Instalación](#instalación)
- [Uso](#uso)
- [API del SDK](#api-del-sdk)
- [Estados (`DgiiOutcome`)](#estados-dgiioutcome)
- [Endpoints cubiertos](#endpoints-cubiertos)
- [Entornos disponibles](#entornos-disponibles)
- [Seguridad](#seguridad)
- [Manejo de errores](#manejo-de-errores)
- [Buenas prácticas](#buenas-prácticas)
- [Pruebas](#pruebas)
- [FAQ](#faq)
- [Soporte](#soporte)
- [Licencia](#licencia)

---

## Cómo encaja en tu arquitectura

```
┌──────────────┐    JSON / XML       ┌─────────────────────┐    XMLDSig firmado     ┌──────────────┐
│ Tu backend   │  ─────────────────► │ ECF GRODTECH (CRM) │ ─────────────────────► │     DGII     │
│ (este SDK)   │ ◄───────────────── │  (firma con .p12)   │ ◄───────────────────── │ (recepción)  │
└──────────────┘   trackId / raw     └─────────────────────┘    trackId             └──────────────┘
       Bearer <API Key>                                          Bearer <token DGII>
       configurada en el portal                                  emitido por DGII al CRM
```

- **Tu backend autentica al CRM con la API Key** que generaste en `https://ecf.grodtech.com`. La API Key viaja en el header `Authorization: Bearer …`.
- **El CRM autentica con DGII por su cuenta**: usa el certificado `.p12` que cargaste en el portal y obtiene el token DGII vía el flujo `semilla → validacioncertificado`. Tu sistema **nunca** ve ese token.
- **Tu sistema nunca firma localmente**: el CRM firma cada XML con tu `.p12` antes de reenviarlo a DGII.

---

## Requisitos

- PHP **8.1** o superior
- Extensiones PHP: `json`, `curl`
- Cuenta activa en [ecf.grodtech.com](https://ecf.grodtech.com) con:
  - RNC autorizado como emisor electrónico ante DGII
  - Certificado `.p12` cargado en el portal
  - **API Key** generada desde el panel

---

## Instalación

El paquete se distribuye dentro del repositorio de la aplicación, como **path repository** de Composer. En el `composer.json` de tu proyecto:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "packages/grodtech-ecf-php"
    }
  ],
  "require": {
    "grodtech/ecf-php": "@dev"
  }
}
```

```bash
composer update grodtech/ecf-php
```

> Si más adelante el paquete se publica en un Git privado (GitHub, GitLab, Bitbucket), basta con cambiar el bloque `repositories` a `"type": "vcs"` apuntando a la URL del repo y declarar la versión deseada.

---

## Uso

### 1. Enviar un e‑CF en JSON

El SDK acepta el mismo árbol JSON que documenta `DOCUMENTACION_API_ECF_GRODTECH.txt` — raíz `ECF` para los tipos 31, 32, 33, 34, 41, 43, 44, 45, 46 y 47, raíz `RFCE` para el Resumen de Factura de Consumo (Tipo 32 < RD$250 000).

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Grodtech\Ecf\Client;
use Grodtech\Ecf\ResultInterpreter;

$client = new Client(
    'https://ecf.grodtech.com/certeCF',
    getenv('ECF_GRODTECH_API_KEY') ?: throw new RuntimeException('Falta ECF_GRODTECH_API_KEY')
);

$payload = [
    'ECF' => [
        'Encabezado' => [
            'Version' => '1.0',
            'IdDoc' => [
                'TipoeCF' => 31,
                'eNCF' => 'E310000000001',
                'FechaVencimientoSecuencia' => '31-12-2028',
                'IndicadorMontoGravado' => '0',
                'TipoIngresos' => '05',
                'TipoPago' => '2',
                'FechaLimitePago' => '10-06-2026',
            ],
            'Emisor' => [
                'RNCEmisor' => '{{RNC}}',
                'RazonSocialEmisor' => '{{RAZON_SOCIAL}}',
                'DireccionEmisor' => '{{DIRECCION_EMISOR}}',
                'FechaEmision' => '{{FECHA_EMISION}}',
            ],
            'Comprador' => [
                'RNCComprador' => '101023122',
                'RazonSocialComprador' => 'INVERSIONES CARIBE EXPRESS SRL',
            ],
            'Totales' => [
                'MontoGravadoTotal' => 1000,
                'MontoGravadoI1' => 1000,
                'ITBIS1' => 18,
                'TotalITBIS' => 180,
                'MontoTotal' => 1180,
            ],
        ],
        'DetallesItems' => [
            'Item' => [[
                'NumeroLinea' => '1',
                'IndicadorFacturacion' => '1',
                'NombreItem' => 'Producto demo',
                'IndicadorBienoServicio' => '1',
                'CantidadItem' => 1,
                'UnidadMedida' => '43',
                'PrecioUnitarioItem' => 1000,
                'MontoItem' => 1000,
            ]],
        ],
        'FechaHoraFirma' => '',
    ],
];

$response = $client->recepcionJson($payload);
$result = ResultInterpreter::gatewayRecepcion($response);

echo 'Estado : ' . $result->outcome->labelEs() . PHP_EOL;
echo 'TrackId: ' . $result->trackId . PHP_EOL;
echo 'Detalle: ' . $result->summary . PHP_EOL;
```

> Los placeholders `{{RNC}}`, `{{RAZON_SOCIAL}}`, `{{DIRECCION_EMISOR}}` y `{{FECHA_EMISION}}` los reemplaza el CRM con los datos del contribuyente registrado en el portal — si los actualiza ahí, los siguientes envíos los reflejan sin tocar código. Ver sección 9 del `DOCUMENTACION_API_ECF_GRODTECH.txt` para la lista completa.

### 2. Enviar un Resumen Factura de Consumo (Tipo 32 < RD$250 000)

```php
$payloadRfce = [
    'RFCE' => [
        'Encabezado' => [
            'Version' => '1.0',
            'IdDoc' => [
                'TipoeCF' => 32,
                'eNCF' => 'E320000000050',
                'TipoIngresos' => '01',
                'TipoPago' => '1',
                'TablaFormasPago' => [
                    'FormaDePago' => [['FormaPago' => '1', 'MontoPago' => 1475.00]],
                ],
            ],
            'Emisor' => [
                'RNCEmisor' => '{{RNC}}',
                'RazonSocialEmisor' => '{{RAZON_SOCIAL}}',
                'FechaEmision' => '{{FECHA_EMISION}}',
            ],
            'Totales' => [
                'MontoGravadoTotal' => 1250.00,
                'MontoGravadoI1' => 1250.00,
                'TotalITBIS' => 225.00,
                'TotalITBIS1' => 225.00,
                'MontoTotal' => 1475.00,
            ],
            'CodigoSeguridadeCF' => '{{CODIGO_SEGURIDAD_RFCE}}',
        ],
        'FechaGeneracion' => '{{FECHA_GENERACION_ISO}}',
    ],
];

$response = $client->recepcionJson($payloadRfce);
```

### 3. Previsualizar el XML antes de enviarlo

`JsonToXml::documentFromArray()` produce el XML sin firma — útil para validarlo offline contra el XSD oficial DGII v1.0 antes del POST.

```php
use Grodtech\Ecf\JsonToXml;

$built = JsonToXml::documentFromArray($payload);
if ($built['ok']) {
    file_put_contents('preview_E310000000001.xml', $built['xml']);
} else {
    fwrite(STDERR, "JSON inválido: {$built['error']}\n");
}
```

### 4. Enviar XML ya firmado por tu propio sistema

Si tu sistema firma localmente con su propio `.p12` (caso avanzado), envía el XML directamente:

```php
$xmlFirmado = file_get_contents('comprobante_firmado.xml');
$response = $client->recepcionXml($xmlFirmado);
$result = ResultInterpreter::gatewayRecepcion($response);
```

### 5. Aprobación Comercial Electrónica (ACECF)

Cuando tu sistema acepta o rechaza un e-CF recibido de un proveedor, envía el ACECF firmado:

```php
$xmlAprobacion = file_get_contents('aprobacion_comercial.xml');
$response = $client->aprobacionComercialXml($xmlAprobacion);
$result = ResultInterpreter::gatewayRecepcion($response);
```

### 6. Interpretar la consulta posterior a DGII

Cuando consultes el estado por `trackId` directamente al endpoint `consultaresultado` de DGII, la respuesta cruda se normaliza así:

```php
use Grodtech\Ecf\ResultInterpreter;

$bodyJson = '{"trackId":"abc-123","estado":"Aceptado","codigo":"0","mensajes":[]}';
$consulta = ResultInterpreter::dgiiConsultaFromBody($bodyJson);

echo $consulta->outcome->labelEs() . PHP_EOL;
foreach ($consulta->mensajesTexto as $msg) {
    echo "  · {$msg}" . PHP_EOL;
}
```

---

## API del SDK

### `Grodtech\Ecf\Client`

```php
public function __construct(
    string $baseUrl,
    string $apiKey,
    int    $timeoutSeconds        = 120,
    int    $connectTimeoutSeconds = 10,
    bool   $allowInsecureHttp     = false
);

public function recepcionJson(array $document): array;
public function recepcionXml(string $xml): array;
public function aprobacionComercialXml(string $xml): array;
```

**Validaciones del constructor** (lanzan `\InvalidArgumentException`):

- `baseUrl` no vacío y bien formado
- `baseUrl` debe usar HTTPS (a menos que `allowInsecureHttp = true`, reservado para tests locales)
- `apiKey` no vacío
- timeouts positivos

### `Grodtech\Ecf\JsonToXml`

```php
public static function applyPlaceholders(mixed &$node, array $placeholders): void;
public static function documentFromArray(array $data): array;  // ['ok','xml','is_rfce','error'?]
public static function ecfToUnsignedXml(array $ecfRoot): string;
public static function ensureFechaHoraFirmaPlaceholder(array &$data): void;
public static function ensureFechaGeneracionRfce(array &$data): void;
```

### `Grodtech\Ecf\ResultInterpreter`

```php
public static function gatewayRecepcion(array $json): GatewayRecepcionResult;
public static function dgiiConsultaFromBody(string $body): DgiiConsultaResult;
public static function dgiiConsultaFromArray(array $json, string $rawBody = ''): DgiiConsultaResult;
```

### `Grodtech\Ecf\StandardPlaceholders`

Constantes para no equivocarse al construir plantillas dinámicas:

```php
StandardPlaceholders::RNC;
StandardPlaceholders::RAZON_SOCIAL;
StandardPlaceholders::NOMBRE_COMERCIAL;
StandardPlaceholders::DIRECCION_EMISOR;
StandardPlaceholders::FECHA_EMISION;
StandardPlaceholders::FECHA_LIMITE_PAGO;
StandardPlaceholders::FECHA_VENC_SEQ;
StandardPlaceholders::NCF_MODIFICADO;
StandardPlaceholders::CODIGO_SEGURIDAD_RFCE;
StandardPlaceholders::FECHA_GENERACION_ISO;

StandardPlaceholders::wrap(StandardPlaceholders::RNC); // '{{RNC}}'
```

### `Grodtech\Ecf\GatewayRecepcionResult` (DTO inmutable)

| Propiedad     | Tipo                  | Descripción                                              |
| ------------- | --------------------- | -------------------------------------------------------- |
| `outcome`     | `DgiiOutcome`         | Estado normalizado                                       |
| `ok`          | `bool`                | `true` si la pasarela aceptó                             |
| `queued`      | `bool`                | `true` si se encoló por contingencia                     |
| `trackId`     | `string`              | ID DGII para `consultaresultado`                         |
| `error`       | `?string`             | Mensaje de error si lo hubo                              |
| `http`        | `?int`                | Código HTTP devuelto                                     |
| `curlError`   | `?string`             | Error de transporte cURL                                 |
| `raw`         | `?array`              | Cuerpo crudo de DGII (auditoría)                         |
| `summary`     | `string`              | Texto humano-legible                                     |

### `Grodtech\Ecf\DgiiConsultaResult` (DTO inmutable)

| Propiedad        | Tipo            | Descripción                              |
| ---------------- | --------------- | ---------------------------------------- |
| `outcome`        | `DgiiOutcome`   | Estado normalizado                       |
| `estado`         | `string`        | `estado` crudo de DGII                   |
| `codigo`         | `string`        | `codigo` crudo de DGII                   |
| `trackId`        | `string`        | TrackId asociado                         |
| `mensajesTexto`  | `list<string>`  | Mensajes aplanados de DGII               |
| `rawBody`        | `string`        | Cuerpo crudo (texto)                     |
| `parsed`         | `?array`        | JSON decodificado cuando fue posible     |

---

## Estados (`DgiiOutcome`)

| Valor       | `labelEs()`            | Significado típico                                                        |
| ----------- | ---------------------- | ------------------------------------------------------------------------- |
| `approved`  | Aprobado               | Aceptado / aprobado por la pasarela o DGII                                |
| `rejected`  | Rechazado              | Rechazo explícito por DGII o error de negocio                             |
| `partial`   | Parcial / condicional  | Aceptado con observaciones (códigos ≠ 0 en `mensajes`)                    |
| `pending`   | En proceso             | Encolado por contingencia (`queued: true`) o "En proceso" en consulta DGII |
| `error`     | Error                  | Fallo de transporte, HTTP 5xx, cuerpo inválido, autenticación API         |
| `unknown`   | Desconocido            | No clasificable; revisa `raw` / `parsed` manualmente                      |

---

## Endpoints cubiertos

| Método del SDK             | Ruta HTTP                              | Descripción                                  |
| -------------------------- | -------------------------------------- | -------------------------------------------- |
| `recepcionJson(array)`     | `POST /fe/recepcion/api/ecf`           | Envía e-CF / RFCE en JSON                    |
| `recepcionXml(string)`     | `POST /fe/recepcion/api/ecf`           | Envía XML pre-generado                       |
| `aprobacionComercialXml`   | `POST /fe/aprobacioncomercial/api/ecf` | Aprobación Comercial Electrónica (ACECF)     |

La verificación de conectividad y de la API Key se hace con un `GET` simple a `/fe/autenticacion/api/autenticacion` (no expuesto como método del SDK por ser trivial — un `curl` o `file_get_contents` con el header `Authorization: Bearer …` basta).

---

## Entornos disponibles

| Entorno    | URL base                                         | Uso                                    |
| ---------- | ------------------------------------------------ | -------------------------------------- |
| `TestCF`   | `https://ecf.grodtech.com/TestCF`                | Desarrollo y pruebas iniciales         |
| `CerteCF`  | `https://ecf.grodtech.com/certeCF`               | Set de pruebas oficial DGII            |
| `eCF`      | `https://ecf.grodtech.com/eCF`                   | **Producción** (facturación oficial)   |

> Cambiar de entorno = simplemente cambiar la `baseUrl` que pasa al `Client`. La pasarela detecta el entorno y enruta a la DGII correspondiente.

---

## Seguridad

Esta sección describe el **modelo de amenazas** del SDK y las contramedidas integradas. Si descubre una vulnerabilidad, repórtela según [SECURITY.md](SECURITY.md).

### Contramedidas implementadas

| Riesgo                                                | Mitigación                                                                                          |
| ----------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| **Man-in-the-middle (MITM)**                          | `CURLOPT_SSL_VERIFYPEER = true`, `CURLOPT_SSL_VERIFYHOST = 2`, TLS 1.2+ obligatorio                 |
| **Downgrade a HTTP**                                  | El constructor rechaza `baseUrl` no-HTTPS salvo `allowInsecureHttp` (reservado para tests locales)  |
| **SSRF / esquemas exóticos**                          | `CURLOPT_PROTOCOLS` y `CURLOPT_REDIR_PROTOCOLS` restringidos a HTTP/HTTPS — bloquea `file://`, `gopher://`, `ftp://`, etc. |
| **Open redirect malicioso**                           | `CURLOPT_MAXREDIRS = 3` y solo HTTPS en redirecciones                                               |
| **Fuga de API Key en `var_dump` / logs**              | `__debugInfo()` redacta la API Key como `*** REDACTED ***`                                          |
| **Fuga de API Key en mensajes de error de transporte**| Los mensajes de error solo contienen el error de cURL, nunca los headers enviados                   |
| **Inyección XML al convertir JSON → XML**             | `JsonToXml` valida nombres de tags con regex y escapa valores con `htmlspecialchars(ENT_XML1)`      |
| **Negociación de protocolo HTTP/0.9 (CVE-2021-22945)**| User-Agent fijo, Content-Type explícito, header `Expect:` deshabilitado para evitar 100-continue    |
| **Hangs / deadlocks**                                 | `CURLOPT_TIMEOUT` (total) + `CURLOPT_CONNECTTIMEOUT` (conexión) configurables, defaults razonables  |
| **Deserialización insegura**                          | El SDK NO usa `unserialize()`. Solo `json_decode($body, true)` (devuelve arrays, no objetos)        |

### Lo que el SDK NO hace (responsabilidad del integrador)

- **Almacenar la API Key**: úsela desde variables de entorno o un secret manager. Nunca la commitee.
- **Rate limiting**: implemente throttling en su lado.
- **Reintentos automáticos**: el SDK no reintenta por sí solo. Si quiere reintentar ante 5xx o `curl_error`, hágalo con jitter exponencial en su capa.
- **Persistir auditoría**: la pasarela ya conserva el XML firmado, el PDF y la respuesta DGII por 10 años (descargables desde el panel). Aun así, persistir `trackId` y `outcome` en su sistema agiliza la conciliación contable.
- **Generar el `eNCF`**: la pasarela no asigna secuencias e-NCF; eso lo hace su sistema con las series autorizadas por DGII.
- **Firmar el XML localmente**: la pasarela firma con el `.p12` que usted cargó en el portal. Si firma localmente, use `recepcionXml()`.

### Reglas de oro

1. **NUNCA** ponga la API Key en JavaScript del navegador, en apps móviles, ni en repos públicos.
2. **NUNCA** desactive la verificación TLS. Este SDK no lo permite, y por buena razón.
3. **NUNCA** pase HTTP en `baseUrl` para producción. El SDK lo rechaza con `InvalidArgumentException`.
4. **NUNCA** logee el cuerpo completo del request (puede contener PII de clientes finales). Logee `trackId` y `outcome`.
5. **NUNCA** acepte input directo del usuario en el `eNCF` o `RNC` sin validar (riesgo de abuso de su cuota DGII).
6. **SIEMPRE** rote la API Key si sospecha exposición; hágalo desde el panel ECF GRODTECH.
7. **SIEMPRE** valide la respuesta con `ResultInterpreter` antes de marcar internamente el documento como “aceptado”.

### Cifrado de la API Key en disco

Si por requisito operacional necesita guardar la API Key en su servidor, ciérrela con permisos restrictivos:

```bash
chmod 600 /etc/grodtech/api.key
chown www-data:www-data /etc/grodtech/api.key
```

Lo recomendado es leerla desde una variable de entorno gestionada por el secret manager de su plataforma (Vault, AWS Secrets Manager, Doppler, GCP Secret Manager, etc.) y nunca commitearla.

---

## Manejo de errores

### Errores HTTP que devuelve la pasarela

| Código | Significado                                                          |
| ------ | -------------------------------------------------------------------- |
| `200`  | OK — verifique `ok`, `queued`, `trackId` en el cuerpo                |
| `401`  | API Key ausente, incorrecta o revocada                               |
| `403`  | Servicio suspendido (revise pago / plan)                             |
| `404`  | Empresa no encontrada (API Key huérfana)                             |
| `415`  | Cuerpo XML/JSON no válido en la recepción                            |
| `422`  | XML rechazado por validación XSD (ver `xsd_errors`) o no se pudo firmar |
| `502`  | Fallo de autenticación con DGII desde la pasarela                    |
| `503`  | Falta el certificado `.p12` o no es válido                           |

### Patrón de manejo recomendado

```php
$response = $client->recepcionJson($payload);
$result = ResultInterpreter::gatewayRecepcion($response);

match ($result->outcome) {
    DgiiOutcome::Approved => $this->markApproved($encf, $result->trackId, $result->raw),
    DgiiOutcome::Pending  => $this->markQueuedForRetry($encf, $result->trackId),
    DgiiOutcome::Partial  => $this->markWithObservations($encf, $result->trackId, $result->raw),
    DgiiOutcome::Rejected => $this->markRejected($encf, $result->error, $result->raw),
    DgiiOutcome::Error    => $this->scheduleRetry($encf, $result->http, $result->curlError),
    DgiiOutcome::Unknown  => $this->logForManualReview($encf, $result->raw),
};
```

---

## Buenas prácticas

1. **Idempotencia del `eNCF`**: nunca reenvíe el mismo `eNCF` en flujos lógicos distintos. La DGII rechazará por “secuencia ya utilizada”.
2. **Aproveche `TestCF` y `CerteCF`** antes de pasar a `eCF`. Cambiar de entorno solo requiere cambiar la `baseUrl` del `Client`.
3. **Persistencia de auditoría**: la pasarela ya retiene el XML firmado y el PDF por 10 años; usted persista al menos `trackId` y `outcome` en su sistema para conciliación.
4. **Validación previa**: use `JsonToXml::documentFromArray()` para verificar que su JSON produce un XML estructurado antes de enviarlo.
5. **Order-aware**: respete el orden de las claves en el JSON. El XSD DGII v1.0 es estricto en `IdDoc`, `Emisor`, `Totales`, etc.
6. **Timeouts realistas**: si su flujo es síncrono (web request), considere bajar `timeoutSeconds` a 30–60 y reintente en background con `curlError`.
7. **Contingencia activada en el panel**: si la DGII tiene un outage, sus envíos quedarán encolados (`queued: true`) y se reintentarán automáticamente.

---

## Pruebas

El paquete está diseñado para ser fácil de testear:

```php
use PHPUnit\Framework\TestCase;
use Grodtech\Ecf\ResultInterpreter;
use Grodtech\Ecf\DgiiOutcome;

final class ResultInterpreterTest extends TestCase
{
    public function testApprovedOutcome(): void
    {
        $json = ['ok' => true, 'trackId' => 'abc-123'];
        $result = ResultInterpreter::gatewayRecepcion($json);

        $this->assertSame(DgiiOutcome::Approved, $result->outcome);
        $this->assertSame('abc-123', $result->trackId);
    }

    public function testRejectedOutcome(): void
    {
        $json = ['ok' => false, 'error' => 'eNCF inválido', 'http' => 200];
        $result = ResultInterpreter::gatewayRecepcion($json);

        $this->assertSame(DgiiOutcome::Rejected, $result->outcome);
    }
}
```

Para tests de integración, mocke `Client` con una clase que implemente la misma firma pública.

---

## FAQ

**¿Necesito firmar el XML yo mismo?**
No. La pasarela firma con el `.p12` cargado en su portal. Solo envíe JSON (`recepcionJson`) y olvídese del `openssl_*`.

**¿Qué pasa si la DGII está caída?**
La pasarela detecta el fallo y, si tiene contingencia activada, encola el documento (`queued: true`, `ok: true`). Luego reintenta automáticamente.

**¿Cómo cambio de entorno (CerteCF → eCF)?**
Cambie la `baseUrl` que pasa al `new Client(...)`. No hace falta reinstalar el SDK.

**¿Puedo enviar múltiples e-CFs en paralelo?**
Sí, instancie un `Client` por hilo / proceso. El SDK no comparte estado mutable.

**¿El SDK soporta async / corutinas?**
No nativamente; usa cURL síncrono. Para concurrencia masiva use ReactPHP/Amphp envolviendo el `Client` o haga `curl_multi_*` por su cuenta con los mismos endpoints.

**¿Por qué `JsonToXml` reemplaza tags con caracteres extraños por `Nodo`?**
Es una protección anti-inyección XML: si una clave no cumple `[A-Za-z_][A-Za-z0-9_.-]*`, se sanea para evitar romper el XML.

**¿Por qué el constructor lanza excepción si paso `http://`?**
Porque la API Key viaja en el header `Authorization` y un MITM podría capturarla en HTTP plano. Use HTTPS siempre. Para tests locales, pase `allowInsecureHttp: true` explícitamente.

**¿Dónde quedan los XML firmados y PDFs?**
La pasarela los persiste 10 años en su panel: **Panel → Archivo de Comprobantes**. Puede descargar PDF o XML por cada e-CF. Si pierde el archivo en disco, el PDF se regenera bajo demanda desde el XML firmado conservado en la base.

---

## Soporte

- **Portal**: [ecf.grodtech.com](https://ecf.grodtech.com)
- **Documentación API**: `DOCUMENTACION_API_ECF_GRODTECH.txt` en el panel
- **Soporte técnico**: desde su panel, incluya su RNC y, si aplica, el `trackId` que devolvió la API.
- **Reportar vulnerabilidades**: ver [SECURITY.md](SECURITY.md)

---

## Licencia

Software propietario. Ver [LICENSE](LICENSE).

Copyright © 2026 GRODTECH. Todos los derechos reservados.
