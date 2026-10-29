{{-- ═════════════════════════════════════════════════════════════════
     TICKET DETAIL SIDE PANEL
     Dibuka dari menu klik kanan baris list ticket ("Detail"). Data tiket
     diambil dari baris list yang sudah dimuat (allTickets), log perjalanan
     tiket dari GET /api/tickets/{id}/timeline (App\Http\Controllers\
     TicketTimelineController, bersumber audit_logs).
═════════════════════════════════════════════════════════════════ --}}
{{-- Layout kritis (posisi, lebar, z-index, transisi) memakai inline style, bukan class
     arbitrary Tailwind (max-w-[520px], z-[9996], ...): CSS Tailwind dibangun lewat Vite
     dan class arbitrary baru tidak ada di build sampai di-rebuild. --}}
<div id="ticketDetailOverlay" class="hidden"
    style="position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:9995;"
    onclick="closeTicketDetailPanel()"></div>

<aside id="ticketDetailPanel"
    class="bg-gray-50 shadow-2xl flex flex-col"
    style="position:fixed;top:0;right:0;height:100%;width:100%;max-width:540px;z-index:9996;transform:translateX(100%);transition:transform .3s ease;visibility:hidden;"
    aria-hidden="true">

    {{-- Header --}}
    <header class="bg-white border-b border-gray-200 shrink-0 px-6 pt-5 pb-4">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-widest">Ticket Detail</p>
                <div class="flex items-center gap-2.5 mt-1 flex-wrap">
                    <h3 id="tdpNumber" class="text-xl font-bold text-gray-900"></h3>
                    <span id="tdpStatus"></span>
                </div>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a id="tdpOpenLink" href="#" target="_blank"
                    class="inline-flex items-center justify-center px-3.5 py-2 border border-gray-300 rounded-lg text-xs font-semibold text-gray-700 hover:bg-gray-50 transition whitespace-nowrap">
                    Open Ticket
                </a>
                <button type="button" onclick="closeTicketDetailPanel()"
                    class="w-9 h-9 flex items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-700 transition"
                    aria-label="Close">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
        <p id="tdpDescription" class="text-sm text-gray-600 mt-3 leading-relaxed"
            style="display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;"></p>
    </header>

    {{-- Body --}}
    <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
        <section class="bg-white border border-gray-200 rounded-xl">
            <h4 class="px-5 py-3 text-xs font-bold text-gray-500 uppercase tracking-widest border-b border-gray-100">Overview</h4>
            <div id="tdpOverview" class="px-5 py-4 grid grid-cols-2 gap-x-4 gap-y-4"></div>
        </section>

        <section class="bg-white border border-gray-200 rounded-xl">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between gap-3">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-widest">Deliverable Documents</h4>
                <span id="tdpDocsSummary"></span>
            </div>
            <div id="tdpDocs" class="px-5 py-4"></div>
        </section>

        <section class="bg-white border border-gray-200 rounded-xl">
            <h4 class="px-5 py-3 text-xs font-bold text-gray-500 uppercase tracking-widest border-b border-gray-100">Team and Delivery</h4>
            <div id="tdpTeam" class="px-5 py-4 grid grid-cols-2 gap-x-4 gap-y-4"></div>
        </section>

        <section class="bg-white border border-gray-200 rounded-xl">
            <h4 class="px-5 py-3 text-xs font-bold text-gray-500 uppercase tracking-widest border-b border-gray-100">Effort and Schedule</h4>
            <div id="tdpSchedule" class="px-5 py-4 grid grid-cols-2 gap-x-4 gap-y-4"></div>
        </section>

        <section class="bg-white border border-gray-200 rounded-xl">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h4 class="text-xs font-bold text-gray-500 uppercase tracking-widest">Activity Log</h4>
                <span id="tdpLogCount" class="text-[11px] font-semibold text-gray-400"></span>
            </div>
            <div id="tdpLogs" class="px-5 py-4"></div>
        </section>
    </div>
</aside>

