**Informe técnico: páginas Meta y WhatsApp asociados a varios clientes**

Fecha: 10 de septiembre de 2026. Repositorio: ecosistema_Leadya. Base revisada al cierre: commit 0d02a7b. Al iniciar, sus cambios estaban locales sobre 4889e2b; se verificó que el commit incorpora esos mismos ajustes de sincronización y vistas.

**Dictamen**

El ajuste es viable y aporta valor para agencias, marcas y unidades comerciales que comparten activos. Sin embargo, requiere separar tres conceptos: el activo de Meta, la autorización para acceder a él y el cliente que debe recibir cada lead. Una tabla de asociaciones por sí sola no resuelve la distribución.

La recomendación es permitir varias páginas por cliente y varios clientes por página, conservando **un destinatario activo por formulario instantáneo**. Para WhatsApp, separar WABA de sus números y asignar por **Phone Number ID + regla comercial explícita**, con validación de la WABA. Cuando haya varios destinatarios posibles, conservar el evento pendiente de resolución; no elegir por antigüedad ni replicarlo automáticamente.

**1. Alcance y grado de certeza**

Se revisaron modelos, migraciones, rutas, controladores, validaciones, sincronizadores, observadores, colas, credenciales, almacenamiento de webhooks, conversiones, despacho de integraciones, consultas de métricas y pruebas relacionadas. El mapa de dependencias aparece en el apartado 9.

Este análisis describe el código local. No se consultaron datos de producción ni el panel de la aplicación de Meta; por tanto, no certifica qué WABAs están realmente suscritas, qué permisos están aprobados, qué procesos de cola están ejecutándose o cuántos leads resultaron afectados.

Se ejecutaron 24 pruebas existentes con SQLite en memoria: 21 pruebas de webhooks/sincronización, 170 aserciones; 3 pruebas de resolución de credenciales WhatsApp, 12 aserciones. Todas pasaron. Pest emitió un aviso de permisos al escribir su caché de resultados en vendor; no hubo fallos de pruebas. Estas pruebas validan el comportamiento actual, incluidas algunas reglas de fallback que deben cambiar.

No se modificaron los ajustes previos de sincronización de Meta y vistas de integraciones, incorporados a Git durante la revisión. El único archivo añadido por este análisis es este informe. No se modificó código funcional ni se ejecutaron suscripciones reales. La documentación de developers.facebook.com devolvió errores de acceso/429; se contrastaron los aspectos de suscripciones con publicaciones oficiales de Meta en Postman y ejemplos fbsamples. Los permisos específicos sujetos a modalidad de integración se señalan como verificaciones pendientes.

**2. Qué representa cada identificador**

| Concepto | Significado y uso correcto |
|---|---|
| Cliente de Leadya, customer_id | Organización o unidad comercial destinataria dentro de este sistema. No es un Business ID de Meta. |
| Business Portfolio ID / Business ID | Identifica el negocio o portafolio de Meta que posee o tiene acceso a activos. |
| App ID | Identifica la aplicación Meta, su configuración de webhooks y su contexto de credenciales. |
| Page ID | Identifica una página de Facebook. No equivale a una cuenta publicitaria. |
| Ad Account ID | Identifica una cuenta publicitaria que contiene campañas, conjuntos y anuncios. |
| Form ID | Identifica el formulario instantáneo; es la clave comercial adecuada para separar leads de una página compartida. |
| WABA ID | Identifica la cuenta de WhatsApp Business; una WABA puede contener varios números. |
| Phone Number ID | Identifica técnicamente un número empresarial dentro de WhatsApp Cloud API; no es el número telefónico visible. |
| metadata.display_phone_number | Número empresarial visible al que escribe la persona. El proyecto lo guarda en number_whatsApp_companies. |
| contacts.wa_id / messages.from | Identificador/número del contacto que escribe. No debe confundirse con el teléfono empresarial receptor. |
| messages.id | Identificador del mensaje, normalmente wamid; sirve para impedir procesamiento repetido. |
| referral.source_id / ctwa_clid | Origen de la referencia y clic de anuncio a WhatsApp; ayudan a atribuir, pero no son customer_id. |

En tu enumeración, la tercera aparición de “Phone Number ID” debe distinguirse como **número telefónico visible**, normalmente display_phone_number.

Además, el código reutiliza nombres: meta_pages.meta_page_id es el ID externo de Meta, mientras meta_forms.meta_page_id y leads.meta_page_id son claves internas de base de datos. La misma distinción aplica a meta_form_id en formularios, mapeos y leads. Conviene hacerla explícita en contratos y pantallas.

**3. Estado actual de las relaciones**

| Relación | Estado real |
|---|---|
| Cliente → varias páginas | Ya existe mediante Customer::metaPages(), relación hasMany. |
| Página → varios clientes | No existe: MetaPage contiene un solo customer_id. |
| Página → varios formularios | Ya existe. Cada Form ID externo es único. |
| Formulario → cliente independiente | No existe. El cliente se hereda de la página. |
| Cliente ↔ cuentas publicitarias | Existe customer_meta_ad_account, con cliente predeterminado para leads WhatsApp y campo legacy. |
| Cliente ↔ registros WhatsApp | Existe customer_meta_whatsapp en ambos sentidos. |
| WABA → varios números | No está correctamente representado: waba_id es único y cada fila contiene un solo phone_number_id. |
| WABA ↔ aplicaciones | La API contempla aplicaciones suscritas; localmente se guarda una lista y un único estado/contexto de suscripción seleccionado. |

