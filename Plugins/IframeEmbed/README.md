# IframeEmbed

Este plugin permite cargar FacturaScripts dentro de un `iframe` desde dominios concretos.

## Configuración

Añade en tu `config.php` la constante `FS_IFRAME_ALLOWED_ORIGINS` con una lista de orígenes separados por comas, espacios o `;`.

Ejemplo:

```php
define('FS_IFRAME_ALLOWED_ORIGINS', 'https://mi-dominio.com, https://intranet.miempresa.com:8443');
```

## Qué hace

- Elimina la cabecera `X-Frame-Options`.
- Añade `Content-Security-Policy` con `frame-ancestors 'self' ...` usando solo los orígenes válidos configurados.

Si no se configura `FS_IFRAME_ALLOWED_ORIGINS`, el plugin no altera cabeceras.
