<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Services\Lead\LeadFunnelHistoryService;
use App\Jobs\SendLeadToFacebook;
use App\Jobs\SendLeadToGoogleAds;
use App\Models\CrmState;
use App\Models\Integration;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LeadCrmStateController extends Controller
{
    public function update(Request $request, string $public_key, LeadFunnelHistoryService $historyService)
    {
        /**
         *  Este endpoint es consumido por los webhooks de Kommo y Freshworks para actualizar el crm_state de los leads
         * en base a los cambios de estado que ocurren en esas plataformas. El payload esperado varía según la plataforma:
         *- Kommo: se espera un array de estados dentro de 'leads.status', donde cada estado tiene un 'id' (lead_id) y 'status_id'.
         * - Freshworks: se espera un payload con 'contact_id' y 'contact_contact_status_name' para identificar el lead y su nuevo estado.
         */
        // Validamos que la integración exista y obtenemos su ID para construir los crm_id de búsqueda y actualización
        $integration = Integration::query()
            ->where('public_key', $public_key)
            ->first();

        // Si no se encuentra la integración, respondemos con un error 404
        if (!$integration) {
            return response()->json(['message' => 'Integration not found'], 404);
        }

        // Procesamos el payload para determinar si es de Kommo o Freshworks y extraemos la información necesaria para actualizar los leads
        $data = $request->all();
        $statuses = data_get($data, 'leads.status', []);
        $isKommoPayload = is_array($statuses) && count($statuses) > 0;
        $isFreshworksPayload = $this->isFreshworksPayload($data);

        // Si el payload no corresponde a ninguno de los formatos esperados, registramos una advertencia y respondemos con un error 422
        if (!$isKommoPayload && !$isFreshworksPayload) {
            Log::warning('Webhook sin payload soportado para actualizar crm_state', [
                'public_key' => $public_key,
                'content_type' => $request->header('content-type'),
                'data_keys' => array_keys($data),
                'data_sample' => $data,
            ]);

            return response()->json([
                'message' => 'Invalid payload: leads.status not found'
            ], 422);
        }

        $updated = 0;
        $notFound = [];
        $crmIdPrefix = $integration->crmIdPrefix();

        // Procesamos los estados de Kommo, construyendo el crm_id a buscar y el nuevo crm_state a asignar, y actualizando los leads correspondientes
        if ($isKommoPayload) {
            // Iteramos sobre cada estado recibido en el payload de Kommo para actualizar el crm_state de los leads correspondientes
            foreach ($statuses as $item) {
                // Validamos que el item sea un array con la estructura esperada (id y status_id) antes de procesarlo
                if (!is_array($item)) {
                    continue;
                }

                // Extraemos el lead_id y el status_id del item, asegurándonos de que sean cadenas de texto para construir los crm_id correctamente
                $kommoLeadId = (string) ($item['id'] ?? '');
                // El status_id en Kommo representa el nuevo estado del lead, y se utiliza para construir el nuevo crm_state a asignar
                $statusId = (string) ($item['status_id'] ?? '');

                // Si el lead_id o el status_id están vacíos, no podemos procesar este item, por lo que lo saltamos
                if ($kommoLeadId === '' || $statusId === '') {
                    continue;
                }
                
                // Construimos el crm_id a buscar en la base de datos combinando el ID de la integración con el lead_id de Kommo, y el nuevo crm_state combinando el ID de la integración con el status_id
                $crmIdToFind = $crmIdPrefix . '-' . $kommoLeadId;
                // El nuevo crm_state se construye combinando el ID de la integración con el status_id recibido, lo que permite identificar el nuevo estado del lead en nuestro sistema
                $newCrmState = $integration->id . '-' . $statusId;

                // Procesamos el cambio de estado del lead utilizando una función dedicada que maneja la lógica de actualización y registro en el historial, y actualizamos los contadores de leads actualizados y no encontrados
                $this->processLeadStateChange(
                    $crmIdToFind,
                    $newCrmState,
                    $historyService,
                    $updated,
                    $notFound
                );
            }
        }

        // Procesamos el payload de Freshworks, construyendo el crm_id a buscar y resolviendo el nuevo crm_state a asignar a partir del nombre del estado recibido, y actualizando el lead correspondiente
        if ($isFreshworksPayload) {

            // Iteramos sobre cada estado recibido en el payload de Freshworks para actualizar el crm_state de los leads correspondientes
            $contactId = (string) data_get($data, 'contact_id');

            // El nombre del estado en Freshworks se utiliza para resolver el nuevo crm_state a asignar, lo que permite identificar el nuevo estado del lead en nuestro sistema
            $statusName = trim((string) data_get($data, 'contact_contact_status_name'));

            // Construimos el crm_id a buscar en la base de datos combinando el ID de la integración con el contact_id de Freshworks, lo que permite identificar el lead correspondiente en nuestro sistema
            $crmIdToFind = $crmIdPrefix . '-' . $contactId;

            // Resolvemos el nuevo crm_state a asignar utilizando una función dedicada que busca en la base de datos un estado que coincida con el nombre recibido, lo que permite manejar cambios de estado en Freshworks sin depender de IDs específicos
            $newCrmState = $this->resolveFreshworksCrmStateId($integration->id, $statusName);

            // Si no se pudo resolver un nuevo crm_state a partir del nombre recibido, registramos una advertencia para investigar posibles problemas de configuración o datos inconsistentes, ya que esto indica que el estado recibido no coincide con ningún estado configurado en nuestro sistema para esta integración
            if ($newCrmState === null) {
                //  Registramos una advertencia con detalles del payload recibido para facilitar la investigación y resolución del problema, incluyendo el ID de la integración, el public_key, el crm_id que se intentó encontrar, y el nombre del estado recibido que no pudo ser resuelto
                Log::warning('Freshworks webhook con estado no resoluble', [
                    'integration_id' => $integration->id,
                    'public_key' => $public_key,
                    'crm_id' => $crmIdToFind,
                    'freshworks_status_name' => $statusName,
                ]);
            } else {
                // Procesamos el cambio de estado del lead utilizando una función dedicada que maneja la lógica de actualización y registro en el historial, y actualizamos los contadores de leads actualizados y no encontrados
                $this->processLeadStateChange(
                    $crmIdToFind,
                    $newCrmState,
                    $historyService,
                    $updated,
                    $notFound
                );
            }
        }
// Respondemos con un resumen de la operación realizada, incluyendo el número de leads actualizados y los CRM IDs que no se encontraron, lo que permite al cliente del webhook tener visibilidad sobre el resultado de la solicitud y facilitar la identificación de posibles problemas o inconsistencias en los datos
        return response()->json([
            'message' => 'OK',
            'integration_id' => $integration->id,
            'updated' => $updated,
            'not_found' => $notFound,
        ]);
    }

    private function isFreshworksPayload(array $data): bool
    {
        // Validamos que el payload tenga las claves necesarias para identificarlo como un webhook de Freshworks, lo que nos permite procesar correctamente los cambios de estado de los leads provenientes de esta plataforma
        $contactId = data_get($data, 'contact_id');
        $statusName = data_get($data, 'contact_contact_status_name');

        // Para considerar que el payload es de Freshworks, necesitamos que tenga un contact_id válido (no nulo ni vacío) y un contact_contact_status_name que sea una cadena de texto no vacía, lo que indica que se trata de un cambio de estado de un contacto en Freshworks
        return $contactId !== null
            && $contactId !== ''
            && is_string($statusName)
            && trim($statusName) !== '';
    }

    // Esta función se encarga de resolver el ID del crm_state correspondiente a un estado de Freshworks a partir del nombre del estado recibido en el payload, lo que permite manejar cambios de estado en Freshworks sin depender de IDs específicos y facilita la configuración y mantenimiento de las integraciones con esta plataforma
    private function resolveFreshworksCrmStateId(int|string $integrationId, string $statusName): ?string
    {
        if ($statusName === '') {
            return null;
        }

        return CrmState::query()
            ->where('id', 'like', $integrationId . '-%')
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($statusName))])
            ->value('id');
    }

    // Esta función maneja la lógica de actualización del crm_state de un lead en base al crm_id a buscar y el nuevo crm_state a asignar, incluyendo la actualización del historial de cambios y el envío del lead a Facebook si corresponde, lo que centraliza la lógica de procesamiento de cambios de estado y facilita su mantenimiento y evolución
    private function processLeadStateChange(
        // El CRM ID a buscar se construye combinando el ID de la integración con el identificador del lead en la plataforma externa (Kommo o Freshworks), lo que permite identificar de manera única el lead correspondiente en nuestro sistema para actualizar su estado
        string $crmIdToFind,
        string $newCrmState,

        // El nuevo CRM State a asignar se construye combinando el ID de la integración con el nuevo estado recibido (status_id en Kommo o resuelto a partir del nombre en Freshworks), lo que permite identificar el nuevo estado del lead en nuestro sistema y actualizarlo correctamente
        LeadFunnelHistoryService $historyService,
        // El servicio de historial de cambios de funnel se utiliza para registrar cualquier cambio en el estado del lead que pueda afectar su posición en el funnel de ventas, lo que permite tener un registro completo de la evolución del lead a lo largo del tiempo y facilitar el análisis y la toma de decisiones basada en su comportamiento
        int &$updated,
        // El contador de leads actualizados se pasa por referencia para ser incrementado dentro de la función cada vez que se realiza una actualización exitosa del crm_state, lo que permite llevar un registro del número total de leads que han sido actualizados durante el procesamiento del payload
        array &$notFound
        // Los contadores de leads actualizados y no encontrados se pasan por referencia para ser actualizados dentro de la función, lo que permite llevar un registro del resultado de la operación a medida que se procesan los cambios de estado de los leads
    ): void {

    // Buscamos el lead correspondiente al CRM ID construido a partir del ID de la integración y el identificador del lead en la plataforma externa, lo que nos permite identificar el lead correcto en nuestro sistema para actualizar su estado
        $lead = Lead::query()
            ->where('crm_id', $crmIdToFind)
            ->first();
        // Si no se encuentra el lead correspondiente al CRM ID, registramos el CRM ID en el array de no encontrados para incluirlo en la respuesta final, lo que permite al cliente del webhook tener visibilidad sobre los leads que no pudieron ser actualizados debido a que no se encontraron en nuestro sistema
        if (!$lead) {
            $notFound[] = $crmIdToFind;
            return;
        }
        // Si el crm_state actual del lead es igual al nuevo crm_state que se desea asignar, no realizamos ninguna actualización ni registro en el historial, ya que esto indica que el lead ya se encuentra en el estado correcto y no es necesario realizar cambios, lo que optimiza el procesamiento al evitar actualizaciones innecesarias y reduce la cantidad de registros en el historial de cambios
        if ((string) $lead->crm_state === (string) $newCrmState) {
            return;
        }
        // Actualizamos el crm_state del lead con el nuevo valor construido a partir del ID de la integración y el nuevo estado recibido, lo que permite reflejar correctamente el cambio de estado del lead en nuestro sistema y mantenerlo sincronizado con la plataforma externa
        $lead->crm_state = $newCrmState;

        // Guardamos los cambios en la base de datos para que el nuevo crm_state se refleje en el lead, lo que permite que cualquier lógica dependiente del estado del lead (como reglas de automatización, segmentación, etc.) funcione correctamente con el nuevo estado asignado
        $lead->save();

        // Registramos el cambio de estado en el historial de funnel del lead para tener un registro completo de su evolución a lo largo del tiempo, lo que facilita el análisis y la toma de decisiones basada en su comportamiento y permite identificar patrones o tendencias en su interacción con las plataformas externas
        $historyService->recordIfFunnelChanged($lead);

        // Incrementamos el contador de leads actualizados para llevar un registro del número total de leads que han sido actualizados durante el procesamiento del payload, lo que permite incluir esta información en la respuesta final y tener visibilidad sobre el resultado de la operación
        $updated++;

        if ($this->isGoogleCampaignOrigin($lead->campaign_origin)) {
            try {
                SendLeadToGoogleAds::dispatch($lead->id, $newCrmState);
            } catch (\Throwable $exception) {
                Log::warning('No fue posible despachar SendLeadToGoogleAds desde cambio de crm_state', [
                    'lead_id' => $lead->id,
                    'campaign_origin' => $lead->campaign_origin,
                    'crm_state' => $newCrmState,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        // Si el origen de la campaña del lead es una de las plataformas de Facebook (fb, meta, ig, wa) o de mensajería (mg, th), y el nuevo crm_state asignado tiene un meta_event_id asociado, enviamos el lead a Facebook para actualizar su estado en esa plataforma, lo que permite mantener sincronizados los estados de los leads entre nuestro sistema y las plataformas de Facebook y aprovechar las funcionalidades de seguimiento y análisis que ofrecen estas plataformas para optimizar las campañas publicitarias y la gestión de leads
        if (!in_array($lead->campaign_origin, ['fb', 'meta', 'ig', 'wa', 'mg', 'th'], true)) {
            return;
        }

        // Si el lead no tiene un crm_state asignado después de la actualización, no es necesario enviar el lead a Facebook, ya que esto indica que el lead no tiene un estado válido para ser sincronizado con las plataformas de Facebook, lo que evita envíos innecesarios y posibles errores en la integración
        if (empty($lead->crm_state)) {
            return;
        }

        // Para asegurarnos de tener la información más actualizada del crm_state del lead, eliminamos la relación cargada previamente y la recargamos desde la base de datos, lo que garantiza que cualquier cambio en el crm_state se refleje correctamente en el lead antes de enviarlo a Facebook para su sincronización
        $lead->unsetRelation('crmState');

        // Cargamos la relación del crmState del lead para tener acceso a su información, incluyendo el meta_event_id asociado, lo que nos permite determinar si el estado del lead está configurado para ser sincronizado con las plataformas de Facebook y obtener el ID del evento de Facebook correspondiente para realizar la actualización en esa plataforma
        $lead->load('crmState');

        // Si el crm_state del lead no tiene un meta_event_id asociado, no es necesario enviar el lead a Facebook, ya que esto indica que el estado del lead no está configurado para ser sincronizado con las plataformas de Facebook, lo que evita envíos innecesarios y posibles errores en la integración debido a estados no compatibles
        if (empty($lead->crmState?->meta_event_id)) {
            return;
        }

        // Enviamos el lead a Facebook utilizando un job en segundo plano para actualizar su estado en esa plataforma, lo que permite mantener sincronizados los estados de los leads entre nuestro sistema y las plataformas de Facebook sin afectar el rendimiento de la solicitud actual y manejar posibles reintentos en caso de fallos en la comunicación con las APIs de Facebook
        SendLeadToFacebook::dispatch($lead->id, $lead->customer_id);
    }

    private function isGoogleCampaignOrigin(?string $campaignOrigin): bool
    {
        $origin = mb_strtolower(trim((string) $campaignOrigin));

        return in_array($origin, ['google', 'gads', 'google_ads', 'google ads'], true);
    }
}
