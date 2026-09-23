# Alertas de impresiones y creación de leads

## Configuración funcional

Abre **Centro de alertas → Impresiones y leads** (`/alerts/metrics`). Usa el permiso `manage-alerts` existente. La bandeja y las confirmaciones continúan siendo personales.

| Categoría | Subcategoría | Tipo |
| --- | --- | --- |
| Publicidad | Impresiones | Impresiones insuficientes |
| Leads Quality | Creación de leads | Leads insuficientes |

Una definición admite varios clientes y cada cliente puede pertenecer a varias definiciones, incluso del mismo tipo. **Personalizar cliente** modifica únicamente su asociación; **Asociar más clientes** amplía la definición con nuevas configuraciones. Los parámetros no se propagan automáticamente entre clientes.

- Mínimo **1**: alertar con cero. Mínimo **10**: alertar de 0 a 9; 10 recupera la incidencia.
- Periodo: 1–720 horas continuas, o días calendario completos usando múltiplos de 24. Los fines de semana forman parte del periodo.
- Horario de consulta: días, hora inicial/final, zona horaria e intervalo mínimo de 5 minutos. Las consultas se ejecutan dentro de esa franja; el retraso de la cola puede aplazar su ejecución.
- Horario de notificación: días y franja independientes, separación entre recordatorios, máximo de avisos, destinatarios, prioridad y comentario obligatorio opcional. Canal interno del centro de alertas.
- 00:00–00:00 significa todo el día. Las franjas nocturnas pertenecen al día de inicio. Para consultar una vez al día, configura una franja corta, por ejemplo 08:00–09:00 y un intervalo de 1440 minutos.
- Un resultado fuera del horario de aviso puede entregarse después, solo si sigue abierto y dentro de su vigencia configurada. Un fallo posterior de consulta suspende los avisos hasta volver a tener datos completos.
- Desactivar la asociación deshabilita consultas y nuevos avisos. Los clientes inactivos se omiten. Las confirmaciones anteriores se conservan.
- Guardar una edición inicia una versión nueva, sustituye las incidencias de esa asociación y conserva su historial. No elimina comentarios ni confirma por los usuarios.

Ejemplos: cliente 1, periodo 72 h y notificaciones Lu–Vi; cliente 2, periodo 24 h y notificaciones Lu–Do; cliente 3, asociación desactivada. Los días de consulta se eligen aparte.

## Impresiones: consulta directa

Se reutilizan `MetaGraphService`, `MetaAccessToken`, `GoogleAdsAuthService`, `GoogleAdsApiClient` y las asociaciones actuales de cuentas. No se invocan ni modifican los jobs de sincronización diarios. Los reportes consultados no se escriben en sus tablas de métricas.

1. Verificar cuentas asociadas al cliente y obtener su zona horaria.
2. Consultar inventario activo por campaña, conjunto/grupo o anuncio. Meta usa `effective_status`; Google exige entidades y padres `ENABLED`.
3. Consultar impresiones con desglose horario o diario según el modo, completando todas las páginas.
4. Evaluar cada entidad elegida. Una entidad activa sin filas de métricas cuenta cero únicamente después de una consulta completa. Una entidad pausada, eliminada o fuera del alcance no genera una nueva alerta.

Sin IDs se supervisan todas las entidades activas del nivel seleccionado. Los filtros aceptan IDs externos, no IDs de filas locales. Las cuentas Meta compartidas se consultan una vez por cuenta en cada lectura y se atribuyen por completo a cada cliente asociado; no hay reparto de impresiones entre clientes.

**Ventana temporal:** se utilizan las últimas N horas completas, desplazadas por un margen de retraso configurable (3 h inicialmente). Por ejemplo, a las 10:30 y con margen 3 h, el periodo de 24 h termina a las 07:00. Cada plataforma usa la zona horaria de su cuenta y el detalle conserva los límites exactos con su desplazamiento UTC. No es una medición minuto a minuto ni garantiza que los datos de la plataforma ya sean definitivos. Si el periodo cruza un cambio de horario estacional ambiguo, la lectura se declara no disponible.

**Excepción verificada con la API v24: anuncios de Google Ads.** `ad_group_ad` rechaza `segments.hour` con `PROHIBITED_SEGMENT_IN_SELECT_OR_WHERE_CLAUSE`. Al seleccionar ese nivel y plataforma, la interfaz exige días calendario completos (24 = 1 día, 72 = 3 días). El reporte diario termina en la última medianoche que ya cumple el margen. Si se eligen ambas plataformas, ambas usan ese modo diario, manteniendo el detalle por cuenta. No se aproxima una ventana horaria usando totales diarios. Campañas y grupos de Google y los tres niveles de Meta admiten el modo horario.

