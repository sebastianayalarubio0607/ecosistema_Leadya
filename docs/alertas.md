# Centro de alertas

## Alcance

Módulo adicional Laravel/Livewire/Tailwind. Solo notificaciones internas. El primer productor detecta cuentas Meta que estaban activas (`1`) y pasan a un estado no activo válido, estando habilitadas internamente y asociadas a clientes. Sin estado anterior se establece una línea base; no se inventan caídas históricas.

No se modifican los servicios, jobs, tablas ni resolvedores de clientes existentes. Las únicas conexiones con la aplicación son `AlertServiceProvider` en `bootstrap/providers.php` y el componente de campana en la navegación. Los observers adicionales escriben observaciones y aíslan sus fallos. `saveQuietly()` continúa igual: su consulta se observa mediante el historial que ya registra el servicio. La importación se observa mediante eventos normales del modelo.

Las relaciones nuevas con clientes y usuarios usan `nullOnDelete` (o eliminan exclusivamente asociaciones nuevas), para no impedir operaciones que la aplicación ya permite. Los nombres históricos, incidencias y comentarios se conservan.

## Instalación MySQL

Aplicar **exclusivamente** la migración nueva, después de revisar el destino configurado. No ejecutar `migrate:fresh`, `refresh`, `reset` ni importar bases de datos.

```sh
php artisan migrate --path=database/migrations/2026_09_14_120000_create_alert_module_tables.php --force
npm run build
php artisan alerts:status
```

Mantener el scheduler existente y añadir un worker para la conexión de cola configurada:

```sh
php artisan queue:work --queue=alerts --tries=3 --timeout=60 --sleep=3
```

El comando debe ser administrado por el supervisor de procesos del despliegue. El módulo agenda un job cada minuto; los workers actuales no necesitan modificarse. `retry_after` debe ser mayor que 60 segundos (el valor de respaldo actual es 90). Una comprobación manual puede hacerse con `php artisan alerts:process`; no consulta APIs externas. Tras desplegar el proveedor, reiniciar los workers mediante el procedimiento habitual del servidor para que registren los observers nuevos.

Variables opcionales:

```dotenv
ALERTS_ENABLED=true
ALERTS_QUEUE=alerts
ALERT_MANAGER_IDS=1,2
```

Sin `ALERT_MANAGER_IDS`, la administración mantiene el criterio de los módulos actuales del back-office: usuarios autenticados. La lista permite restringir solo este módulo. La bandeja y todas sus acciones siempre se limitan al destinatario autenticado. No se introduce ni se cambia una política global de acceso a clientes.

Sin la migración, o con `ALERTS_ENABLED=false`, la campana se oculta y no se capturan observaciones. La ruta informa que el módulo no está disponible. No se cambian las dependencias compartidas: Livewire 3.6.3 y Tailwind 3 instalados son suficientes.

## Alertas adicionales de impresiones y leads

La ampliación con consultas directas a Meta/Google y horarios independientes está documentada en [alertas-metricas.md](alertas-metricas.md). Sus categorías son Publicidad → Impresiones y Leads Quality → Creación de leads. Las reglas y límites que siguen describen el detector original de estados de cuenta.

## Operación

