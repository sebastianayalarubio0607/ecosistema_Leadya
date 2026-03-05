<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matrix URL Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { height: 8px; width: 8px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f8fafc; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-900">

    <div class="w-full min-h-screen flex flex-col">
        <header class="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between sticky top-0 z-50 shadow-sm">
            <div class="flex items-center gap-4">
                <div class="bg-indigo-600 p-2 rounded-lg shadow-indigo-200 shadow-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-slate-800">Matrix Multi-URL</h1>
                    <p class="text-xs text-slate-500 font-medium">Generador masivo e independiente</p>
                </div>
            </div>
            
            <button onclick="addRow()" class="group bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-bold transition-all flex items-center gap-2 shadow-lg shadow-indigo-100 active:scale-95">
                <span class="text-lg">+</span> Nueva Campaña
            </button>
        </header>

        <main class="flex-1 p-6">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full border-collapse min-w-[1400px]">
                        <thead>
                            <tr class="bg-slate-900 text-slate-300 text-[11px] uppercase tracking-[0.1em] font-bold">
                                <th class="p-4 text-left w-[220px]">Base URL</th>
                                <th class="p-4 text-left w-[160px]">Origen</th>
                                <th class="p-4 text-left w-[160px]">Plataforma</th>
                                <th class="p-4 text-left w-[140px]">Geo</th>
                                <th class="p-4 text-left w-[120px]">Idioma</th>
                                <th class="p-4 text-left w-[160px]">Servicio</th>
                                <th class="p-4 text-left w-[140px]">Site Link</th>
                                <th class="p-4 text-left bg-indigo-950 text-indigo-200 border-l border-indigo-900">Resultados</th>
                                <th class="p-4 text-center w-[60px]"></th>
                            </tr>
                        </thead>
                        <tbody id="matrixBody" class="divide-y divide-slate-100">
                            </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        const optionsData = @json($options);

        function addRow() {
            const tbody = document.getElementById('matrixBody');
            const id = Date.now();
            const row = document.createElement('tr');
            row.id = `row-${id}`;
            row.className = "hover:bg-slate-50/50 transition-colors group animate-in fade-in duration-300";

            row.innerHTML = `
                <td class="p-3">
                    <input type="text" placeholder="https://ejemplo.com" 
                        class="w-full p-2 border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 base-url" 
                        oninput="updateResults(${id})">
                </td>
                
                <td class="p-3">${createSelect('origin', optionsData.origin, id)}</td>
                <td class="p-3">${createSelect('platform', optionsData.platform, id)}</td>
                <td class="p-3">${createSelect('geo', optionsData.geo, id)}</td>
                <td class="p-3">${createSelect('language', optionsData.language, id)}</td>
                
                <td class="p-3">
                    <input type="text" maxlength="20" placeholder="Letras únicamente" 
                        class="w-full p-2 border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 service-input" 
                        oninput="normalizeInput(this); updateResults(${id})">
                </td>
                
                <td class="p-3">
                    <input type="text" maxlength="20" placeholder="opcional" 
                        class="w-full p-2 border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 sitelink-input" 
                        oninput="normalizeInput(this); updateResults(${id})">
                </td>

                <td class="p-3 bg-slate-50/30 border-l border-slate-100 min-w-[380px]">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2 group/btn">
                            <input type="text" readonly class="w-full p-1.5 text-[10px] bg-white border border-slate-200 rounded-md text-slate-500 result-url truncate" placeholder="Esperando datos...">
                            <button onclick="copyToClipboard(this)" class="shrink-0 bg-indigo-50 text-indigo-600 px-3 py-1.5 rounded-md hover:bg-indigo-600 hover:text-white transition-all text-[9px] font-bold uppercase tracking-wider">Copiar</button>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="text" readonly class="w-full p-1.5 text-[10px] bg-white border border-slate-200 rounded-md text-slate-800 font-semibold result-title truncate" placeholder="Título...">
                            <button onclick="copyToClipboard(this)" class="shrink-0 bg-slate-100 text-slate-600 px-3 py-1.5 rounded-md hover:bg-slate-800 hover:text-white transition-all text-[9px] font-bold uppercase tracking-wider">Copiar</button>
                        </div>
                    </div>
                </td>

                <td class="p-3 text-center">
                    <button onclick="removeRow(${id})" class="text-slate-300 hover:text-red-500 p-2 rounded-full hover:bg-red-50 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        }

        function createSelect(name, data, id) {
            let html = `<select onchange="updateResults(${id})" class="w-full p-2 border border-slate-200 rounded-lg text-xs outline-none focus:ring-2 focus:ring-indigo-500/20 select-${name}">`;
            html += `<option value="">---</option>`;
            for (const [code, label] of Object.entries(data)) {
                html += `<option value="${code}" data-label="${label}">${label}</option>`;
            }
            return html + `</select>`;
        }

        function normalizeInput(input) {
            // Eliminar números y especiales, minúsculas, espacios por guiones
            let val = input.value.replace(/[^a-zA-Z\s]/g, '');
            val = val.toLowerCase().replace(/\s+/g, '-');
            input.value = val;
        }

        function updateResults(id) {
            const row = document.getElementById(`row-${id}`);
            const baseUrl = row.querySelector('.base-url').value.trim().replace(/\/$/, '');
            
            const originEl = row.querySelector('.select-origin');
            const platformEl = row.querySelector('.select-platform');
            const geoEl = row.querySelector('.select-geo');
            const langEl = row.querySelector('.select-language');
            const service = row.querySelector('.service-input').value;
            const sitelink = row.querySelector('.sitelink-input').value;

            // Solo generar si los campos obligatorios están listos
            if (baseUrl && originEl.value && platformEl.value && geoEl.value && langEl.value && service) {
                
                // 1. Effective Lead
                const lead = `${originEl.value}-${platformEl.value}-${geoEl.value}-${langEl.value}-${service}`;
                
                // 2. Título (Usando el texto del select)
                const oL = originEl.selectedOptions[0].text;
                const pL = platformEl.selectedOptions[0].text;
                const gL = geoEl.selectedOptions[0].text;
                const lL = langEl.selectedOptions[0].text;
                const sL = service.charAt(0).toUpperCase() + service.slice(1).replace(/-/g, ' ');

                row.querySelector('.result-title').value = `${oL} | ${pL} | ${gL} | ${lL} | ${sL}`;

                // 3. URL
                let finalUrl = `${baseUrl}?effective_lead=${lead}&campaign_origin=${originEl.value}&platform=${platformEl.value}&geo=${geoEl.value}&language=${langEl.value}&services=${service}`;
                if (sitelink) finalUrl += `&site_link=${sitelink}`;
                
                row.querySelector('.result-url').value = finalUrl;
            } else {
                row.querySelector('.result-url').value = '';
                row.querySelector('.result-title').value = '';
            }
        }

        function copyToClipboard(btn) {
            const input = btn.previousElementSibling;
            if (input.value) {
                navigator.clipboard.writeText(input.value);
                const originalText = btn.innerText;
                btn.innerText = 'COPIADO';
                btn.classList.add('bg-emerald-600', 'text-white');
                setTimeout(() => {
                    btn.innerText = originalText;
                    btn.classList.remove('bg-emerald-600', 'text-white');
                }, 1200);
            }
        }

        function removeRow(id) {
            const row = document.getElementById(`row-${id}`);
            row.classList.add('opacity-0', '-translate-x-4');
            setTimeout(() => row.remove(), 200);
        }

        // Fila inicial al cargar
        window.onload = addRow;
    </script>
</body>
</html>