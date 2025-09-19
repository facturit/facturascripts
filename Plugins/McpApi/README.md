# Plugin MCP API

Este plugin añade una implementación completa del protocolo Model Context Protocol (MCP) sobre la API REST de FacturaScripts. Publica un punto de descubrimiento HTTP tradicional y un endpoint JSON-RPC que permite a un agente de IA inspeccionar los recursos disponibles, consultar datos y ejecutar operaciones de escritura de forma estructurada.

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

Los endpoints mantienen las mismas reglas de autenticación que el resto de la API. Debes enviar la cabecera `X-Auth-Token` (o `Token`) con una clave generada en _Administrador → Usuarios → API_.

## Instalación

1. Copia la carpeta del plugin en el directorio `Plugins` de tu instalación de FacturaScripts.
2. Activa el plugin desde _Administrador → Plugins_.
3. Vuelve a generar la caché de rutas si es necesario.

Tras activarlo, el endpoint `/api/v3/mcp` estará disponible tanto para peticiones REST como JSON-RPC.
