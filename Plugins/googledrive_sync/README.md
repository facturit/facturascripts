# Google Drive Sync Plugin

El plugin **googledrive_sync** sincroniza documentos imprimibles de FacturaScripts con Google Drive
y mantiene un historial de auditoría completo para cumplir con los requisitos RGPD. Cada documento
se vincula con su identificador en la nube, ruta personalizada y estado de sincronización.

## Características principales

- **Cola de trabajos con reintentos**: cada documento se envía a `gd_queue` y el cron `Cron/runQueue.php`
  procesa los trabajos liberando bloqueos obsoletos y aplicando backoff exponencial ante errores.
- **Plantillas de rutas y nombres**: se admiten `placeholders` como `{year}`, `{doctype}`, `{third.nif}` o
  `{date:YYYYMMDD}` para construir la jerarquía de carpetas y el nombre del fichero PDF.
- **Auditoría detallada**: la tabla `gd_log` almacena acción, resultado, usuario, tamaño, duración y
  metadatos (como destinatarios compartidos). El panel muestra las últimas entradas con filtros por empresa.
- **Opciones RGPD**: anonimización de nombres en rutas, exclusión de tipos sensibles mediante un selector
  y control sobre el borrado en Drive al eliminar documentos en FacturaScripts.
- **Compartición automática**: al subir un fichero se puede compartir en modo lectura con el tercero
  y direcciones adicionales configuradas; los destinatarios quedan registrados en la auditoría.
- **Panel y asistente masivo**: la pestaña de panel permite reintentos, búsqueda por estado, breakdown por año
  y un wizard para encolar documentos en bloque.
- **Seguridad e internacionalización**: el acceso al panel respeta los permisos `allowAccess/allowUpdate`; todas
  las cadenas están preparadas para traducciones en español e inglés.

## Instalación

1. Copia la carpeta `Plugins/googledrive_sync` en tu instalación de FacturaScripts.
2. Activa el plugin desde el panel de administración y ejecuta el asistente de actualización para crear las tablas
   (`gd_company_cfg`, `gd_file_map`, `gd_folders_map`, `gd_queue`, `gd_log`).
3. Configura las credenciales en la pestaña **Configuración** del panel del plugin.

## Configuración por empresa

En la pestaña de configuración selecciona la empresa y completa:

- **Credenciales**: modo *Cuenta de servicio* (recomendado) u *OAuth 2.0*, JSON de credenciales, identificador de
  Shared Drive y carpeta raíz (opcional). El botón de prueba de conexión aparecerá en futuras versiones.
- **Plantillas**: define ruta y nombre usando los placeholders documentados. El plugin genera automáticamente la
  jerarquía de carpetas y fuerza la extensión `.pdf`.
- **Compartición**: activa “Compartir automáticamente” para enviar el archivo en solo lectura al email del tercero
  (cliente/proveedor) y a las direcciones adicionales (separadas por comas).
- **Privacidad**: marca “Anonimizar nombres en rutas” para usar únicamente el NIF y añade los modelos excluidos
  (facturas, pedidos, etc.) en el selector “Tipos de documento excluidos” para evitar su subida a Drive.
- **Otras opciones**: subir el PDF generado por la plantilla, mover a la papelera al borrar, activar (o no) la
  sincronización inversa experimental.

Los permisos de usuario determinan quién puede acceder al panel y ejecutar acciones de reintento o guardar cambios.

## Ejecución del cron

Lanza periódicamente `php Plugins/googledrive_sync/Cron/runQueue.php --limit=50` desde tu planificador.
El script libera bloqueos, procesa trabajos y deja un resumen en el log (`googledrive-sync-cron-summary`).

## Auditoría y RGPD

- Cada sincronización genera una entrada en `gd_log` con duración, tamaño, acción y resultado (`ok`, `skipped`, `failed`).
- La opción de anonimizar rutas evita incluir el nombre comercial en la estructura de carpetas.
- El selector de tipos excluidos permite omitir documentos sensibles o manuales sin modificar los históricos existentes.

## Pruebas

Se incluyen pruebas básicas bajo `Plugins/googledrive_sync/Test/` para cubrir la resolución de rutas, la configuración
de compartición y la lógica principal del cliente simulado. Ejecútalas con:

```
composer install
vendor/bin/phpunit --configuration phpunit-plugins.xml --testsuite plugins
```

## Estructura

```
Plugins/googledrive_sync/
├── Controller/GoogleDriveSyncController.php
├── Cron/runQueue.php
├── Extension/Model/Base/BusinessDocument.php
├── Lib/{GoogleDriveClient,PathResolver,PdfRenderer,SyncWorker,HashUtil}.php
├── Model/
├── Translation/{en_EN,es_ES}.json
├── View/{panel,config}.html.twig
└── Test/
```

Consulta los ficheros `Sql/install.sql` y `Table/*.xml` para revisar el esquema exacto de la base de datos.
