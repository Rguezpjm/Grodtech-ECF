<?php declare(strict_types=1);

namespace Grodtech\Ecf;

/**
 * Convierte documento JSON estilo MSeller/DGII (`{ "ECF": { ... } }` o `{ "RFCE": { ... } }`) a XML
 * sin firma, respetando el orden de claves del arreglo (relevante para IdDoc y XSD).
 *
 * El servidor GRODTECH vuelve a validar y firma antes de enviar a la DGII.
 */
final class JsonToXml
{
    /**
     * @param array<string, mixed> $placeholders claves sin llaves: RNC, RAZON_SOCIAL, …
     */
    public static function applyPlaceholders(mixed &$node, array $placeholders): void
    {
        if (is_string($node)) {
            foreach ($placeholders as $key => $val) {
                if (!is_string($key) || $key === '') {
                    continue;
                }
                $node = str_replace('{{' . $key . '}}', (string) $val, $node);
            }

            return;
        }
        if (!is_array($node)) {
            return;
        }
        foreach ($node as &$child) {
            self::applyPlaceholders($child, $placeholders);
        }
        unset($child);
    }

    /**
     * @param array<string, mixed> $ecfRoot contenido bajo la clave `ECF`
     */
    public static function ecfToUnsignedXml(array $ecfRoot): string
    {
        $body = self::encodeChildren($ecfRoot, 1);
        $decl = '<?xml version="1.0" encoding="UTF-8"?>';

        return $decl . "\n<ECF>\n" . $body . '</ECF>' . "\n";
    }

    /**
     * @param array<string, mixed> $data documento con raíz `ECF` o `RFCE`
     *
     * @return array{ok: bool, xml?: string, error?: string, is_rfce?: bool}
     */
    public static function documentFromArray(array $data): array
    {
        if (isset($data['ECF']) && is_array($data['ECF'])) {
            return ['ok' => true, 'xml' => self::ecfToUnsignedXml($data['ECF']), 'is_rfce' => false];
        }
        if (isset($data['RFCE']) && is_array($data['RFCE'])) {
            $body = self::encodeChildren($data['RFCE'], 1);
            $decl = '<?xml version="1.0" encoding="UTF-8"?>';

            return ['ok' => true, 'xml' => $decl . "\n<RFCE>\n" . $body . '</RFCE>' . "\n", 'is_rfce' => true];
        }

        return ['ok' => false, 'error' => 'JSON inválido: se esperaba un objeto con clave raíz «ECF» o «RFCE».'];
    }

    /**
     * @param array<string, mixed> $obj
     */
    private static function encodeChildren(array $obj, int $depth): string
    {
        $pad = str_repeat('  ', $depth);
        $out = '';
        foreach ($obj as $k => $v) {
            if (!is_string($k) || $k === '') {
                continue;
            }
            if ($v === null) {
                continue;
            }
            $out .= self::encodeValue($k, $v, $depth, $pad);
        }

        return $out;
    }

    private static function encodeValue(string $tag, mixed $value, int $depth, string $pad): string
    {
        if (is_array($value)) {
            if ($value === []) {
                return '';
            }
            if (self::isList($value)) {
                $buf = '';
                foreach ($value as $item) {
                    if (is_array($item)) {
                        $buf .= $pad . '<' . self::xmlName($tag) . ">\n" . self::encodeChildren($item, $depth + 1) . $pad . '</' . self::xmlName($tag) . ">\n";
                    } else {
                        $buf .= $pad . '<' . self::xmlName($tag) . '>' . self::escapeScalar($item) . '</' . self::xmlName($tag) . ">\n";
                    }
                }

                return $buf;
            }

            return $pad . '<' . self::xmlName($tag) . ">\n" . self::encodeChildren($value, $depth + 1) . $pad . '</' . self::xmlName($tag) . ">\n";
        }
        $scalar = self::escapeScalar($value);
        if ($scalar === '' && $value !== '' && $value !== 0 && $value !== 0.0 && $value !== false) {
            return '';
        }

        return $pad . '<' . self::xmlName($tag) . '>' . $scalar . '</' . self::xmlName($tag) . ">\n";
    }

    private static function xmlName(string $tag): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_.-]*$/', $tag)) {
            return 'Nodo';
        }

        return $tag;
    }

    /** @param array<mixed> $a */
    private static function isList(array $a): bool
    {
        $i = 0;
        foreach ($a as $k => $_) {
            if ($k !== $i) {
                return false;
            }
            ++$i;
        }

        return true;
    }

    private static function escapeScalar(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        if (is_float($v)) {
            if (!is_finite($v)) {
                return '';
            }
            if (floor($v) == $v) {
                return number_format($v, 0, '.', '');
            }

            return number_format($v, 2, '.', '');
        }
        if (is_int($v)) {
            return (string) $v;
        }

        return htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param array<string, mixed> $data árbol con clave `ECF`
     */
    public static function ensureFechaHoraFirmaPlaceholder(array &$data): void
    {
        if (!isset($data['ECF']) || !is_array($data['ECF'])) {
            return;
        }
        $ecf = &$data['ECF'];
        $fh = $ecf['FechaHoraFirma'] ?? null;
        if (!is_string($fh) || trim($fh) === '') {
            $tz = new \DateTimeZone('America/Santo_Domingo');
            $ecf['FechaHoraFirma'] = (new \DateTimeImmutable('now', $tz))->format('d-m-Y H:i:s');
        }
    }

    /**
     * @param array<string, mixed> $data árbol con clave `RFCE`
     */
    public static function ensureFechaGeneracionRfce(array &$data): void
    {
        if (!isset($data['RFCE']) || !is_array($data['RFCE'])) {
            return;
        }
        $r = &$data['RFCE'];
        $fg = $r['FechaGeneracion'] ?? null;
        if (!is_string($fg) || trim($fg) === '') {
            $tz = new \DateTimeZone('America/Santo_Domingo');
            $r['FechaGeneracion'] = (new \DateTimeImmutable('now', $tz))->format('Y-m-d\TH:i:sP');
        }
    }
}