Referencias: [Customer](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Models/Customer.php:100), [MetaPage](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Models/MetaPage.php:11), [MetaForm](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Models/MetaForm.php:11), [MetaWhatsapp](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Models/MetaWhatsapp.php:42), [esquema WhatsApp](C:/proyectos/app.leadsya.com/ecosistema_Leadya/database/migrations/2026_08_12_030000_create_meta_whatsapps_and_subscription_tables.php:11), [cuentas compartidas](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Models/MetaAdAccount.php:52).

**Hallazgo crítico actual:** al guardar páginas seleccionadas desde un cliente, syncCustomerMetaPages() actualiza directamente customer_id en todas esas páginas. Si la página pertenecía a A y se selecciona desde B, pasa a B. No se crea una asociación adicional. La siguiente importación de leads nuevos usa B incluso para formularios utilizados comercialmente por A. Los leads ya existentes conservan su cliente porque el sincronizador los omite al encontrarlos. Esto puede dividir la historia de un mismo formulario entre clientes. [Código de reasignación](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Controllers/CustomerController.php:337).

**4. Informe de formularios instantáneos**

**4.1. Flujo actual**

1. El POST /api/webhooks/meta/lead-ads, o su alias /lead-ad, almacena el evento.
2. Por cada página con cambios leadgen, despacha SyncMetaPageLeadsJob. El controlador extrae page_id y tiempo, pero no utiliza form_id ni leadgen_id para crear un trabajo específico.
3. El job localiza una única página por su ID externo, sincroniza sus formularios y consulta leads de todos sus formularios elegibles.
4. Un formulario es elegible si está activo, su página está activa y tiene customer_id, y existe al menos un mapeo activo.
5. El servicio consulta /{form_id}/leads, aplica mapeos, obtiene customer_id desde form->page->customer_id y busca un lead existente por meta_lead_id.
6. Si existe, lo omite. Si es nuevo, crea el lead, despacha la conversión Meta cuando corresponde, registra historial inicial y despacha las integraciones activas del cliente.

Referencias: [receptor](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Controllers/Webhooks/MetaLeadAdsWebhookController.php:141), [trabajo por página](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Jobs/SyncMetaPageLeadsJob.php:38), [selección de formularios](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Services/Meta/MetaLeadAdsSyncService.php:377), [asignación y efectos posteriores](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Services/Meta/MetaLeadAdsSyncService.php:492).

**4.2. Tu escenario: misma página, distintos formularios**

| Evento | Destino propuesto |
|---|---|
| Página P, formulario F1 asignado a A | Crear solo en A. |
| Página P, formulario F2 asignado a B | Crear solo en B. |
| Página P, formulario F3 sin asignación | Registrar pendiente; no adoptar el cliente de otro formulario. |
| Página P, F1 inactivo, F2 activo | Detener importación comercial de F1; mantener F2 y la suscripción compartida. |
| Desvinculación de A | Preservar página, suscripción y formularios que B necesita. |
| Repetición de un evento de F1 | Reconocer el evento/lead existente y evitar duplicados y reenvíos. |

La página debe mantenerse como catálogo único; los formularios también. Duplicar la misma página por cliente rompería sus índices únicos, la búsqueda firstWhere(), la unicidad de jobs y la pertenencia de formularios.

