# Política de Seguridad

## Versiones soportadas

Solo la rama `main` y la última versión estable etiquetada (`vX.Y.Z`) reciben parches de seguridad.

| Versión   | Soporte de seguridad |
| --------- | -------------------- |
| `1.x`     | ✅                   |
| `< 1.0`   | ❌                   |

## Reportar una vulnerabilidad

**No abra issues públicos para vulnerabilidades de seguridad.** En su lugar:

1. Envíe un correo cifrado a `seguridad@grodtech.com` con:
   - Descripción de la vulnerabilidad
   - Pasos reproducibles (PoC si aplica)
   - Impacto esperado
   - Versión del SDK afectada
   - Su nombre / handle de GitHub para crédito (opcional)

2. **Tiempo de respuesta**:
   - Acuse de recibo: **dentro de 72 horas**
   - Triage inicial: **dentro de 7 días**
   - Parche y disclosure coordinado: según severidad (CVSS), típicamente **dentro de 30 días**

3. **Disclosure responsable**: agradeceremos públicamente al investigador (a menos que prefiera anonimato) en el changelog y en un advisory de GitHub Security.

## Modelo de amenazas

Este SDK opera bajo los siguientes supuestos:

- Se ejecuta **exclusivamente en backend** (servidor web, worker, CLI, CI).
- La API Key se considera **secreto de tipo "server-to-server"** y nunca debe distribuirse a clientes.
- El canal HTTPS hacia `ecf.grodtech.com` es confiable cuando la verificación TLS está activa (lo está por defecto y no se puede desactivar desde el SDK).
- El JSON / XML enviado a la pasarela puede contener PII de clientes finales (RNC, nombres, direcciones); proteja sus logs.

## Vulnerabilidades fuera de alcance

Las siguientes situaciones **no se consideran** vulnerabilidades del SDK:

- Pérdida o exposición de la API Key por mal uso del integrador (commit en repo público, hardcoding en frontend, logs sin redactar).
- Fallos de la DGII o del entorno de pasarela ECF GRODTECH (reportarlos a soporte del portal).
- Negar servicio enviando millones de payloads válidos (use sus propios rate limits).
- Comportamientos esperados de las dependencias del sistema (PHP, OpenSSL, libcurl) cuando están actualizadas.

## Buenas prácticas para integradores

Lea la sección **Seguridad** del [README.md](README.md). Resumen:

- API Key en variables de entorno o secret manager, nunca en código.
- HTTPS obligatorio (el SDK lo valida).
- Permisos `chmod 600` para cualquier archivo que contenga secretos.
- Rote la API Key al menor signo de exposición.
- No logee el cuerpo completo de los requests (PII).
- Mantenga PHP, OpenSSL y libcurl actualizados.
