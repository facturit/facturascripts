# Plugin MCP API

Este plugin añade una implementación completa del protocolo Model Context Protocol (MCP) sobre la API REST de FacturaScripts. Publica un punto de descubrimiento HTTP tradicional, un manifiesto compatible con clientes MCP modernos y un endpoint JSON-RPC que permite a un agente de IA inspeccionar los recursos disponibles, consultar datos y ejecutar operaciones de escritura de forma estructurada.

## Descubrimiento MCP

- `GET /.well-known/mcp.json`: manifiesto con la versión del protocolo soportada, endpoints disponibles, capacidades y metadatos de autenticación para agentes externos (por ejemplo ChatGPT). El manifiesto incluye los enlaces al flujo OAuth 2.0 y al endpoint JSON-RPC.

## Endpoints REST

Una vez activado el plugin, se publican los siguientes endpoints dentro de la API (`/api/v3`):

- `GET /api/v3/mcp`: documentación general del protocolo MCP para FacturaScripts y enlaces rápidos.
- `GET /api/v3/mcp/resources`: lista resumida de los recursos disponibles y sus operaciones.
- `GET /api/v3/mcp/resources/{recurso}`: detalles completos de un recurso, incluyendo operaciones soportadas y enlaces al esquema.
- `GET /api/v3/mcp/resources/{recurso}/schema`: devuelve el esquema de campos para los recursos de tipo modelo.
- `GET /api/v3/mcp/custom`: relación de recursos especiales gestionados por controladores específicos.

Los datos devueltos permiten a un cliente construir herramientas automáticas y comprender cómo utilizar la API REST estándar de FacturaScripts.

## Endpoint JSON-RPC

- `POST /api/v3/mcp`: servidor JSON-RPC 2.0. Admite peticiones individuales o en lote.

Métodos disponibles:

- `session/create`, `session/refresh` y `session/close`: gestionan la sesión MCP y devuelven metadatos sobre las capacidades disponibles.
- `resources/list`, `resources/get`, `resources/schema`, `resources/query`: exponen el inventario de recursos y permiten ejecutar consultas filtradas sobre los modelos nativos.
- `tools/list`: enumera las herramientas automáticas publicadas (`model_query` y `model_mutation`).
- `tools/call`: ejecuta las herramientas anteriores para realizar consultas, inserciones, actualizaciones o eliminaciones sin tener que construir manualmente la petición REST.

Ejemplo de petición para consultar clientes con JSON-RPC:

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "resources/query",
  "params": {
    "resource": "clientes",
    "filter": {
      "codgrupo": "EMPRESA"
    },
    "limit": 20
  }
}
```

La respuesta contendrá `rows`, `total`, `limit`, `offset`, el nombre del modelo y la clave primaria asociada. Para ejecutar una creación mediante herramientas MCP se puede invocar `tools/call` con `model_mutation` y `operation: "create"`.

Las solicitudes JSON-RPC sin campo `id` se interpretan como notificaciones y devuelven un estado HTTP 204 sin cuerpo. Las sesiones creadas con `session/create` caducan tras una hora de inactividad; ejecuta `session/refresh` para ampliar la expiración si vas a mantener la conversación abierta.

## Autenticación

FacturaScripts sigue aceptando la cabecera `X-Auth-Token` (o `Token`) para integraciones manuales, pero el plugin incorpora además un flujo OAuth 2.0 de tipo _authorization code_ para que los agentes MCP puedan obtener tokens compatibles con `Authorization: Bearer`.

1. **Inicio del flujo:** registra `https://<tu-dominio>/mcp/oauth/authorize` como URL de autorización y `https://<tu-dominio>/mcp/oauth/token` como endpoint de tokens. Cuando el cliente redirija al navegador, introduce la clave API que quieras delegar.
2. **Intercambio de código:** el endpoint `/mcp/oauth/token` acepta peticiones `application/x-www-form-urlencoded` con `grant_type=authorization_code`, `code`, `client_id`, `redirect_uri` y, si procede, `code_verifier` (PKCE).
3. **Uso de tokens:** cada acceso emitido envuelve la clave API original y expira a la hora. El cliente debe incluirlo en `Authorization: Bearer <token>` al llamar a `/api/v3/mcp`.

> ℹ️ Las claves API pueden generarse desde _Administrador → Usuarios → API_. Puedes revocar un acceso eliminando la clave original o esperando a que caduque el token emitido (1 hora).

## Instalación

1. Copia la carpeta del plugin en el directorio `Plugins` de tu instalación de FacturaScripts.
2. Activa el plugin desde _Administrador → Plugins_.
3. Vuelve a generar la caché de rutas si es necesario.

Tras activarlo, el endpoint `/api/v3/mcp` estará disponible tanto para peticiones REST como JSON-RPC.