La suscripción leadgen es de página/aplicación. El filtrado por formulario debe hacerlo Leadya. El ejemplo oficial de Meta muestra los dos niveles de suscripción y un evento con page_id, form_id y leadgen_id, seguido de la consulta del lead por su ID. [Ejemplo oficial de Lead Ads](https://github.com/fbsamples/lead-ads-webhook-sample/blob/main/postman/FB%20Lead%20Ads%20%28Part%201%20-%20The%20Webhook%29.postman_collection.json).

**4.3. Ajustes necesarios**

- Incorporar customer_meta_page con asociación única, estado local, auditoría y contexto de acceso.
- Añadir un destinatario por formulario: customer_id en meta_forms sería el cambio mínimo; una tabla de asignaciones con vigencia permite historial y reasignaciones auditadas.
- Comprobar que el cliente asignado al formulario también esté asociado activamente a su página.
- Resolver el cliente desde el formulario, tanto en webhook como en sincronización manual, horaria y recuperación histórica.
- Mantener los mapeos existentes por formulario si este tiene un único destinatario. Solo llevarlos a una asociación formulario–cliente si se decide permitir entrega múltiple del mismo formulario.
- No activar automáticamente nuevos formularios descubiertos. Mostrar “sin asignar”, “sin mapeo” y “listo para importar” como estados distintos.
- Procesar cada leadgen_id en un trabajo idempotente; conservar sincronización por formulario como recuperación.
- Validar que el form_id recuperado pertenece a la página anunciada y coincide con la asignación vigente aplicable.
- Congelar la decisión de asignación para el evento. Si F1 cambia de A a B mientras hay trabajos pendientes, aplicar una política por fecha de evento y registrar la versión de regla; no depender únicamente del estado que exista al ejecutarse la cola.
- Mantener el índice único global de meta_lead_id si cada lead externo tiene un solo destinatario. Compartir página no obliga a cambiarlo. Entregar el mismo lead a varios clientes sería una funcionalidad distinta, con registro fuente y entregas únicas por cliente.

**4.4. Riesgos específicos de sincronización**

La tarea por página es única durante su ciclo de bloqueo, con uniqueFor=300. Agrupar eventos reduce carga, pero un nuevo evento puede quedar sin un job propio si llega durante otro proceso. La sincronización horaria es el respaldo; no reemplaza una bandeja durable de pendientes. Dentro de un payload, el tiempo guardado para una página es el último recorrido, no necesariamente el más antiguo del lote.

El job usa como inicio el tiempo del webhook menos 15 minutos; la tarea horaria usa la hora anterior completa calculada cuando ejecuta. No hay un cursor durable de última recuperación satisfactoria. Tras una interrupción prolongada, algunas ventanas pueden quedar sin cubrir. from_date/to_date se envían a Graph, pero estas pruebas no comprueban que la versión real de Meta aplique esos parámetros como se espera: debe verificarse con leads en los límites temporales y paginación. [Ventana](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Services/Meta/MetaLeadAdsSyncService.php:663).

Los errores se capturan por formulario y por lead. Un lead omitido por mapeo requerido faltante solo genera un log; el formulario puede acabar con last_synced_at actualizado y last_error vacío. Se necesita separar “se consultó” de “se importaron todos los resultados” y registrar fallos recuperables individualmente.

Si se crea el lead y después falla el despacho de conversiones/integraciones, una nueva sincronización lo encuentra existente y lo omite. Por eso la recuperación de efectos externos debe tener estado propio y una outbox transaccional; no depender de volver a crear el lead.

La consulta descarga todas las páginas de resultados en memoria. Repetir el escaneo completo de una página por cada cliente multiplicaría llamadas, memoria y riesgo de límites de API. El catálogo y la descarga deben compartirse, conservando aislado el destino comercial.

**5. Informe de leads por WhatsApp**

**5.1. Qué mensajes crean leads hoy**

El servicio procesa object=whatsapp_business_account, field=messages y mensajes que contengan referral. Un mensaje orgánico sin referral no crea lead por este flujo. Los eventos de estados enviados/leídos/entregados tampoco generan leads.

Existe una diferencia entre los dos registros: la tabla especializada meta_whatsapp_messages solo almacena referencias con source_type=ad; el creador de leads acepta cualquier referral. Una referencia a una publicación podría tratarse como anuncio, porque source_id se copia a meta_id_ad sin esa comprobación. Debe definirse una política consistente. [Servicio](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Services/Meta/MetaWhatsappReferralLeadService.php:49), [almacenamiento especializado](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Controllers/Webhooks/MetaWhatsAppWebhookController.php:91).

El nombre sale del contacto o del texto; el teléfono sale de wa_id/from y otras alternativas. Guarda WABA, número empresarial visible, identificador del contacto y ctwa_clid. **El lead no guarda Phone Number ID ni message_id**, aunque el receptor sí los dispone. Eso limita trazabilidad y deduplicación por número receptor. Además tc=true se establece automáticamente: técnicamente no representa evidencia de aceptación de condiciones por esa persona. Registrar origen y evidencia de consentimiento separadamente.

**5.2. Cómo elige el cliente**

La prioridad real es:

1. Buscar referral.source_id en MetaAdInsight y obtener la cuenta publicitaria.
2. Obtener el cliente predeterminado WhatsApp de esa cuenta. Si no existe predeterminado, el modelo puede elegir el cliente asociado más antiguo y actualizar los flags en base de datos.
3. Usar customer_id legacy o el cliente del último lead del mismo anuncio, según el resultado anterior.
4. Si no se resolvió por origen, buscar asociaciones WhatsApp con WABA coincidente **o** Phone Number ID coincidente; escoger la fila pivot más reciente.
5. Como respaldo, tomar el cliente del último lead con la misma WABA **o** teléfono empresarial visible.
6. Si nada resuelve, registrar warning y omitir el lead.

Referencias: [resolución por origen](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Services/Meta/MetaWhatsappReferralLeadService.php:275), [resolución por WhatsApp](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Services/Meta/MetaWhatsappReferralLeadService.php:335), [predeterminado y mutaciones durante resolución](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Models/MetaAdAccount.php:99).

**Consecuencias concretas:**

- Un número compartido por A y B no distribuye a ambos ni entiende sus campañas por sí mismo. Puede enviar todo al predeterminado de la cuenta publicitaria o al último cliente asociado.
- Si una WABA tiene N1 para A y N2 para B, buscar por WABA OR número no garantiza aislamiento por N1/N2.
- La ruta de cuenta/anuncio devuelve antes de comprobar que ese cliente esté asociado al número receptor.
- Un anuncio nuevo sin insights entra en fallbacks históricos. El sincronizador de insights programado consulta ayer a las 02:00; no es una fuente garantizada para identificar en tiempo real anuncios recién creados.
- No se filtra status del WhatsApp ni del cliente en estas consultas de resolución. Un registro desactivado puede seguir siendo elegido mientras lleguen o se reproduzcan eventos.
- Una asignación errónea histórica puede alimentar futuras asignaciones erróneas.
- Resolver el predeterminado modifica flags sin una transacción/lock que abarque toda la decisión: conviene sacar esa corrección de la ingestión y hacer explícitas las reglas en configuración.

**5.3. Deduplicación y efectos posteriores**

Con ctwa_clid se busca customer_id + ctwa_clid. Sin él, se usa customer_id + contacto + anuncio + WABA, sin número receptor ni ventana temporal. Esto puede suprimir oportunidades nuevas del mismo contacto/anuncio o dejar pasar duplicados cuando faltan esos datos. Si cambia el cliente resuelto, el mismo evento puede producir un segundo lead.

Estas comprobaciones son “buscar y luego insertar”; los campos WhatsApp tienen índices normales, sin restricción única equivalente. Dos workers pueden crear dos leads. La unicidad de message_id en meta_whatsapp_messages no lo evita: el job del lead es independiente y no usa ese registro como bloqueo.

is_first_message se calcula por wa_id global, sin cliente/número receptor; tampoco representa correctamente el primer contacto por negocio y tiene una carrera entre workers. [Deduplicación](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Services/Meta/MetaWhatsappReferralLeadService.php:247), [primer mensaje](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Controllers/Webhooks/MetaWhatsAppWebhookController.php:151).

Tras crear el lead se despacha conversión Meta y se registra historial inicial. **No se despacha ProcessLeadIntegrationsJob**, a diferencia de formularios y API. Las pruebas actuales comprueban expresamente esta ausencia. Activar envíos de WhatsApp a CRM debe ser una decisión funcional explícita, preferentemente configurable por cliente/canal. [Prueba existente](C:/proyectos/app.leadsya.com/ecosistema_Leadya/tests/Feature/MetaLeadAdsWebhookTest.php:421).

**5.4. Distribución recomendada**

Resolver primero el número exacto con metadata.phone_number_id y comprobar que pertenece a entry.id, la WABA. Obtener únicamente asociaciones activas de ese número, dentro del contexto de aplicación autorizado.

Si existe una regla específica por anuncio/campaña/formato/origen, usarla solo si el destinatario también está habilitado para ese número. Si hay un solo destinatario posible, asignarlo. Si hay varios, exigir una regla o un predeterminado elegido conscientemente para ese número y alcance; ante conflicto, registrar pendiente.

Un número compartido y un mensaje sin referencia pueden no contener suficiente información para identificar al cliente. Las opciones son un número separado, una selección explícita en la conversación o una bandeja de distribución manual. No es un problema que se resuelva deduciendo la página.

Guardar message_id, phone_number_id, waba_id, app_id/conexión validada, timestamp del mensaje, evento origen y regla aplicada. Impedir repetición del mensaje con índice único en el registro fuente; separar esa idempotencia técnica de la política comercial que decide si varios mensajes/clics forman una o varias oportunidades.

**6. Suscripciones y credenciales necesarias**

**6.1. WhatsApp: dos niveles**

El proyecto necesita una aplicación con callback HTTPS verificado y el objeto whatsapp_business_account/campo messages configurado. Además, cada WABA que deba entregar eventos tiene que estar suscrita a esa aplicación mediante /{WABA_ID}/subscribed_apps. Suscribir una WABA no suscribe todas las demás del portafolio.

Una suscripción WABA cubre sus números; no se repite por cliente interno ni por Phone Number ID. La colección oficial indica usar un System User Access Token con whatsapp_business_management para estos endpoints. [Meta: suscripciones de WABA](https://www.postman.com/meta/whatsapp-business-platform/folder/ozgs3jn/webhook-subscriptions).

Para una sola aplicación con varios negocios/clientes de Meta, puede mantenerse un callback compartido: verificar separadamente cada WABA y los permisos sobre sus activos, y enrutar por identificadores del evento. Esto no convierte automáticamente al usuario del sistema de un negocio en autorizado para otro.

Si se usan varias aplicaciones, registrar y comprobar cada pareja App ID–WABA ID. GET /{WABA_ID}/subscribed_apps devuelve las aplicaciones suscritas: hay que comprobar el App ID esperado, no solo que data tenga elementos. [Meta: consulta de suscripciones](https://www.postman.com/meta/whatsapp-business-platform/request/tl2wk2j/get-all-subscriptions-for-a-waba).

Los callbacks alternativos por WABA pueden cambiar el destino real de messages. Deben incluirse en el inventario, especialmente si pruebas y producción comparten activos. [Meta: override de callback](https://www.postman.com/meta/whatsapp-business-platform/request/l6a09ow/override-callback-url).

**6.2. Qué implementa el proyecto y qué falta**

Implementa GET, POST y DELETE sobre /{waba_id}/subscribed_apps; compara el App ID en la respuesta, incluso en whatsapp_business_api_data.id. Tiene jobs, reintentos, registro de fallos y escaneo diario a las 03:00. Es una base aprovechable. [Servicio de suscripción](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Services/Meta/Subscription/Whatsapp/MetaWhatsappSubscriptionService.php:140).

Faltan o deben revisarse:

| Dependencia | Hallazgo y ajuste |
|---|---|
| Configuración de aplicación | El servicio verifica la WABA, pero no certifica la URL/campo messages de la aplicación ni su entrega real. Añadir diagnóstico separado y prueba extremo a extremo. |
| Estado compartido | status pertenece a la fila WABA. Desactivarla encola DELETE para la aplicación seleccionada y afecta a todos sus números/clientes. Calcular necesidad de suscripción desde todas las asociaciones activas. |
| Borrado | MetaWhatsappObserver encola baja al eliminar la fila. Desvincular un cliente debe operar sobre su asociación; reservar borrado/baja global para administración del activo. |
| Jobs obsoletos | Subscribe/Unsubscribe ejecutan la orden sin reevaluar todas las necesidades actuales. Una baja retrasada puede cancelar una reactivación posterior. Reconciliar bajo lock por App ID–WABA. |
| Identidad editable | Cambiar WABA ID o token/App ID puede dejar la suscripción anterior activa sin un ciclo de transición explícito. Versionar conexión y limpiar solo la anterior cuando corresponda. |
| Credencial elegida | Prioridad: token del job, token de WABA, token de pivot, token de cliente, default y legacy. Un token global de WABA se antepone al específico de la asociación. |
| Varios clientes/token | Sin customerId, se elige el último token de pivot/cliente candidato; no se exploran y validan todas las conexiones. |
| Varios App ID | Un solo booleano y un solo subscription_meta_app_id no modelan varias suscripciones independientes. Usar tabla por pareja aplicación–activo. |
| Permisos | El servicio enumera cinco permisos, pero faltantes solo producen warnings. ads_read/ads_management no deben interpretarse como mínimos universales de recepción WhatsApp; corresponden a funciones publicitarias adicionales. |
| Tokens de usuario del sistema | El refresco global excluye purpose=whatsapp. Exige monitorización de validez/revocación y renovación operativa, aunque no tenga expires_at. |

Referencias: [resolver de credenciales](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Services/Meta/Subscription/Whatsapp/MetaWhatsappCredentialResolver.php:12), [comprobación de permisos](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Services/Meta/Subscription/Whatsapp/MetaWhatsappSubscriptionService.php:261), [observador](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Observers/MetaWhatsappObserver.php:21), [job de baja](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Jobs/MetaWhatsappUnsubscribeJob.php:32).

Para uso de Cloud API y operaciones de mensajería, revisar whatsapp_business_messaging; para administrar WABA/suscripciones, whatsapp_business_management; para descubrir/administrar activos de negocios, business_management según operación. Validar acceso real del usuario del sistema a cada WABA, correspondencia del token con App ID, modo de publicación, revisión de aplicación y nivel de acceso exigido para servir negocios externos. Estos puntos requieren inspección del panel y llamadas autorizadas con la modalidad concreta; no están certificados por el repositorio.

**6.3. Páginas y formularios**

El proyecto suscribe /{PAGE_ID}/subscribed_apps con subscribed_fields=leadgen. Para funcionar también necesita el objeto page/campo leadgen en la aplicación, permisos para la página y acceso efectivo a sus leads.

La validación actual toma App ID del último token de usuario general activo; la baja toma un app token global. Si hay varias aplicaciones, podrían no corresponder con el page_access_token almacenado. syncPages() sin token específico también procesa solo un token general seleccionado, pese a que su comentario sugiere “todos”. Guardar varios tokens no garantiza descubrir las páginas de todos los negocios. [Descubrimiento](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Services/Meta/MetaLeadAdsSyncService.php:194), [suscripciones de página](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Services/Meta/Subscription/Pages/MetaPageSubscriptionLeadgenService.php:149).

Matriz de permisos a verificar por operación: leads_retrieval para recuperar datos; pages_manage_metadata para suscripciones; pages_show_list para descubrimiento; pages_read_engagement y permisos publicitarios según los endpoints y el acceso concedido. Revisar además Leads Access/CRM del negocio y requisitos de acceso avanzado. La lista exacta de requisitos para la aplicación real queda pendiente de contraste en el panel/documentación vigente, cuya página de recuperación no pudo descargarse durante esta revisión.

**6.4. Recepción segura y operación de colas**

Los GET comparan verify_token, pero **no encontré validación HMAC de los POST**. Guardar X-Hub-Signature-256 no valida su autenticidad. Validar el cuerpo crudo con el App Secret de la conexión, comparación de tiempo constante y rechazo antes de persistir/despachar. Con varias aplicaciones, separar callbacks por conexión o resolver de forma segura entre contextos permitidos; no confiar en un App ID declarado por el payload. El ejemplo oficial ilustra HMAC sobre el cuerpo crudo, aunque no debe copiarse literalmente sin revisar su código. [Ejemplo Meta de firma](https://github.com/fbsamples/whatsapp-api-examples/blob/main/signature-validation-with-webhooks-payloads/app.py).

Los receptores capturan varios errores y responden 200 incluso cuando no se almacenó/despachó correctamente. Si no quedó evento durable ni job, Meta recibe confirmación sin que exista recuperación local. Responder éxito después de persistencia durable y procesar con reintentos internos; ante fallo de persistencia sin respaldo, responder error reintentable.

La tabla genérica tiene processing_status=received, pero no encontré un ciclo implementado que la avance a procesado/fallido para estos flujos. Es almacenamiento de auditoría, no una bandeja de procesamiento completa. [Almacenamiento](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Services/Meta/MetaWebhookStorageService.php:117).

Deben operar scheduler y workers para: meta, tracking, default de integraciones, y las conexiones específicas meta_page_subscriptions, meta_whatsapp_subscriptions y meta_ad_account_subscriptions. Son conexiones/tablas separadas, no basta escuchar sus nombres desde una conexión equivocada. El comando dev de Composer ejecuta queue:listen sin declarar todas estas colas. [Configuración](C:/proyectos/app.leadsya.com/ecosistema_Leadya/config/queue.php:31), [programación](C:/proyectos/app.leadsya.com/ecosistema_Leadya/routes/console.php:73), [Composer](C:/proyectos/app.leadsya.com/ecosistema_Leadya/composer.json:55).

Ajustar timeout/retry_after a duración máxima real y evitar que jobs largos se reserven de nuevo mientras siguen ejecutándose. El cliente Graph permite 60 segundos por intento y múltiples páginas; los valores por defecto de la conexión general no acreditan una configuración operativa adecuada.

**7. Informe de API leads**

POST /api/leads usa ApiAuthMiddleware: exige X-Customer-ID y X-Auth-Token, comprueba cliente activo y compara el hash. CustomerService sustituye customer_id del body por el header. En esta ruta el cliente autenticado debe seguir siendo la autoridad; compartir una página/WABA no debe cambiarlo. [Middleware](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Middleware/ApiAuthMiddleware.php:21), [aplicación del header](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Services/Customer/CustomerService.php:14).

El controlador valida, crea, registra historial, clasifica Meta/Google, despacha conversiones y encola integraciones. La API no usa el resolver WhatsApp ni el de formularios: recibir campos de WhatsApp no equivale al flujo de webhook. [LeadController](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Controllers/LeadController.php:68).

Riesgos actuales relevantes:

- POST /api/leads-formualario apunta al mismo store sin ApiAuthMiddleware. Puede aceptar customer_id desde body/header, validado solo por existencia. Debe definirse y protegerse como endpoint público de captación, con credencial acotada/origen confiable y límites; no utilizarlo como alternativa irrestricta a la API autenticada.
- La API crea directamente, sin clave idempotente. Un reintento del integrador puede duplicar lead, CRM y conversiones.
- La API valida identificadores de anuncio/WABA como cadenas, sin validar pertenencia al cliente. Añadir esta validación cuando se declaren activos relacionados, sin exigir página o formulario a leads que no los usan.
- Los identificadores estructurados de formulario/meta_lead_id no aparecen en validateLeadRequest(). No asumir que la API actual permite importar de forma equivalente un lead instantáneo o deduplicarlo contra la sincronización.
- GET /api/leads y show/update/destroy tienen excepción de acceso global para customer_id=1. Es una convención de autorización que debe mantenerse explícita y restringida.
- /api/meta/sync-leads autoriza comparando fb_pixel_id y encola sincronización global. Un Pixel ID no debe tratarse como secreto; cambiar a autenticación real y alcance explícito.
- /api/customers/{id}/regenerate-token también está declarado fuera del grupo autenticado: revisar la protección antes de ampliar acceso a clientes.

Referencias: [rutas API](C:/proyectos/app.leadsya.com/ecosistema_Leadya/routes/api.php:28), [validación del lead](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Services/Lead/LeadService.php:38), [listado y excepción cliente 1](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Services/Lead/LeadService.php:21).

**8. Modelo objetivo y reglas que no deben romperse**

| Componente | Propuesta |
|---|---|
| meta_pages | Catálogo único por Page ID externo. Estado técnico del activo separado del estado de uso por cliente. |
| customer_meta_page | Asociación N:M, única por cliente/página, con estado y auditoría. |
| meta_forms | Catálogo único por Form ID externo, perteneciente a una página. |
| Asignación de formulario | Un cliente activo por formulario, con vigencia y versión de regla. |
| meta_wabas | WABA ID único; datos de cuenta. Puede evolucionarse la tabla actual para evitar una renombración prematura. |
| meta_whatsapp_numbers | Phone Number ID único, relación a WABA, display_phone_number y estado técnico. |
| Asociación cliente–número | N:M, estado y reglas de distribución. La asociación a WABA puede mantenerse para administración, sin equivaler automáticamente a recibir todos sus leads. |
| meta_connections | App ID, negocio, usuario del sistema y credencial; acceso a activos comprobado. |
| Suscripciones | Filas únicas por aplicación–tipo de activo–ID externo, estado deseado/observado, callback y último error. |
| Eventos y entregas | Registro fuente idempotente; decisión de cliente, versión de regla, estados y reintentos. |
| leads | Un cliente por lead; conservar relación al evento fuente y al número/formulario que lo originó. |
| Outbox de efectos | Entregas a integración/conversión idempotentes, creadas junto al lead en transacción. |

Una página o WABA compartida no obliga a compartir leads. Mantener leads.customer_id como propietario único simplifica los contratos CRM, estados, embudos y reportes. Una entrega deliberada a varios clientes debe configurarse como política distinta, con permisos y trazabilidad; no surgir del simple hecho de compartir activo.

Para formularios distintos, los mapeos ya están separados por formulario. Evitar una migración innecesaria de mapeos por cliente si se conserva destinatario único. Para los números compartidos, una cuenta publicitaria compartida puede contener campañas de varios clientes: un default por cuenta es demasiado amplio para separarlas.

**9. Mapa de dependencias e impacto**

| Área revisada | Cambio o verificación necesaria |
|---|---|
| Migraciones/modelos Customer, MetaPage, MetaForm, MetaWhatsapp, MetaAccessToken, Lead | Cardinalidades, índices, FKs, estados por asociación, IDs externos separados de internos. |
| CustomerController y vistas de clientes | Sustituir reasignación destructiva por attach/detach; elegir formularios y números; mostrar conflictos y destinatarios. |
| MetaPageController/Request y vistas pages | Selección múltiple, permisos sobre asociaciones, distinción baja local/baja global. |
| MetaFormController/Request y form_field_mappings | Mostrar/validar cliente del formulario; conservar aislamiento de mapeos; evitar mover formularios entre páginas sin validación. |
| MetaLeadAdsSyncService y jobs SyncMetaPages/Forms/Leads/PageLeads | Selección por asignación, contexto de token, idempotencia, recuperación y eventos tardíos. |
| MetaWhatsappReferralLeadService/Job | Resolver por número y regla; retirar fallbacks históricos ambiguos; almacenar message_id/phone_number_id; estado de fallo recuperable. |
| Ambos controladores webhook y MetaWebhookStorageService | Firma, persistencia durable, lotes con varias WABAs, deduplicación semántica y seguimiento por mensaje. |
| MetaWhatsappController/CustomerController y vistas whatsapps | Separar cuenta/número; credenciales por conexión; estado de relación y reglas comerciales. |
| Servicios de suscripción y observadores Page/Whatsapp | Agregar demanda de todas las asociaciones, baja solo al dejar de necesitarse, locks y contexto de aplicación. |
| Jobs de suscripción, pantallas de fallidos y PruneMetaSubscriptionFailedJobs | Claves de recurso por aplicación/activo, reintentos seguros, no borrar evidencia pendiente antes de resolución. |
| MetaAssetStatusSyncService e historiales de página/cuenta | Estado físico único y acceso para clientes autorizados; hoy páginas se filtran por customer_id y el evento de cuenta elige un cliente mediante el default WhatsApp. |
| LeadController, CustomerService, ApiAuthMiddleware, rutas API | Mantener cliente autenticado, comprobar pertenencia, proteger rutas públicas y agregar idempotencia. |
| LeadService/LeadFunnelHistoryService/CRM states | Valor inicial y embudo correctos por destinatario; no trasladar historial automáticamente al cambiar asociaciones. |
| IntegrationService/ProcessLeadIntegrationsJob | Aislar configuración y destinos; verificar pertenencia al ejecutar; recuperación por entrega; decidir envíos WhatsApp. |
| FacebookConversionsService/SendLeadToFacebook | Dataset/pixel y evento correctos; congelar destinatario y clave de conversión; evitar doble envío por replays. |
| MetaInsightsSyncService/AdAccountSync y GeneralLeadsAdsLiveMetricsService | Credenciales por activo y separación de campañas; métricas de cuentas compartidas no equivalen a gasto imputable a cada cliente. |
| Dashboards gerencial/general, Livewire, exportaciones y conectores IA | Seguir filtrando por propietario del lead; incluir nuevas dimensiones sin multiplicar filas en joins; invalidar cachés tras cambiar asociaciones. |
| Scheduler, workers, caché/locks, despliegue | Escuchas de todas las conexiones, recuperación temporal, orden de despliegue y compatibilidad con jobs antiguos. |
| Pruebas de webhooks, sincronización, credenciales, pivots y conversiones | Incorporar escenarios múltiples, ambigüedad, fallos parciales y separación de datos. |

El punto común de envíos incluye Google Sheets, Kommo/pipelines, Atom, Lety, Zoho, Freshworks, Salesforce, Monday, HubSpot y GoHighLevel, entre otros handlers disponibles. Una asignación incorrecta puede propagarse fuera de Leadya. La revisión de esta dependencia se hizo sobre el contrato y despacho común, no como auditoría exhaustiva del API remoto de cada proveedor. [Dispatcher](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Services/Integration/IntegrationService.php:65).

SendLeadToFacebook guarda customerId en el job y lo usa al ejecutarse, aunque el lead pudiera haber cambiado de cliente. FacebookConversionsService elige dataset WhatsApp, dataset de formularios o pixel desde ese cliente; el event_id depende del updated_at del lead. Se debe preservar el destinatario de la entrega y generar claves estables por evento/transición, además de comprobar coherencia del job con su registro fuente. [Job de conversión](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Jobs/SendLeadToFacebook.php:47), [selección de credenciales](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Services/Convention/FacebookConversionsService.php:329).

El modelo Customer conserva un dataset de formularios, uno de WhatsApp y un pixel por cliente. Si muchos activos de un mismo cliente necesitan datasets diferentes, será necesaria configuración por conexión/origen; si todos usan el mismo dataset, el modelo actual puede seguir sirviendo.

Los informes ya filtran leads.customer_id. En cambio, métricas vivas pueden incluir cuentas completas asociadas a un cliente. Compartir una cuenta y sumar ambos reportes puede contar gasto dos veces o calcular CPL con gasto total y leads parciales. Definir atribución por anuncio/campaña o mostrar explícitamente gasto compartido. [Consulta de leads](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Services/GeneralLeads/GeneralLeadsLeadQuery.php:17), [cuentas para métricas](C:/proyectos/app.leadsya.com/ecosistema_Leadya/app/Http/Services/GeneralLeads/GeneralLeadsAdsLiveMetricsService.php:56).

**10. Matriz de riesgos**

Severidad expresa impacto potencial; no es una medición de incidentes de producción.

| Riesgo | Severidad | Situación | Mitigación prioritaria |
|---|---|---|---|
| Leads de formularios enviados a otro cliente al reasignar página | Crítica | Lógica actual comprobada | Destinatario explícito por formulario; migración de asociaciones. |
| WhatsApp asignado al cliente más reciente/predeterminado incorrecto | Crítica | Lógica actual comprobada | Número exacto + reglas; pendientes ante conflicto. |
| Exposición de leads en CRM/dataset equivocado | Crítica | Consecuencia del enrutamiento | Resolver antes de enviar; outbox y auditoría de destinatario. |
| POST de webhook sin autenticidad verificada | Crítica | Falta de validación en código revisado | Firma y contexto de aplicación antes de procesar. |
| Captación pública para customer_id arbitrario | Alta | Ruta actual fuera de middleware | Credencial acotada, controles por origen y límites. |
| Baja de WABA/página usada por otros | Crítica | Estado y baja globales actuales | Demanda agregada y distinción asociación/activo. |
| Duplicados por reintentos o concurrencia | Alta | WhatsApp/API sin idempotencia suficiente | Índices fuente y transacciones; efectos idempotentes. |
| Pérdidas con 200 sin persistencia o errores capturados | Alta | Comportamiento actual | Bandeja durable, fallos por evento, reintentos verificables. |
| Leads históricos asignados con reglas nuevas | Alta | Se agravaría al compartir/reasignar | Vigencia y snapshot de decisión; preservar historia. |
| Tokens/aplicaciones intercambiados entre negocios | Alta | Fallbacks y estados globales | Conexiones explícitas y permisos verificados por activo. |
| Borrado en cascada de formularios y mapeos | Alta | Esquema actual | Desvincular relaciones; proteger catálogo compartido e historial. |
| Métricas/CPL y conversiones duplicadas o mal atribuidas | Alta | Dependencia de cuentas/destinatarios | Atribución comercial y claves de evento estables. |
| Bloqueos de cola, límites Graph y llamadas redundantes | Media/alta | Aumenta con más clientes | Descargar una vez por activo/formulario; paginar y medir. |
| Cambiar silenciosamente envíos de WhatsApp a CRM | Alta | Diferencia funcional actual | Configuración y pruebas de aceptación específicas. |
| Registros/tokens/payloads visibles a usuarios no autorizados | Alta | Debe reevaluarse al abrir acceso a clientes | Policies por asociación y ocultación de secretos/datos ajenos. |

Las rutas de administración revisadas están bajo auth y varios FormRequest autorizan true; eso no acredita segregación por cliente. Si las pantallas son solo de personal interno, documentar ese supuesto. Si acceden clientes finales, implementar permisos por activo/asociación antes de exponerlos.

**11. Ventajas y costes**

Permitir el modelo propuesto evita duplicar páginas y credenciales, permite separar líneas de negocio por formulario, facilita centralizar captación y posibilita varios números por cliente. Un catálogo único reduce consultas repetidas y da una visión completa del estado técnico.

El coste principal es operar reglas explícitas y resolver ambigüedades. También aumentan la importancia del control de acceso, auditoría, recuperación de eventos y coordinación de bajas. Compartir credenciales o una suscripción extiende el alcance de un fallo; separar los estados permite limitar su efecto.

Viabilidad: alta. Riesgo de implementar únicamente una tabla pivot de páginas: alto. Riesgo de seguir ampliando WhatsApp con los fallbacks actuales: alto. La parte más compleja es la distribución de números compartidos y sus efectos externos, no la cardinalidad de páginas.

**12. Plan de cambio y criterios de aceptación**

1. Inventariar producción en lectura: páginas, formularios activos, clientes actuales, WABAs/números, conexiones/App ID, suscripciones efectivas, duplicados y jobs pendientes. Identificar formularios sin mapeo o destinatario y casos compartidos reales.
2. Añadir tablas/campos sin quitar los actuales. Migrar cada página actual a su asociación y cada formulario al cliente actual de la página, sujeto a revisión comercial: el propietario actual podría ser consecuencia de una reasignación previa.
3. Separar WABAs/números y cargar los números reales. No inventar Phone Number ID faltantes a partir del teléfono visible. Validar pertenencia con Meta.
4. Ejecutar el nuevo resolver en modo de comparación sin envíos: registrar diferencias con el actual y revisar cada ambigüedad.
5. Implementar persistencia durable, firma, índices idempotentes y outbox; luego habilitar distribución por formulario y por número mediante flags.
6. Adaptar pantallas, filtros, historiales, cachés y suscripciones agregadas. Los jobs de baja deben releer el estado deseado antes de llamar a Meta.
7. Pilotar página compartida con F1→A/F2→B y una WABA con N1/N2; después probar un número compartido con reglas inequívocas.
8. Migrar gradualmente; retirar lecturas legacy solo tras reconciliar. Revertir mediante flags y conservar tablas/eventos. Tras permitir varios clientes por página, volver a un solo customer_id ya no es una reversión de datos sin pérdida: no colapsar automáticamente relaciones.

| Prueba necesaria | Resultado esperado |
|---|---|
| F1 y F2, misma página, clientes distintos | Leads y CRM separados, una suscripción de página por aplicación. |
| Cliente con varias páginas | Importación completa sin quitar asociaciones de otros. |
| Formulario nuevo sin asignación/mapeo | Pendiente visible, sin cliente inferido ni envío externo. |
| Reasignación con eventos antiguos en cola | Destino conforme a regla de vigencia, sin mover leads existentes. |
| Dos números dentro de una WABA | Aislamiento por Phone Number ID y WABA validada. |
| Un número compartido con anuncio conocido | Regla específica y destinatario autorizado para ese número. |
| Un número compartido con anuncio desconocido | Pendiente o default explícito; nunca “último cliente”. |
| Mensaje orgánico y estados de entrega | Comportamiento definido; no confundirlos con captación de anuncios. |
| Mensaje repetido por ambos endpoints y dos workers | Una creación y una entrega por destino autorizado. |
| Varias entry/WABAs en un payload | Procesamiento independiente de cada mensaje y asociación. |
| Dos negocios Meta en la misma aplicación | Cada WABA recibe eventos con su acceso validado. |
| Varias aplicaciones en una WABA | Estados separados por App ID, firmas y callbacks correctos. |
| Desvincular A mientras B sigue activo | La suscripción permanece; B continúa recibiendo. |
| Desactivar y reactivar antes de ejecutar baja pendiente | El job obsoleto no cancela la suscripción necesaria. |
| Falla DB/cola/CRM tras recepción | Evento recuperable, sin éxito silencioso ni duplicados. |
| Token revocado de una conexión | Error aislado y visible, sin usar otro negocio por defecto. |
| API con header A/body B | Crear en A con autenticación válida; no aceptar activos ajenos. |
| API repetida con misma clave idempotente | Un lead y una respuesta recuperable. |
| Formularios paginados y ventana de caída prolongada | Recuperación completa y sin saltos temporales. |
| Reportes de activos/cuentas compartidas | Sin multiplicación de leads ni imputación doble de gasto. |
| Lead reasignado con conversión/CRM en cola | Entrega coherente con política histórica y registro fuente. |

**Recomendación de alcance:** implementar primero páginas compartidas con formularios de destinatario único. En paralelo al diseño, corregir la ambigüedad del resolver WhatsApp y la persistencia de eventos. Habilitar números compartidos después de separar WABA/número y aprobar reglas comerciales explícitas. La recepción de un webhook, la creación correcta del lead y su entrega a CRM/Meta deben medirse como tres resultados distintos.
