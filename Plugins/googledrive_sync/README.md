# Google Drive Sync Plugin

El plugin **googledrive_sync** para FacturaScripts permite sincronizar documentos imprimibles
con Google Drive. Cada documento queda vinculado mediante su identificador de fichero en la nube,
nombre de carpeta personalizado y estado de sincronización.

## Estado actual

Esta entrega inicial incorpora la estructura básica del plugin junto con los modelos del nivel
de persistencia y el esquema de base de datos. Los siguientes hitos añadirán la lógica de
sincronización, controladores, vistas y pruebas funcionales.

## Características previstas

- Sincronización automática de documentos (facturas, albaranes, presupuestos, pedidos, abonos, etc.).
- Generación de rutas dinámicas basadas en plantillas (`{year}/{doctype}/{third.nif} - {third.name}`).
- Gestión de cola de trabajos con reintentos y backoff exponencial.
- Registro de auditoría y opciones RGPD.
- Panel de administración con asistente de resincronización masiva.
- Configuración por empresa con soporte para cuentas de servicio y Shared Drives.

## Instalación

1. Copia la carpeta `googledrive_sync` dentro del directorio `Plugins/` de tu instancia de
   FacturaScripts.
2. Desde el panel de administración habilita el plugin y ejecuta el asistente de actualización
   para crear las tablas (`gd_company_cfg`, `gd_file_map`, `gd_folders_map`, `gd_queue`, `gd_log`).
3. Configura las credenciales de Google Drive en el panel del plugin (próximas entregas).

## Estructura de carpetas

```
Plugins/googledrive_sync/
├── Controller/
├── Cron/
├── Lib/
├── Model/
├── Sql/
├── Table/
├── Test/
├── View/
├── README.md
└── plugin.json
```

Cada directorio seguirá el estándar de FacturaScripts: controladores para el panel y wizard,
librerías para la integración con la API de Google Drive, modelos ORM para la persistencia,
crons para la ejecución de la cola y vistas Twig para la interfaz de usuario.

## Esquema de base de datos

Los ficheros XML bajo `Table/` definen la estructura de las tablas y los índices necesarios.
Además, se incluye `Sql/install.sql` como referencia para instalaciones manuales o auditorías.

## Próximos pasos

- Implementar librerías principales (`GoogleDriveClient`, `PathResolver`, `PdfRenderer`, `SyncWorker`).
- Integrar los modelos de documentos de FacturaScripts con la cola de sincronización.
- Construir el panel de administración y el asistente de resincronización.
- Añadir pruebas unitarias y funcionales.
