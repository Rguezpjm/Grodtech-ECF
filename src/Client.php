<?php declare(strict_types=1);

namespace Grodtech\Ecf;

/**
 * Cliente HTTP para la API de ECF GRODTECH.
 *
 * Diseño "secure by default":
 *  - Solo acepta `baseUrl` con esquema `https://` salvo que se pase explícitamente
 *    `allowInsecureHttp: true` (útil únicamente para entornos de desarrollo locales).
 *  - Verificación TLS estricta (peer + host) NO desactivable por configuración.
 *  - `CURLOPT_PROTOCOLS` y `CURLOPT_REDIR_PROTOCOLS` restringidos a HTTP/HTTPS,
 *    cerrando vectores file://, gopher://, ftp:// si la URL base se manipulara.
 *  - `Authorization` header nunca se imprime en mensajes de error ni en `var_dump`
 *    (ver `__debugInfo`).
 *  - `CURLOPT_CONNECTTIMEOUT` independiente del tiempo total para detectar redes caídas.
 *
 * Uso EXCLUSIVO en servidor. Nunca distribuya la API Key en clientes (navegador, móvil, etc.).
 */
final class Client
{
    private string $base;

    /**
     * @param string $baseUrl   URL base del entorno (TestCF, CerteCF, eCF). Debe ser HTTPS.
     * @param string $apiKey    API Key generada en el panel ECF GRODTECH.
     * @param int    $timeoutSeconds Tiempo total máximo (segundos) para la petición.
     * @param int    $connectTimeoutSeconds Tiempo máximo para establecer conexión TCP/TLS.
     * @param bool   $allowInsecureHttp Permitir baseUrl con http://. SOLO desarrollo local.
     *
     * @throws \InvalidArgumentException si baseUrl o apiKey son inválidos.
     */
    public function __construct(
        string $baseUrl,
        private readonly string $apiKey,
        private readonly int $timeoutSeconds = 120,
        private readonly int $connectTimeoutSeconds = 10,
        bool $allowInsecureHttp = false
    ) {
        $trimmed = trim($baseUrl);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('baseUrl no puede estar vacío.');
        }
        $parts = parse_url($trimmed);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new \InvalidArgumentException('baseUrl mal formada: ' . $trimmed);
        }
        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'https' && !($allowInsecureHttp && $scheme === 'http')) {
            throw new \InvalidArgumentException('baseUrl debe usar HTTPS (recibido: ' . $scheme . ').');
        }
        if (trim($apiKey) === '') {
            throw new \InvalidArgumentException('apiKey no puede estar vacío.');
        }
        if ($timeoutSeconds < 1 || $connectTimeoutSeconds < 1) {
            throw new \InvalidArgumentException('Los timeouts deben ser positivos (segundos).');
        }

        $this->base = rtrim($trimmed, '/');
    }

    /**
     * POST /fe/recepcion/api/ecf con cuerpo JSON (objeto con raíz ECF o RFCE).
     *
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    public function recepcionJson(array $document): array
    {
        try {
            $payload = json_encode(
                $document,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            return [
                'ok' => false,
                'queued' => false,
                'trackId' => '',
                'raw' => null,
                'error' => 'JSON inválido al codificar el documento: ' . $e->getMessage(),
                'http' => 0,
                'curl_error' => null,
            ];
        }

        return $this->postJson('/fe/recepcion/api/ecf', $payload);
    }

    /**
     * POST /fe/recepcion/api/ecf con XML ya generado.
     *
     * @return array<string, mixed>
     */
    public function recepcionXml(string $xml): array
    {
        return $this->request(
            'POST',
            '/fe/recepcion/api/ecf',
            $xml,
            [
                'Content-Type: application/xml; charset=utf-8',
                'Accept: application/json',
            ]
        );
    }

    /**
     * POST /fe/aprobacioncomercial/api/ecf (cuerpo XML).
     *
     * @return array<string, mixed>
     */
    public function aprobacionComercialXml(string $xml): array
    {
        return $this->request(
            'POST',
            '/fe/aprobacioncomercial/api/ecf',
            $xml,
            [
                'Content-Type: application/xml; charset=utf-8',
                'Accept: application/json',
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function postJson(string $path, string $json): array
    {
        return $this->request(
            'POST',
            $path,
            $json,
            [
                'Content-Type: application/json; charset=utf-8',
                'Accept: application/json',
            ]
        );
    }

    /**
     * @param list<string> $extraHeaders
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, string $body, array $extraHeaders): array
    {
        $url = $this->base . $path;
        $headers = array_merge(
            ['Authorization: Bearer ' . $this->apiKey],
            $extraHeaders,
            ['Expect:']
        );

        $ch = curl_init($url);
        if ($ch === false) {
            return $this->transportError(0, 'curl_init falló');
        }

        $allowedProtocols = (defined('CURLPROTO_HTTPS') ? CURLPROTO_HTTPS : 0)
            | (defined('CURLPROTO_HTTP') ? CURLPROTO_HTTP : 0);

        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_NOPROGRESS => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeoutSeconds,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            CURLOPT_USERAGENT => 'grodtech-ecf-php/1.0',
        ];

        if ($allowedProtocols !== 0) {
            $opts[CURLOPT_PROTOCOLS] = $allowedProtocols;
            $opts[CURLOPT_REDIR_PROTOCOLS] = $allowedProtocols;
        }

        curl_setopt_array($ch, $opts);

        $respBody = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($respBody === false) {
            return $this->transportError($code, $err !== '' ? $err : 'curl_exec falló');
        }

        $decoded = json_decode((string) $respBody, true);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'queued' => false,
                'trackId' => '',
                'raw' => null,
                'error' => 'Respuesta no JSON (HTTP ' . $code . '): ' . mb_substr((string) $respBody, 0, 500),
                'http' => $code,
                'curl_error' => $err !== '' ? $err : null,
            ];
        }

        if ($err !== '') {
            $decoded['_ecf_curl_error'] = $err;
        }
        if (!isset($decoded['http'])) {
            $decoded['http'] = $code;
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function transportError(int $code, string $message): array
    {
        return [
            'ok' => false,
            'queued' => false,
            'trackId' => '',
            'raw' => null,
            'error' => $message,
            'http' => $code,
            'curl_error' => $message,
        ];
    }

    /**
     * Redacta la API Key al hacer var_dump / print_r del Client.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'base' => $this->base,
            'apiKey' => '*** REDACTED ***',
            'timeoutSeconds' => $this->timeoutSeconds,
            'connectTimeoutSeconds' => $this->connectTimeoutSeconds,
        ];
    }
}