Referencias del desglose: [Google Ads v24, segments.hour](https://developers.google.com/google-ads/api/fields/v24/segments#segments.hour) y [SDK oficial de Meta, AdsInsights](https://github.com/facebook/facebook-python-business-sdk/blob/main/facebook_business/adobjects/adsinsights.py). Se mantienen las versiones API configuradas en los clientes existentes.

Errores, credenciales ausentes, respuestas incompletas y paginación fallida invalidan el ciclo completo. No abren ni recuperan incidencias y no se convierten en cero. Se comparte una caché breve de consultas equivalentes, como máximo 60 segundos; los fallos no se cachean.

**Avisos separados:** una incidencia por cliente, asociación, plataforma, cuenta y entidad. **Aviso conjunto:** una incidencia con todas las entidades que incumplen, mostrando cada plataforma y su conteo. No se suman plataformas para compensar un incumplimiento. Si cambia el conjunto de entidades afectadas, se sustituye el aviso anterior para que una confirmación previa no oculte una nueva entidad afectada.

La espera inicial está activada por defecto: se exige un periodo completo desde la activación (leads) o desde la primera detección de la entidad (publicidad). Puede desactivarse para evaluar inmediatamente el histórico consultable.

## Leads

Se cuenta `leads.customer_id` y `leads.created_at`, con inicio incluido y fin excluido, utilizando el índice existente. Se siguen las zonas horarias de almacenamiento actuales. No se cuentan conversiones publicitarias, envíos a integraciones, actualizaciones ni `meta_created_time`. Una importación creada ahora cuenta ahora; las filas duplicadas existentes siguen siendo filas distintas. La asociación es la vigente en el momento de la consulta.

## Instalación y operación

Migración adicional para MySQL, sin vaciar tablas:

```sh
php artisan migrate --path=database/migrations/2026_09_15_120000_create_alert_metric_monitors.php --force
```

Agrega cinco tablas propias y dos tipos con sus categorías. Permite `rule_id = NULL` en `alert_notifications` para las nuevas entregas, cuya política íntegra queda en `rule_snapshot`; los IDs y la FK de las entregas anteriores se conservan. El rollback se bloquea si existen asociaciones, para preservar el historial.

El scheduler existente debe ejecutar `schedule:run` cada minuto. El proveedor registra `alerts:metrics`, que programa consultas y evalúa avisos independientemente del procesamiento de estados Meta.

Inicia un **worker adicional**, conservando los anteriores:

```sh
php artisan queue:work alert_metrics --queue=alert-metrics --tries=1 --timeout=1800 --sleep=3
```

La conexión adicional utiliza la tabla de jobs actual con cola propia y `retry_after=1900`, mayor que el timeout. No cambies el `retry_after` global ni añadas esta cola a workers con otra conexión. En producción administra este worker con el supervisor de procesos existente; en Windows también puede ejecutarse con el mecanismo que mantenga los demás workers. La instalación del código no crea un servicio del sistema operativo.

Comprobaciones manuales:

```sh
php artisan alerts:metrics
php artisan alerts:metrics-probe ID_CLIENTE --platform=meta
php artisan alerts:metrics-probe ID_CLIENTE --platform=google
php artisan alerts:metrics-probe ID_CLIENTE --platform=google --level=ad
php artisan schedule:list
```

Los probes solo leen y muestran cantidades/periodos; no crean alertas ni avisos. La autenticación Google puede renovar su token mediante el servicio existente. `ALERT_METRICS_ENABLED=false` detiene este módulo adicional; los jobs ya encolados comprueban la habilitación antes de consultar.

No se crean reglas de clientes ni destinatarios automáticamente. Tailwind y Livewire instalados son suficientes; no se actualizan dependencias compartidas.

## Verificación y límites operativos

`tests/Unit/MetricAlertsTest.php` usa SQLite en memoria y APIs simuladas, sin `RefreshDatabase`, junto con las regresiones de `AlertsModuleTest.php`. Cubre categorías, asociaciones múltiples, límites temporales, umbrales, agrupación, concurrencia de edición, idempotencia, errores, horarios, permisos y vistas Livewire. No sustituye una prueba de carga concurrente en MySQL ni una consulta real con las credenciales del despliegue.

Las comprobaciones por cliente quedan en `alert_metric_runs`; las mediciones actuales, en `alert_metric_states`; los detalles de cada incidencia/entrega conservan su periodo y configuración. No se almacenan tokens ni datos personales de leads en estas tablas. No hay purgado automático del historial; debe definirse una política de retención según volumen.

Los errores de conectividad o permisos aparecen como datos no disponibles. La ausencia de avisos también puede deberse a la espera inicial, franjas horarias, vigencia del resultado o a que el worker adicional no esté ejecutándose.

### Verificación local del 15 de septiembre de 2026

- 48 pruebas del módulo de alertas aprobadas, con 171 aserciones, incluidas 27 de la ampliación. También se verificaron las 14 pruebas existentes del catálogo GoHighLevel.
- Consultas reales de lectura: Meta devolvió 1 campaña, 4 conjuntos y 21 anuncios activos; Google devolvió 5 campañas, 7 grupos y 9 anuncios activos. La prueba de anuncios Google confirmó el modo diario. Estas cantidades corresponden a las cuentas elegidas para la comprobación, no al total del sistema.
- Migración adicional aplicada en MySQL. Evaluación y entrega verificadas en una transacción revertida al finalizar, sin dejar avisos de prueba.
- Se conservaron 33 clientes, 59.103 leads, 61 integraciones, 29 cuentas Meta y 33 reglas anteriores. No se crearon configuraciones nuevas para clientes reales.
- El comando del scheduler y el arranque del worker con salida al vaciar la cola fueron comprobados. No se instaló un worker permanente ni se modificó el planificador del sistema operativo.