- `/alerts`: bandeja personal, no leídas, pendientes y todas.
- `/alerts/rules`: creación, edición, activación/inactivación por cliente.
- `/alerts/history`: incidencias, destinatarios, entregas, lectura, comentarios y recuperación.
- Una configuración para varios clientes crea copias independientes. Los valores iniciales del formulario funcionan como predeterminados; no hay herencia global dinámica.
- Solo existe una regla por cliente y alcance. Tipo > subcategoría > categoría. La regla más específica sustituye a la general incluso si está inactiva o fuera de vigencia.
- Los usuarios se asocian a cada regla. Cambiar destinatarios detiene entregas futuras a quienes se retiren; conserva los avisos que ya recibieron.
- Horas vacías permiten todo el día. Una ventana 22:00–06:00 pertenece al día en que empieza. Fechas del formulario se interpretan en su zona horaria y se guardan siguiendo la zona de la aplicación, sin cambiar la convención de los datos existentes.
- Fecha final vacía significa permanente. Máximo incluye la primera entrega y sus recordatorios, por episodio/usuario/canal; editar la regla no reinicia los contadores.
- El umbral cuenta incidencias abiertas distintas del cliente que realmente usan esa regla. No cuenta consultas repetidas ni combina clientes. Al cumplirse habilita avisos individuales; no genera un resumen agrupado.
- Lectura y confirmación son diferentes. Si se exige respuesta, abrir no quita de pendientes; se exige comentario no vacío de hasta 4000 caracteres. La recuperación automática no borra esa obligación. La confirmación es idempotente y personal.
- Sin respuesta obligatoria, leer retira de pendientes y detiene recordatorios. Archivar conserva la entrega en “Todas”.
- Descartar administrativamente detiene avisos futuros y conserva la clave del episodio hasta recuperación; no confirma por otros usuarios. Una nueva caída posterior crea un episodio enlazado al anterior.

## Procesamiento y garantías

`alert_observations` es una bandeja durable de entrada. El job procesa observaciones bajo transacción y bloqueo por fuente; la huella única abierta incluye cliente, plataforma, entidad y tipo. Las entregas internas y sus contadores se confirman juntos, con una clave única por destinatario/canal/secuencia. No hay efectos externos que puedan quedar enviados a medias.

El recuperador lee hasta 500 historiales nuevos y 250 cuentas por ciclo (cursor circular de snapshots), exclusivamente en lectura. Los historiales anteriores a la instalación no se reenvían. Si existe atraso de historiales/observaciones, se aplazan entregas hasta procesarlo. Observaciones más antiguas que la última aplicada no sobrescriben el estado nuevo. Las reglas fuera de horario vuelven a evaluarse aunque Meta no cambie otra vez.

Límites explícitos:

- Se conserva la frecuencia actual de consulta a Meta; este módulo no ofrece detección instantánea ni agrega llamadas a Meta.
- La recuperación por snapshot no puede reconstruir una caída y recuperación completas ocurridas entre lecturas cuando también falló la captura. Los procesos antiguos no se convierten en transacciones nuevas; se prioriza no alterar su funcionamiento.
- El historial antiguo solo conserva un cliente y el estado interno actual. La recuperación reconstruye asociaciones vigentes; las observaciones capturadas normalmente conservan la lista de clientes del momento de captura.
- Los estados agregados `201/202`, respuestas vacías y errores de consulta no se usan para abrir incidencias. `disable_reason` se conserva como dato; no se interpreta automáticamente como causa de pago. `problema_pago` queda pendiente de validar el contrato Meta de la versión desplegada.
- Solo `internal` es seleccionable. La columna de canal y la separación de entregas permiten añadir adaptadores futuros. No se crean canales ficticios ni tipos sin detector.
- Las entregas internas no necesitan una tabla de intentos externos: sus fallos se revierten y reintentan como jobs. Los fallos definitivos utilizan `failed_jobs` de Laravel.
- No hay purgado automático. Definir retención de observaciones procesadas antes de crecer a grandes volúmenes; conservar incidencias/comentarios. Revisar la duración del evaluador de reglas al aumentar el número de incidencias abiertas.

## Validación

`tests/Unit/AlertsModuleTest.php` usa SQLite en memoria, sin `RefreshDatabase`, sin tocar MySQL ni APIs reales. Cubre episodios, concurrencia mediante idempotencia/repetición (no sustituye una prueba de carga MySQL), cuentas compartidas, errores, respaldo de captura, horarios, intervalos, umbral, permisos, edición concurrente, lectura, comentario y vistas Livewire.

```sh
php vendor/bin/phpunit tests/Unit/AlertsModuleTest.php --no-progress
```

La suite Unit general se ejecutó aislando su base de datos. Tres pruebas existentes de `FacebookConversionsServiceTest` fallan por no crear las tablas `customers`/`qualification` de sus fixtures; el mismo fallo se reproduce con alertas deshabilitadas. No se alteraron esas funcionalidades ni sus pruebas.
