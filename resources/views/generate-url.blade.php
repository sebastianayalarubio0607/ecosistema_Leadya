<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matrix URL Pro</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        .bg-leadsya-gradient {
            background: linear-gradient(135deg, #a06bd9 0%, #6b23c8 20%, #00A99D 100%);
        }

        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .custom-scrollbar::-webkit-scrollbar { height: 8px; width: 8px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.08); }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.35); border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255, 255, 255, 0.5); }
    </style>
</head>
@php
    $fieldLabel = function (string $label, bool $required, string $description): string {
        $badge = $required ? '<span class="ml-1 text-rose-200">*</span>' : '';

        return '<span class="inline-flex items-center gap-1">'.$label.$badge.'<span title="'.e($description).'" class="ml-1 inline-flex h-4 w-4 cursor-help items-center justify-center rounded-full border border-white/25 text-[10px] text-white/70">i</span></span>';
    };
@endphp
<body class="antialiased selection:bg-[#00A99D] selection:text-white">
    <div class="min-h-screen bg-leadsya-gradient text-white">
        <header class="sticky top-0 z-50 border-b border-white/10 bg-white/10 backdrop-blur px-6 py-4">
            <div class="flex w-full items-center justify-between gap-4">
                <a href="{{ url('/') }}" class="flex items-center gap-3">
                    <x-application-logo class="block w-24 fill-current text-white" />
                    <div>
                        <h1 class="text-xl font-bold">Matrix Multi-URL</h1>
                        <p class="text-xs font-medium text-white/70">Generador masivo e independiente</p>
                    </div>
                </a>

                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}"
                           class="rounded-full border border-white/20 bg-white/10 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-white/20">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ Route::has('login') ? route('login') : url('/') }}"
                           class="rounded-full border border-white/20 bg-white/10 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-white/20">
                            Iniciar sesion
                        </a>
                    @endauth

                    <button onclick="addRow()" class="inline-flex items-center gap-2 rounded-full bg-[#00A99D] px-5 py-2.5 text-sm font-bold text-white shadow-lg transition hover:bg-[#48c3a1] active:scale-95">
                        <span class="text-lg leading-none">+</span>
                        Nueva Campana
                    </button>
                </div>
            </div>
        </header>

        <main class="w-full ">
            <div class="glass-effect  p-5 shadow-2xl">
                <div class="mb-5 flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h2 class="text-2xl font-extrabold">Generador de URLs</h2>
                        <p class="text-sm text-white/75">Completa los campos requeridos y copia la URL generada.</p>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs text-white/70">
                        <span class="rounded-full border border-oreande/15 bg-white/10 px-3 py-1">* Requerido</span>
                        <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1">Opcional segun source</span>
                    </div>
                </div>

                    <table class="w-full min-w-[1860px] border-collapse ">
                        <thead>
                            <tr class="bg-slate-950/60 text-left text-[11px] font-bold uppercase tracking-[0.1em] text-white/70">
                                <th class="p-4 w-[320px]">Base URL <span class="text-rose-200">*</span></th>
                                <th class="p-4 w-[150px]">{!! $fieldLabel('Source', true, 'Es la parte fundamental: de aqui se desprenden configuraciones de tracking y funcionalidades de LQ.') !!}</th>
                                <th class="p-4 w-[150px]">{!! $fieldLabel('Origen', true, 'Se habilita despues de seleccionar Source y muestra solo origenes activos de ese Source.') !!}</th>
                                <th class="p-4 w-[150px]">{!! $fieldLabel('tipo', true, 'Se habilita despues de seleccionar Origen y muestra solo plataformas activas del Source seleccionado.') !!}</th>
                                <th class="p-4 w-[150px]">{!! $fieldLabel('Site Link', false, 'Opcional cuando el Source seleccionado es Google. No aplica para Meta.') !!}</th>
                                <th class="p-4 w-[150px]">{!! $fieldLabel('Geo', true, 'Catalogo activo de ubicaciones o mercados.') !!}</th>
                                <th class="p-4 w-[150px]">{!! $fieldLabel('Idioma', false, 'Catalogo activo de idiomas. No bloquea la generacion de la URL.') !!}</th>
                                <th class="p-4 w-[150px]">{!! $fieldLabel('Campaign Objective', false, 'Catalogo activo de objetivos de campana. No bloquea la generacion de la URL.') !!}</th>
                                <th class="p-4 min-w-[320px] border-l border-white/10 bg-slate-950/70">Resultados</th>
                                <th class="p-4 w-[44px]"></th>
                            </tr>
                        </thead>
                        <tbody id="matrixBody" class="divide-y divide-white/10 bg-slate-950/20"></tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        const optionsData = @json($options);
        const googleSourceIds = new Set(@json($googleSourceIds ?? []));

        function addRow() {
            const tbody = document.getElementById('matrixBody');
            const id = Date.now();
            const row = document.createElement('tr');
            row.id = `row-${id}`;
            row.className = 'transition hover:bg-white/5';

            row.innerHTML = `
                <td class="p-3">
                    <input type="text" placeholder="https://ejemplo.com"
                        class="base-url w-full rounded-xl border border-white/10 bg-slate-950/50 p-2 text-xs text-white placeholder-white/35 outline-none focus:border-[#00A99D]"
                        oninput="updateResults(${id})">
                </td>
                <td class="p-3">${createObjectSelect('source', optionsData.source, id, false, 'Seleccionar source')}</td>
                <td class="p-3">${createObjectSelect('origin', [], id, true, 'Selecciona source')}</td>
                <td class="p-3">${createObjectSelect('platform', [], id, true, 'Selecciona origen')}</td>
                <td class="p-3">${createObjectSelect('site_link', [], id, true, 'Selecciona source')}</td>
                <td class="p-3">${createKeyValueSelect('geo', optionsData.geo, id, false, 'Seleccionar geo')}</td>
                <td class="p-3">${createKeyValueSelect('language', optionsData.language, id, false, 'Opcional')}</td>
                <td class="p-3">${createKeyValueSelect('campaign_objective', optionsData.campaign_objective, id, false, 'Opcional')}</td>
                <td class="border-l border-white/10 bg-slate-950/30 p-3">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <input type="text" readonly class="result-url w-full rounded-lg border border-white/10 bg-white/10 p-2 text-[10px] text-white/75 placeholder-white/35" placeholder="Esperando datos...">
                            <button onclick="copyToClipboard(this)" class="shrink-0 rounded-lg bg-[#00A99D]/20 px-3 py-2 text-[9px] font-bold uppercase tracking-wider text-white transition hover:bg-[#00A99D]">Copiar</button>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="text" readonly class="result-title w-full rounded-lg border border-white/10 bg-white/10 p-2 text-[10px] font-semibold text-white placeholder-white/35" placeholder="Titulo...">
                            <button onclick="copyToClipboard(this)" class="shrink-0 rounded-lg bg-white/10 px-3 py-2 text-[9px] font-bold uppercase tracking-wider text-white transition hover:bg-white/20">Copiar</button>
                        </div>
                    </div>
                </td>
                <td class="p-3 text-center">
                    <button onclick="removeRow(${id})" class="rounded-full p-2 text-white/40 transition hover:bg-rose-500/20 hover:text-rose-100" title="Eliminar fila">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </td>
            `;

            tbody.appendChild(row);
        }

        function createObjectSelect(name, data, id, disabled, placeholder) {
            const disabledAttr = disabled ? 'disabled' : '';
            let html = `<select onchange="handleSelectChange(${id}, '${name}')" class="select-${name} w-full rounded-xl border border-white/10 bg-slate-950/50 p-2 text-xs text-white outline-none focus:border-[#00A99D] disabled:cursor-not-allowed disabled:opacity-45" ${disabledAttr}>`;
            html += `<option value="">${escapeHtml(placeholder)}</option>`;

            data.forEach((item) => {
                html += `<option value="${escapeHtml(item.id)}" data-code="${escapeHtml(item.code)}" data-label="${escapeHtml(item.label)}">${escapeHtml(item.label)}</option>`;
            });

            return html + '</select>';
        }

        function createKeyValueSelect(name, data, id, disabled, placeholder) {
            const disabledAttr = disabled ? 'disabled' : '';
            let html = `<select onchange="updateResults(${id})" class="select-${name} w-full rounded-xl border border-white/10 bg-slate-950/50 p-2 text-xs text-white outline-none focus:border-[#00A99D] disabled:cursor-not-allowed disabled:opacity-45" ${disabledAttr}>`;
            html += `<option value="">${escapeHtml(placeholder)}</option>`;

            Object.entries(data).forEach(([code, label]) => {
                html += `<option value="${escapeHtml(code)}" data-label="${escapeHtml(label)}">${escapeHtml(label)}</option>`;
            });

            return html + '</select>';
        }

        function handleSelectChange(id, name) {
            const row = document.getElementById(`row-${id}`);

            if (name === 'source') {
                resetSelect(row.querySelector('.select-origin'), 'Selecciona source');
                resetSelect(row.querySelector('.select-platform'), 'Selecciona origen');
                resetSelect(row.querySelector('.select-site_link'), 'Selecciona source');
                hydrateOrigins(row);
                hydrateSiteLinks(row);
            }

            if (name === 'origin') {
                resetSelect(row.querySelector('.select-platform'), 'Selecciona origen');
                hydratePlatforms(row);
            }

            updateResults(id);
        }

        function hydrateOrigins(row) {
            const sourceId = row.querySelector('.select-source').value;
            const select = row.querySelector('.select-origin');
            const origins = optionsData.origin.filter((origin) => origin.source_id === sourceId);
            fillObjectSelect(select, origins, sourceId ? 'Seleccionar origen' : 'Selecciona source', !sourceId);
        }

        function hydratePlatforms(row) {
            const sourceId = row.querySelector('.select-source').value;
            const originId = row.querySelector('.select-origin').value;
            const select = row.querySelector('.select-platform');
            const platforms = optionsData.platform.filter((platform) => platform.source_ids.includes(sourceId));
            fillObjectSelect(select, platforms, originId ? 'Seleccionar plataforma' : 'Selecciona origen', !originId);
        }

        function hydrateSiteLinks(row) {
            const sourceId = row.querySelector('.select-source').value;
            const select = row.querySelector('.select-site_link');
            const siteLinks = optionsData.site_link.filter((siteLink) => siteLink.source_id === sourceId);
            const isGoogle = googleSourceIds.has(sourceId);
            const placeholder = isGoogle ? 'Opcional para Google' : 'No aplica';
            fillObjectSelect(select, isGoogle ? siteLinks : [], sourceId ? placeholder : 'Selecciona source', !isGoogle || siteLinks.length === 0);
        }

        function fillObjectSelect(select, data, placeholder, disabled) {
            select.innerHTML = `<option value="">${escapeHtml(placeholder)}</option>`;
            data.forEach((item) => {
                const option = document.createElement('option');
                option.value = item.id;
                option.dataset.code = item.code;
                option.dataset.label = item.label;
                option.textContent = item.label;
                select.appendChild(option);
            });
            select.disabled = disabled;
        }

        function resetSelect(select, placeholder) {
            select.innerHTML = `<option value="">${escapeHtml(placeholder)}</option>`;
            select.disabled = true;
        }

        function updateResults(id) {
            const row = document.getElementById(`row-${id}`);
            const baseUrl = row.querySelector('.base-url').value.trim().replace(/\/+$/, '');

            const sourceEl = row.querySelector('.select-source');
            const originEl = row.querySelector('.select-origin');
            const platformEl = row.querySelector('.select-platform');
            const siteLinkEl = row.querySelector('.select-site_link');
            const geoEl = row.querySelector('.select-geo');
            const langEl = row.querySelector('.select-language');
            const campaignObjectiveEl = row.querySelector('.select-campaign_objective');

            const isGoogle = googleSourceIds.has(sourceEl.value);

            if (baseUrl && sourceEl.value && originEl.value && platformEl.value && geoEl.value) {
                const source = selectedData(sourceEl);
                const origin = selectedData(originEl);
                const platform = selectedData(platformEl);
                const siteLink = selectedData(siteLinkEl);
                const geo = selectedData(geoEl);
                const language = selectedData(langEl);
                const campaignObjective = selectedData(campaignObjectiveEl);

                const leadParts = [origin.code, platform.code, geo.code];
                if (language.code) leadParts.push(language.code);
                const lead = leadParts.join('-');

                const titleParts = [source.label, origin.label, platform.label, geo.label];
                if (language.label) titleParts.push(language.label);
                if (siteLink.label) titleParts.push(siteLink.label);
                row.querySelector('.result-title').value = titleParts.join(' | ');

                const params = [
                    ['effective_lead', lead],
                    ['gad_source', source.code],
                    ['campaign_origin', origin.code],
                    ['platform', platform.code],
                    ['geo', geo.code],
                ];

                if (language.code) params.push(['language', language.code]);
                if (campaignObjective.code) params.push(['campaign_objective', campaignObjective.code]);
                if (siteLink.code) params.push(['site_link', siteLink.code]);

                let finalUrl = baseUrl + (baseUrl.includes('?') ? '&' : '/?') + params.map(([key, value]) => `${key}=${encodeURIComponent(value)}`).join('&');

                if (isGoogle) {
                    finalUrl += '&' + [
                        'utm_source=google',
                        'utm_medium=cpc',
                        'utm_campaign={campaignid}',
                        'utm_content={creative}',
                        'google_ad_id={creative}',
                        'google_adgroup_id={adgroupid}',
                        'google_campaign_id={campaignid}',
                        'matchtype={matchtype}',
                        'device={device}',
                    ].join('&');
                }

                row.querySelector('.result-url').value = finalUrl;
            } else {
                row.querySelector('.result-url').value = '';
                row.querySelector('.result-title').value = '';
            }
        }

        function selectedData(select) {
            if (!select.value) {
                return {
                    code: '',
                    label: '',
                };
            }

            const selected = select.selectedOptions[0];

            return {
                code: selected?.dataset?.code || select.value || '',
                label: selected?.dataset?.label || selected?.text || '',
            };
        }

        function copyToClipboard(btn) {
            const input = btn.previousElementSibling;
            if (input.value) {
                navigator.clipboard.writeText(input.value);
                const originalText = btn.innerText;
                btn.innerText = 'COPIADO';
                btn.classList.add('bg-emerald-500');
                setTimeout(() => {
                    btn.innerText = originalText;
                    btn.classList.remove('bg-emerald-500');
                }, 1200);
            }
        }

        function removeRow(id) {
            const row = document.getElementById(`row-${id}`);
            row.classList.add('opacity-0');
            setTimeout(() => row.remove(), 200);
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        window.onload = addRow;
    </script>
</body>
</html>
