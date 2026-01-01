# Webhooks

Este plugin añade una pantalla de configuración (ListWebhookRule) desde la que se pueden definir reglas para lanzar peticiones **POST** a endpoints externos cuando se crean o modifican registros en FacturaScripts.

## Características

- Activación independiente por modelo y por evento (insert/update).
- Condiciones de disparo basadas en comparaciones sencillas (por ejemplo `codserie = X; estado = 3`).
- Condiciones previas opcionales para comprobar el estado anterior en actualizaciones (por ejemplo `estado = 3` para detectar transiciones `3 -> 5`).
- Envío de todo el contenido del modelo como JSON. En documentos de venta/compra el JSON incluye los datos de cabecera y las líneas del documento.
- Disparo manual mediante botón en los listados y formularios de edición para las reglas marcadas como "Por acción".

## Formato de las condiciones

Cada regla puede incluir varias condiciones separadas por saltos de línea o punto y coma. Cada condición admite los operadores `=`, `!=`, `<`, `<=`, `>` y `>=`. Los valores pueden ir entre comillas para conservar espacios o distinguir mayúsculas/minúsculas.

## Payload enviado

```json
{
  "event": "insert|update",
  "model": "NombreDelModelo",
  "data": {
    "campo": "valor"
  }
}
```

En el caso de documentos (`getLines()` disponible), la clave `data` incluye:

```json
{
  "header": { "..." },
  "lines": [ { "..." } ]
}
```

Además, el payload incorpora la sección `previous` con los valores previos al guardado (cabecera y líneas vacías en documentos) y `user.nick` cuando la sesión activa lo proporciona.

Para las reglas con la opción **Por acción** se envía el evento `action` con el modelo y los códigos seleccionados en listados o, en edición, el registro completo.

## Desinstalación

Al desinstalar el plugin se elimina la tabla `webhook_rules`.