<script>
    (function() {
        const panel = document.getElementById('ticketDetailPanel');
        const overlay = document.getElementById('ticketDetailOverlay');
        let loadToken = 0; // abaikan respons log milik tiket yang sudah ditutup / diganti

        const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        } [c]));

        const STATUS = {
            open: ['Open', 'bg-blue-100 text-blue-700'],
            inprocess: ['In Progress', 'bg-yellow-100 text-yellow-700'],
            waiting_on_customer: ['Waiting on Customer', 'bg-amber-100 text-amber-700'],
            waiting_on_3rd_party: ['Waiting on 3rd Party', 'bg-indigo-100 text-indigo-700'],
            waiting_to_confirmation: ['Waiting to Confirmation', 'bg-teal-100 text-teal-700'],
            hold: ['Hold', 'bg-orange-100 text-orange-700'],
            cancelled: ['Cancelled', 'bg-gray-100 text-gray-600'],
            closed: ['Closed', 'bg-green-100 text-green-700'],
        };

        const PRIORITY = {
            'Very High': 'bg-red-100 text-red-700',
            'High': 'bg-orange-100 text-orange-700',
            'Medium': 'bg-yellow-100 text-yellow-700',
            'Low': 'bg-green-100 text-green-700',
        };

        // Warna titik timeline per jenis event audit.
        const EVENT_DOT = {
            created: '#16a34a',
            updated: '#2563eb',
            deleted: '#dc2626'
        };

        const EMPTY = '<span class="text-gray-300">-</span>';

        const pill = (label, cls) =>
            `<span class="inline-block px-2.5 py-0.5 rounded-md text-xs font-semibold ${cls}">${esc(label)}</span>`;

        function fmtDate(v) {
            if (!v) return null;
            const d = new Date(String(v).replace(' ', 'T'));
            if (isNaN(d)) return String(v);
            return d.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric'
            });
        }

        function fmtDateTime(v) {
            if (!v) return null;
            const d = new Date(String(v));
            if (isNaN(d)) return String(v);
            return fmtDate(d.toISOString()) + ' | ' +
                d.toLocaleTimeString('id-ID', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                }).replace('.', ':');
        }

        // Satu sel info: label kecil di atas, nilai di bawah. `wide` = melebar dua kolom.
        function field(label, valueHtml, wide) {
            return `<div class="min-w-0 ${wide ? 'col-span-2' : ''}">
                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">${esc(label)}</p>
                <div class="mt-1 text-sm font-medium text-gray-900 break-words">${valueHtml}</div>
            </div>`;
        }

        const text = v => (v === null || v === undefined || v === '') ? EMPTY : esc(v);

        function progressHtml(raw) {
            if (raw === null || raw === undefined || raw === '') return EMPTY;
            const pct = Math.max(0, Math.min(100, Math.round(parseFloat(raw) || 0)));
            const color = pct >= 75 ? '#22c55e' : pct >= 40 ? '#facc15' : '#f87171';
            return `<div class="flex items-center gap-2.5">
                <div class="flex-1 rounded-full bg-gray-100" style="height:6px;">
                    <div class="rounded-full" style="height:6px;width:${pct}%;background:${color};"></div>
                </div>
                <span class="text-xs font-semibold text-gray-700">${pct}%</span>
            </div>`;
        }

        function renderData(t) {
            const st = STATUS[t.status];
            document.getElementById('tdpNumber').textContent = '#' + (t.ticket_number || '');
            document.getElementById('tdpStatus').innerHTML = st ? pill(st[0], st[1]) : '';

            const desc = document.getElementById('tdpDescription');
            desc.textContent = t.description || '';
            desc.title = t.description || '';

            const doc = t.document_status === 'complete' ? pill('Complete', 'bg-green-100 text-green-700') :
                t.document_status === 'incomplete' ? pill('Incomplete', 'bg-amber-100 text-amber-700') : EMPTY;

            document.getElementById('tdpOverview').innerHTML = [
                field('Customer', text(t.customer?.customer_name), true),
                t.end_customer_name ? field('End Customer', text(t.end_customer_name), true) : '',
                field('Type', text(t.ticket_type)),
                field('Module', text(t.module)),
                field('Priority', t.ticket_priority ? pill(t.ticket_priority, PRIORITY[t.ticket_priority] || 'bg-gray-100 text-gray-600') : EMPTY),
                field('Scale', text(t.scale)),
                field('Document Status', doc),
            ].join('');

            document.getElementById('tdpTeam').innerHTML = [
                field('Ticket Lead', text(t.employee?.employee_name), true),
                field('Assign Delivery', text(t.delivery?.delivery_label || t.delivery?.delivery_name), true),
            ].join('');

            document.getElementById('tdpSchedule').innerHTML = [
                field('Progress', progressHtml(t.all_consultant_progress), true),
                field('Man Days', text(t.man_days)),
                field('Start Date', text(fmtDate(t.start_date))),
                field('End Date', text(fmtDate(t.end_date))),
                field('Created', text(fmtDateTime(t.created_at))),
            ].join('');
        }

        // Kelengkapan dokumen deliverable: tiap doc type MANDATORY ditandai sudah / belum
        // terupload (beserta file-nya); dokumen non-mandatory yang sudah ada ditampilkan di bawahnya.
        function renderDocs(mandatoryTypes, docs) {
            const key = v => String(v || '').trim().toUpperCase();
            const byType = {};
            docs.forEach(d => {
                (byType[key(d.doc_type)] = byType[key(d.doc_type)] || []).push(d);
            });
            const mandatoryKeys = new Set(mandatoryTypes.map(key));

            const sentPill = d => (d.status === 'Sent' || d.status === 'Sended') ?
                '<span class="px-1.5 py-0.5 rounded bg-green-50 text-green-700 font-semibold" style="font-size:10px;">Sent</span>' :
                '<span class="px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 font-semibold" style="font-size:10px;">Not sent</span>';

            const fileLine = d => `<div class="flex items-start justify-between gap-2 text-xs">
                <span class="text-gray-600 break-words min-w-0">${esc(d.file_name || '(no file name)')}</span>
                <span class="flex items-center gap-1.5 shrink-0 text-gray-400">${esc(d.upload_date || '')} ${sentPill(d)}</span>
            </div>`;

            const done = mandatoryTypes.filter(t => (byType[key(t)] || []).length).length;
            const summary = document.getElementById('tdpDocsSummary');
            if (!mandatoryTypes.length) {
                summary.innerHTML = '<span class="text-[11px] font-semibold text-gray-400">No mandatory set</span>';
            } else {
                const complete = done === mandatoryTypes.length;
                summary.innerHTML = pill(complete ? 'Complete' : `Incomplete: ${done}/${mandatoryTypes.length} uploaded`,
                    complete ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700');
            }

            const checkIcon = '<span class="shrink-0 inline-flex items-center justify-center rounded-full bg-green-100 text-green-700" style="width:20px;height:20px;"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></span>';
            const alertIcon = '<span class="shrink-0 inline-flex items-center justify-center rounded-full bg-amber-100 text-amber-700" style="width:20px;height:20px;"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 8v5m0 4h.01"/></svg></span>';

            const mandatoryRows = mandatoryTypes.map(t => {
                const files = byType[key(t)] || [];
                const ok = files.length > 0;
                return `<li class="flex items-start gap-3 py-2.5">
                    ${ok ? checkIcon : alertIcon}
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-gray-900">${esc(t)}</p>
                            <span class="text-xs font-semibold ${ok ? 'text-green-600' : 'text-amber-600'}">${ok ? 'Uploaded' : 'Not uploaded yet'}</span>
                        </div>
                        ${ok ? `<div class="mt-1 space-y-1">${files.map(fileLine).join('')}</div>` : ''}
                    </div>
                </li>`;
            }).join('');

            const others = docs.filter(d => !mandatoryKeys.has(key(d.doc_type)));
            const otherBlock = others.length ? `
                <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest ${mandatoryTypes.length ? 'mt-4 pt-4 border-t border-gray-100' : ''} mb-2">Other uploaded documents</p>
                <ul class="space-y-2">${others.map(d => `<li>
                    <p class="text-xs font-semibold text-gray-700">${esc(d.doc_type)}</p>${fileLine(d)}</li>`).join('')}</ul>` : '';

            const box = document.getElementById('tdpDocs');
            if (!mandatoryTypes.length && !others.length) {
                box.innerHTML = '<p class="text-sm text-gray-400 text-center py-4">No mandatory documents set and nothing uploaded yet.</p>';
                return;
            }
            box.innerHTML = (mandatoryTypes.length ? `<ul class="divide-y divide-gray-100">${mandatoryRows}</ul>` : '') + otherBlock;
        }

        function initials(name) {
            const parts = String(name || '?').trim().split(/\s+/).filter(Boolean);
            return ((parts[0] || '?')[0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
        }

        function changeLine(c) {
            const val = (c.from === null || c.from === undefined) ?
                `<span class="text-gray-900 font-medium">${esc(c.to)}</span>` :
                `<span class="text-gray-400 line-through">${esc(c.from)}</span>
                   <span class="text-gray-400">&rarr;</span>
                   <span class="text-gray-900 font-medium">${esc(c.to)}</span>`;
            return `<div class="text-xs leading-relaxed break-words">
                <span class="text-gray-500">${esc(c.label)}</span>
                <span class="text-gray-300">&middot;</span> ${val}
            </div>`;
        }

        function renderLogs(entries) {
            const box = document.getElementById('tdpLogs');
            document.getElementById('tdpLogCount').textContent = entries.length ? entries.length + ' entries' : '';

            if (!entries.length) {
                box.innerHTML = '<p class="text-sm text-gray-400 text-center py-8">No activity recorded for this ticket yet.</p>';
                return;
            }

            // Kelompokkan per tanggal ("Senin, 12 Okt 2026 | 12:53" -> tanggal | jam), urutan terbaru di atas.
            const groups = [];
            entries.forEach(e => {
                const [date, time] = String(e.at_label || '').split(' | ');
                let g = groups[groups.length - 1];
                if (!g || g.date !== date) {
                    g = {
                        date,
                        items: []
                    };
                    groups.push(g);
                }
                g.items.push({
                    ...e,
                    time
                });
            });

            box.innerHTML = groups.map(g => `
                <div class="mb-5 last:mb-0">
                    <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-3">${esc(g.date)}</p>
                    <ol class="relative" style="border-left:2px solid #e5e7eb;margin-left:5px;">
                        ${g.items.map(e => `
                            <li class="relative pl-5 pb-5 last:pb-0">
                                <span style="position:absolute;left:-7px;top:4px;width:12px;height:12px;border-radius:9999px;background:${EVENT_DOT[e.event] || '#9ca3af'};border:2px solid #fff;box-shadow:0 0 0 1px #e5e7eb;"></span>
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-sm font-semibold text-gray-900 break-words min-w-0">${esc(e.title)}</p>
                                    <span class="text-xs text-gray-400 shrink-0 pt-0.5">${esc(e.time || '')}</span>
                                </div>
                                <div class="flex items-center gap-1.5 mt-1">
                                    <span class="inline-flex items-center justify-center rounded-full bg-gray-100 text-gray-600 font-bold shrink-0"
                                        style="width:18px;height:18px;font-size:9px;">${esc(initials(e.actor))}</span>
                                    <span class="text-xs text-gray-500">${esc(e.actor)}</span>
                                </div>
                                ${(e.changes || []).length ? `<div class="mt-2 rounded-lg bg-gray-50 border border-gray-100 px-3 py-2 space-y-1">${e.changes.map(changeLine).join('')}</div>` : ''}
                            </li>`).join('')}
                    </ol>
                </div>`).join('');
        }

        window.openTicketDetailPanel = async function(ticketId) {
            const t = (typeof allTickets !== 'undefined' ? allTickets : []).find(x => String(x.ticket_id) === String(ticketId));
            const token = ++loadToken;

            document.getElementById('tdpOpenLink').href = '/ticket/' + ticketId;
            if (t) {
                renderData(t);
            } else {
                document.getElementById('tdpNumber').textContent = '';
                document.getElementById('tdpStatus').innerHTML = '';
                document.getElementById('tdpDescription').textContent = '';
                ['tdpOverview', 'tdpTeam', 'tdpSchedule'].forEach(id => {
                    document.getElementById(id).innerHTML = '<p class="col-span-2 text-sm text-gray-400 text-center py-2">Ticket data is not available.</p>';
                });
            }
            document.getElementById('tdpLogCount').textContent = '';
            document.getElementById('tdpLogs').innerHTML = '<p class="text-sm text-gray-400 text-center py-8">Loading logs...</p>';
            document.getElementById('tdpDocsSummary').innerHTML = '';
            document.getElementById('tdpDocs').innerHTML = '<p class="text-sm text-gray-400 text-center py-4">Loading documents...</p>';

            overlay.classList.remove('hidden');
            panel.style.visibility = 'visible';
            // dua frame: transisi slide-in butuh posisi awal (di luar layar) sudah ter-render dulu
            requestAnimationFrame(() => requestAnimationFrame(() => {
                panel.style.transform = 'translateX(0)';
            }));
            panel.setAttribute('aria-hidden', 'false');
            panel.querySelector('.overflow-y-auto').scrollTop = 0;

            const getJson = async (url, failMsg) => {
                const res = await fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                });
                const json = await res.json();
                if (!res.ok || !json.success) throw new Error(json.message || failMsg);
                return json;
            };

            // Log dan dokumen dimuat paralel; gagal salah satu tidak menggagalkan yang lain.
            await Promise.all([
                getJson(`/api/tickets/${ticketId}/timeline`, 'Failed to load logs.')
                .then(json => {
                    if (token === loadToken) renderLogs(json.data || []);
                })
                .catch(err => {
                    if (token === loadToken) document.getElementById('tdpLogs').innerHTML = `<p class="text-sm text-red-500 text-center py-8">${esc(err.message)}</p>`;
                }),
                getJson(`/api/tickets/${ticketId}/deliverables`, 'Failed to load documents.')
                .then(json => {
                    if (token === loadToken) renderDocs(json.mandatory_types || [], json.data || []);
                })
                .catch(err => {
                    if (token === loadToken) document.getElementById('tdpDocs').innerHTML = `<p class="text-sm text-red-500 text-center py-4">${esc(err.message)}</p>`;
                }),
            ]);
        };

        window.closeTicketDetailPanel = function() {
            loadToken++;
            panel.style.transform = 'translateX(100%)';
            panel.setAttribute('aria-hidden', 'true');
            overlay.classList.add('hidden');
            setTimeout(() => {
                if (panel.getAttribute('aria-hidden') === 'true') panel.style.visibility = 'hidden';
            }, 300);
        };

        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && panel.getAttribute('aria-hidden') === 'false') closeTicketDetailPanel();
        });
    })();
</script>
