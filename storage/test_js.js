
    const ticketId      = 0;
    const userRole      = 0;
    const ticketCustomerId = 0;
    const ticketChannel = "B";
    let quillEditor     = null;

    function toggleSidebarPanel(panelId, chevronId) {
        const panel   = document.getElementById(panelId);
        const chevron = document.getElementById(chevronId);
        const hidden  = panel.classList.toggle('hidden');
        if (chevron) chevron.style.transform = hidden ? 'rotate(180deg)' : '';
    }
    let allSidebarTickets  = [];
    let sidebarView        = 'all';
    let deliverySupportList = [];
    // Set berisi ID pesan yang sudah dirender ke DOM.
    // Digunakan agar polling tidak me-render ulang pesan lama → gambar tidak flicker.
    let renderedMessageIds = new Set();

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Quill
        quillEditor = new Quill('#quillEditor', {
            theme: 'snow',
            placeholder: 'Type your reply here...',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline', 'strike'],
                    ['blockquote'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'header': [1, 2, 3, false] }],
                    ['link'],
                    ['clean']
                ]
            }
        });

        // Tambah tooltip title pada tombol toolbar Quill
        const toolbar = document.querySelector('.ql-toolbar');
        if (toolbar) {
            const map = {
                'ql-bold': 'Bold', 'ql-italic': 'Italic',
                'ql-underline': 'Underline', 'ql-strike': 'Strikethrough',
                'ql-blockquote': 'Blockquote', 'ql-link': 'Link',
                'ql-clean': 'Clear Formatting',
            };
            Object.entries(map).forEach(([cls, label]) => {
                const btn = toolbar.querySelector('.' + cls);
                if (btn) btn.setAttribute('title', label);
            });
            toolbar.querySelectorAll('.ql-list').forEach(btn => {
                btn.setAttribute('title', btn.value === 'ordered' ? 'Numbered List' : 'Bullet List');
            });
            const header = toolbar.querySelector('.ql-header');
            if (header) header.setAttribute('title', 'Heading');

            // Inject attachment button into toolbar
            const attachGroup = document.createElement('span');
            attachGroup.className = 'ql-formats';
            attachGroup.innerHTML = `
                <button type="button" id="attachBtn" title="Attach File"
                        onclick="document.getElementById('attachInput').click()"
                        style="width:auto;padding:2px 7px;display:inline-flex;align-items:center;gap:4px;border-radius:3px;">
                    <i class="fas fa-paperclip" style="font-size:12px;color:#555"></i>
                    <span style="font-size:11px;font-weight:500;color:#444;line-height:1.5">Attachment</span>
                </button>`;
            toolbar.appendChild(attachGroup);
        }

        loadMessages();
        loadSidebarTickets();
        markMessagesRead();
        startMessagePolling();
    });

    // ==================== AUTO POLLING: reload pesan & cek email baru ====================
    function startMessagePolling() {
        setInterval(async function () {
            // Jika tiket dari email, proses inbox dulu
            if (ticketChannel === 'email') {
                try {
                    await fetch('/api/email/process-inbox', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? ''
                        },
                        credentials: 'same-origin'
                    });
                } catch (_) {}
            }
            // Selalu reload pesan (bisa ada balasan dari agent lain juga)
            await loadMessages();
        }, 15000); // setiap 15 detik
    }

    // ==================== MESSAGES ====================
    async function loadMessages() {
        const thread  = document.getElementById('messagesThread');
        const loading = document.getElementById('messagesLoading');

        if (!thread) {
            console.error('[loadMessages] ERROR: #messagesThread tidak ditemukan di DOM');
            return;
        }

        try {
            const response = await fetch(`/api/tickets/${ticketId}/messages`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                console.error('[loadMessages] Response tidak OK:', response.status);
                if (loading) loading.classList.add('hidden');
                return;
            }

            const data = await response.json();
            if (loading) loading.classList.add('hidden');

            // Tidak ada pesan dari server
            if (!data.success || !data.data || data.data.length === 0) {
                // Hanya tampilkan fallback jika memang belum ada apapun di thread
                if (renderedMessageIds.size === 0) {
                    thread.innerHTML = createFallbackMessage();
                }
                return;
            }

            const messages    = data.data;
            const isFirstLoad = renderedMessageIds.size === 0;

            // Filter hanya pesan yang belum pernah dirender
            const newMessages = messages.filter(msg => !renderedMessageIds.has(msg.id));

            if (newMessages.length === 0) {
                // Tidak ada pesan baru — DOM tidak disentuh, gambar tidak hilang
                return;
            }

            if (isFirstLoad) {
                // Load pertama: render semua sekaligus (innerHTML sekali, bukan per-pesan)
                thread.innerHTML = messages.map(msg => createMessageBubble(msg)).join('');
                messages.forEach(msg => renderedMessageIds.add(msg.id));
                console.log('[loadMessages] Initial render:', messages.length, 'pesan');
            } else {
                // Poll berikutnya: hanya append pesan baru di bawah, pesan lama tidak disentuh
                newMessages.forEach(msg => {
                    thread.insertAdjacentHTML('beforeend', createMessageBubble(msg));
                    renderedMessageIds.add(msg.id);
                });
                console.log('[loadMessages] Appended', newMessages.length, 'pesan baru');
            }

            thread.scrollTop = thread.scrollHeight;

        } catch (error) {
            console.error('[loadMessages] EXCEPTION:', error.name, error.message);
            if (loading) loading.classList.add('hidden');
            // Hanya tampilkan fallback jika thread masih kosong
            if (renderedMessageIds.size === 0) {
                thread.innerHTML = createFallbackMessage();
            }
        }
    }

    // ── Render attachment list (gambar inline, file sebagai link download) ──────
    // isEmailWithHtml: true jika pesan email sudah punya message_html →
    //   inline images sudah ditampilkan di dalam HTML body, jadi tidak perlu ditampilkan ulang sebagai thumbnail
    function renderAttachments(attachments, isEmailWithHtml = false) {
        if (!attachments || attachments.length === 0) return '';

        // Pisahkan inline images dan file biasa
        // Jika email dengan HTML body: abaikan inline images (sudah ada di message_html setelah CID replacement)
        const inlineImgs = isEmailWithHtml
            ? []
            : attachments.filter(a => a.is_inline && a.mime_type?.startsWith('image/'));
        // Untuk email dengan HTML body: juga exclude is_inline=true dari files (sudah ada di HTML body)
        const files = isEmailWithHtml
            ? attachments.filter(a => !a.is_inline)
            : attachments.filter(a => !inlineImgs.includes(a));

        let html = '';

        if (inlineImgs.length > 0) {
            html += `<div class="mt-2 flex flex-wrap gap-2">`;
            inlineImgs.forEach(img => {
                html += `<a href="${img.url}" target="_blank" title="${escHtml(img.file_name)}">
                    <img src="${img.url}" alt="${escHtml(img.file_name)}"
                         class="max-h-48 max-w-xs rounded-lg border border-gray-200 cursor-zoom-in hover:opacity-90 transition-opacity"
                         onerror="this.style.display='none'">
                </a>`;
            });
            html += `</div>`;
        }

        if (files.length > 0) {
            html += `<div class="mt-2 space-y-1">`;
            files.forEach(file => {
                const icon  = attachmentIcon(file.attachment_type, file.mime_type);
                const size  = formatFileSize(file.file_size);
                const isImg = file.mime_type?.startsWith('image/');
                html += `<div class="flex items-center gap-2 bg-white border border-gray-200 rounded-lg px-3 py-2 max-w-xs">
                    <span class="text-lg flex-shrink-0">${icon}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium text-gray-700 truncate">${escHtml(file.file_name)}</p>
                        ${size ? `<p class="text-[10px] text-gray-400">${size}</p>` : ''}
                    </div>
                    <div class="flex gap-1 flex-shrink-0">
                        ${isImg ? `<a href="${file.url}" target="_blank" class="text-xs text-blue-500 hover:underline">View</a>` : ''}
                        <a href="${file.url}" download="${escHtml(file.file_name)}"
                           class="text-xs text-blue-500 hover:underline">Download</a>
                    </div>
                </div>`;
            });
            html += `</div>`;
        }

        return html;
    }

    function attachmentIcon(type, mime) {
        if (mime?.startsWith('image/'))        return '🖼️';
        if (type === 'pdf')                    return '📄';
        if (type === 'document')               return '📝';
        if (type === 'spreadsheet')            return '📊';
        if (type === 'archive')                return '🗜️';
        return '📎';
    }

    function formatFileSize(bytes) {
        if (!bytes) return '';
        if (bytes < 1024)       return bytes + ' B';
        if (bytes < 1048576)    return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function escHtml(str) {
        if (!str) return '';
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Pilih konten pesan: HTML dari email atau plain text dari web ────────────
    function messageContent(msg) {
        // Email dengan HTML body → render HTML mentah (sudah disanitasi oleh extractReplyBody)
        if (msg.channel === 'email' && msg.message_html) {
            return `<div class="message-content text-sm text-gray-700 email-html-body">${msg.message_html}</div>`;
        }
        // Web reply (Quill HTML) atau plain text — guard null untuk reply tanpa teks (file only)
        if (!msg.message_body) return '';
        return `<div class="message-content text-sm text-gray-700">${msg.message_body}</div>`;
    }

    function createMessageBubble(msg) {
        const isEmployee     = msg.sender_type === 'employee';
        const isInternalNote = msg.message_type === 'internal_note';
        const senderName     = msg.sender_name || (isEmployee ? 'Employee' : 'Customer');
        const initials       = senderName.substring(0, 1).toUpperCase();
        const date           = new Date(msg.created_at).toLocaleString('en-GB', {
            timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit', hour12: false
        }) + ' (WIB)';

        const channelBadge = msg.channel === 'email'
            ? `<span class="msg-channel-badge msg-channel-email"><svg style="width:9px;height:9px;display:inline" viewBox="0 0 20 20" fill="currentColor"><path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/><path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/></svg> Email</span>`
            : `<span class="msg-channel-badge msg-channel-web"><svg style="width:9px;height:9px;display:inline" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM4.332 8.027a6.012 6.012 0 011.912-2.706C6.512 5.73 6.974 6 7.5 6A1.5 1.5 0 019 7.5V8a2 2 0 004 0 2 2 0 011.523-1.943A5.977 5.977 0 0116 10c0 .34-.028.675-.083 1H15a2 2 0 00-2 2v2.197A5.973 5.973 0 0110 16v-2a2 2 0 00-2-2 2 2 0 01-2-2 2 2 0 00-1.668-1.973z" clip-rule="evenodd"/></svg> Web</span>`;

        // CC badge — hanya tampil kalau ada CC
        const ccList   = msg.cc_emails || [];
        const ccBadge  = ccList.length > 0
            ? `<span class="inline-flex items-center gap-1 text-[10px] text-gray-400 mt-0.5">
                <svg style="width:9px;height:9px;flex-shrink:0" viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/></svg>
                <span class="font-medium text-gray-500">CC:</span>
                ${ccList.map(c => `<span title="${c.address || c}">${c.name || c.address || c}</span>`).join(', ')}
               </span>`
            : '';

        const isEmailWithHtml = msg.channel === 'email' && !!msg.message_html;
        const attachmentsHtml = renderAttachments(msg.attachments, isEmailWithHtml);

        if (isInternalNote) {
            return `
                <div class="flex gap-3">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 bg-amber-200 text-amber-800 text-xs font-bold">${initials}</div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-sm font-semibold text-gray-900">${senderName}</span>
                            <span class="text-[10px] bg-amber-200 text-amber-800 px-1.5 py-0.5 rounded font-semibold">Internal Note</span>
                            ${channelBadge}
                            <span class="text-xs text-gray-400">${date}</span>
                        </div>
                        <div class="message-bubble internal-note p-3">
                            ${messageContent(msg)}
                            ${attachmentsHtml}
                        </div>
                    </div>
                </div>`;
        }

        const avatarBg   = isEmployee ? 'bg-blue-500' : 'bg-gray-400';
        const bubbleClass = isEmployee ? 'employee' : 'customer';

        return `
            <div class="flex gap-3 ${isEmployee ? 'flex-row-reverse' : ''}">
                <div class="w-8 h-8 ${avatarBg} rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-bold">${initials}</div>
                <div class="${isEmployee ? 'text-right' : ''}">
                    <div class="flex flex-col mb-1 ${isEmployee ? 'items-end' : ''}">
                        <div class="flex items-center gap-2 ${isEmployee ? 'justify-end' : ''}">
                            <span class="text-sm font-semibold text-gray-900">${senderName}</span>
                            ${channelBadge}
                            <span class="text-xs text-gray-400">${date}</span>
                        </div>
                        ${ccBadge}
                    </div>
                    <div class="message-bubble ${bubbleClass} p-3 inline-block text-left">
                        ${messageContent(msg)}
                        ${attachmentsHtml}
                    </div>
                </div>
            </div>`;
    }

    function createFallbackMessage() {
        const customerName = "B";
        const description = "B";
        const date = "B");
        return `
            <div class="flex gap-3">
                <div class="w-8 h-8 bg-gray-400 rounded-full flex items-center justify-center flex-shrink-0 text-white text-xs font-bold">${customerName.substring(0, 1)}</div>
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-sm font-semibold text-gray-900">${customerName}</span>
                        <span class="text-[10px] bg-gray-200 text-gray-600 px-1.5 py-0.5 rounded font-semibold">Initial</span>
                        <span class="text-xs text-gray-400">${date}</span>
                    </div>
                    <div class="message-bubble customer p-3 inline-block">
                        <div class="message-content text-sm text-gray-700">${description}</div>
                    </div>
                </div>
            </div>`;
    }

    // ==================== ATTACHMENT HANDLING (COMPOSE) ====================
    let selectedFiles = []; // File[] yang dipilih user untuk dikirim bersama reply

    document.getElementById('attachInput').addEventListener('change', function () {
        const maxSize = 10 * 1024 * 1024; // 10 MB per file
        Array.from(this.files).forEach(file => {
            if (file.size > maxSize) {
                showNotification(`${file.name} terlalu besar (maks 10 MB)`, 'error');
                return;
            }
            // Hindari duplikat berdasarkan nama + ukuran
            if (!selectedFiles.find(f => f.name === file.name && f.size === file.size)) {
                selectedFiles.push(file);
            }
        });
        // Reset value agar file yang sama bisa dipilih ulang setelah dihapus
        this.value = '';
        renderAttachmentPreview();
    });

    function renderAttachmentPreview() {
        const preview = document.getElementById('attachmentPreview');
        const countEl = document.getElementById('attachCount');

        if (selectedFiles.length === 0) {
            preview.style.display = 'none';
            countEl.classList.add('hidden');
            return;
        }

        preview.style.display = 'flex';
        countEl.classList.remove('hidden');
        countEl.textContent = selectedFiles.length + (selectedFiles.length === 1 ? ' file' : ' files');

        preview.innerHTML = selectedFiles.map((file, idx) => {
            const size = formatFileSize(file.size);
            const icon = file.type.startsWith('image/') ? '🖼️'
                       : file.type === 'application/pdf' ? '📄'
                       : /\.(doc|docx)$/i.test(file.name) ? '📝'
                       : /\.(xls|xlsx|csv)$/i.test(file.name) ? '📊'
                       : /\.(zip|rar)$/i.test(file.name) ? '🗜️'
                       : '📎';
            return `<div class="flex items-center gap-1.5 bg-gray-100 border border-gray-200 rounded-lg px-2.5 py-1.5" style="max-width:200px">
                <span class="text-sm flex-shrink-0">${icon}</span>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-700 truncate" title="${escHtml(file.name)}">${escHtml(file.name)}</p>
                    ${size ? `<p class="text-[10px] text-gray-400">${size}</p>` : ''}
                </div>
                <button type="button" onclick="removeAttachment(${idx})" title="Remove"
                        class="flex-shrink-0 w-4 h-4 flex items-center justify-center text-gray-400 hover:text-red-500 transition-colors text-xs leading-none">✕</button>
            </div>`;
        }).join('');
    }

    function removeAttachment(idx) {
        selectedFiles.splice(idx, 1);
        renderAttachmentPreview();
    }

    function resetAttachments() {
        selectedFiles = [];
        document.getElementById('attachInput').value = '';
        renderAttachmentPreview();
    }

    // ==================== SEND REPLY ====================
    async function sendReply(messageType) {
        const htmlContent  = quillEditor.root.innerHTML;
        const plainContent = quillEditor.getText().trim();
        const hasFiles     = selectedFiles.length > 0;

        // Perlu minimal teks atau file lampiran
        if (!plainContent && !hasFiles) {
            showNotification('Type a message or attach a file', 'error');
            return;
        }

        // Disable tombol kirim selama proses agar tidak double-submit
        const sendBtn = document.querySelector('button[onclick="sendReply(\'reply\')"]');
        const noteBtn = document.querySelector('button[onclick="sendReply(\'internal_note\')"]');
        if (sendBtn) { sendBtn.disabled = true; sendBtn.classList.add('opacity-60'); }
        if (noteBtn) { noteBtn.disabled = true; noteBtn.classList.add('opacity-60'); }

        try {
            let requestBody;
            const headers = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            };

            if (hasFiles) {
                // Kirim sebagai multipart/form-data
                // Jangan set Content-Type manual — browser otomatis tambahkan boundary yang benar
                const formData = new FormData();
                formData.append('message_body', htmlContent);
                formData.append('message_type', messageType);
                selectedFiles.forEach(file => formData.append('attachments[]', file));
                requestBody = formData;
            } else {
                headers['Content-Type'] = 'application/json';
                requestBody = JSON.stringify({ message_body: htmlContent, message_type: messageType });
            }

            const response = await fetch(`/api/tickets/${ticketId}/messages`, {
                method: 'POST',
                headers,
                credentials: 'same-origin',
                body: requestBody
            });

            const data = await response.json();

            if (data.success) {
                quillEditor.setContents([]);
                resetAttachments();
                await loadMessages();
                showNotification(messageType === 'internal_note' ? 'Internal note added' : 'Reply sent', 'success');
            } else {
                console.warn('[sendReply] API error:', data.message, data.errors);
                showNotification(data.message || 'Failed to send message', 'error');
            }
        } catch (error) {
            console.error('[sendReply] EXCEPTION:', error.name, error.message);
            showNotification('Error: ' + error.message, 'error');
        } finally {
            if (sendBtn) { sendBtn.disabled = false; sendBtn.classList.remove('opacity-60'); }
            if (noteBtn) { noteBtn.disabled = false; noteBtn.classList.remove('opacity-60'); }
        }
    }

    async function markMessagesRead() {
        try {
            await fetch(`/api/tickets/${ticketId}/messages/mark-all-read`, {
                method: 'PUT',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin'
            });
        } catch (e) {}
    }

    // ==================== SIDEBAR TICKETS ====================
    function switchSidebarView(view) {
        sidebarView = view;
        const tabAll = document.getElementById('sidebarTabAll');
        const tabMy  = document.getElementById('sidebarTabMy');
        if (tabAll && tabMy) {
            tabAll.style.background = view === 'all' ? 'rgba(255,255,255,0.2)' : '';
            tabAll.classList.toggle('opacity-60', view !== 'all');
            tabMy.style.background  = view === 'my'  ? 'rgba(255,255,255,0.2)' : '';
            tabMy.classList.toggle('opacity-60', view !== 'my');
        }
        loadSidebarTickets();
    }

    async function loadSidebarTickets() {
        try {
            let endpoint = '/api/tickets';
            if (userRole === 3) endpoint = '/api/tickets/my';
            else if ([1, 2, 6, 7].includes(userRole) && sidebarView === 'my') endpoint = '/api/tickets/my';

            const response = await fetch(endpoint, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            const data = await response.json();

            document.getElementById('sidebarLoading').classList.add('hidden');

            if (data.success) {
                allSidebarTickets = data.data.sort((a, b) => new Date(b.last_message_at || b.created_at) - new Date(a.last_message_at || a.created_at));
                renderSidebarTickets(allSidebarTickets);
            }
        } catch (error) {
            document.getElementById('sidebarLoading').classList.add('hidden');
        }
    }

    function renderSidebarTickets(tickets) {
        const list = document.getElementById('sidebarTicketList');
        list.innerHTML = tickets.map(t => {
            const isActive = t.ticket_id === ticketId;
            const customerName = t.customer?.customer_name || 'Unknown';
            const desc = t.description || 'No description';
            const shortDesc = desc.length > 40 ? desc.substring(0, 40) + '...' : desc;
            const lastActivity = new Date(t.last_message_at || t.created_at);
            const timeAgo = formatTimeAgo(lastActivity);
            const timeTitle = lastActivity.toLocaleString('id-ID', { timeZone: 'Asia/Jakarta', day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });

            const prioColors = { 'Very High': 'bg-purple-500', 'High': 'bg-red-400', 'Medium': 'bg-blue-400', 'Low': 'bg-green-400' };
            const prioDot = prioColors[t.ticket_priority] || 'bg-gray-400';

            return `
                <a href="/ticket/${t.ticket_id}" class="sidebar-ticket-item ${isActive ? 'active' : ''}">
                    <div class="flex items-center justify-between mb-0.5">
                        <span class="text-xs font-semibold text-gray-800 truncate max-w-[140px]">${customerName}</span>
                        <span class="text-[10px] text-gray-400" title="${timeTitle}">${timeAgo}</span>
                    </div>
                    <p class="text-[11px] text-gray-500 truncate mb-1">${t.ticket_number || 'No Number'} — ${shortDesc}</p>
                    <div class="flex items-center gap-1.5">
                        <div class="w-1.5 h-1.5 rounded-full ${prioDot}"></div>
                        <span class="text-[10px] text-gray-400">${t.ticket_priority || 'Medium'}</span>
                    </div>
                </a>`;
        }).join('');
    }

    function filterSidebarTickets() {
        const term = document.getElementById('sidebarSearch').value.toLowerCase();
        if (!term) {
            renderSidebarTickets(allSidebarTickets);
            return;
        }
        const filtered = allSidebarTickets.filter(t =>
            (t.ticket_number && t.ticket_number.toLowerCase().includes(term)) ||
            (t.description && t.description.toLowerCase().includes(term)) ||
            (t.customer?.customer_name && t.customer.customer_name.toLowerCase().includes(term))
        );
        renderSidebarTickets(filtered);
    }

    // ==================== TEAM MEMBERS ====================
    const allEmployees  = "B";
    const canManageMembers = 0;

    function escHtmlMember(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    function renderMembers(members) {
        const list = document.getElementById('membersList');
        if (!list) return;

        const memberIds = new Set(members.map(m => m.employee_id));

        if (members.length === 0) {
            list.innerHTML = '<p class="text-xs text-gray-400 italic" id="noMembersText">No members assigned.</p>';
        } else {
            list.innerHTML = members.map(m => `
                <div class="member-chip flex items-center justify-between gap-1 px-2.5 py-1.5 bg-blue-50 rounded-lg" data-id="${m.employee_id}">
                    <span class="text-xs text-blue-700 font-medium truncate">${escHtmlMember(m.name)}</span>
                    ${canManageMembers ? `<button type="button" onclick="removeMemberBtn(${m.employee_id})"
                        class="text-blue-300 hover:text-red-500 transition-colors flex-shrink-0 ml-1">
                        <i class="fas fa-times text-[9px]"></i></button>` : ''}
                </div>`).join('');
        }

        // Rebuild dropdown: show only employees not already in members and not the PIC
        const sel = document.getElementById('addMemberSelect');
        if (sel) {
            sel.innerHTML = '<option value="">-- Add member --</option>';
            allEmployees.forEach(emp => {
                if (!memberIds.has(emp.employee_id) && emp.employee_id != 0) {
                    const opt = document.createElement('option');
                    opt.value = emp.employee_id;
                    opt.textContent = emp.name;
                    sel.appendChild(opt);
                }
            });
        }
    }

    async function addMemberBtn() {
        const sel   = document.getElementById('addMemberSelect');
        const empId = sel?.value;
        if (!empId) { showNotification('Please select a member to add.', 'error'); return; }

        const btn = sel.nextElementSibling;
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin text-[10px]"></i>'; }

        try {
            const res  = await fetch(`/api/tickets/${ticketId}/members`, {
                method: 'POST',
                headers: getHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ employee_id: parseInt(empId) }),
            });
            const data = await res.json();
            if (!data.success) { showNotification(data.message || 'Failed to add member.', 'error'); return; }
            renderMembers(data.data);
            showNotification('Member added successfully.', 'success');
        } catch {
            showNotification('Error adding member.', 'error');
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-user-plus text-[10px]"></i>'; }
        }
    }

    async function removeMemberBtn(employeeId) {
        try {
            const res  = await fetch(`/api/tickets/${ticketId}/members/${employeeId}`, {
                method: 'DELETE',
                headers: getHeaders(),
                credentials: 'same-origin',
            });
            const data = await res.json();
            if (!data.success) { showNotification(data.message || 'Failed to remove member.', 'error'); return; }
            renderMembers(data.data);
            showNotification('Member removed.', 'success');
        } catch {
            showNotification('Error removing member.', 'error');
        }
    }

    // ==================== ADMIN ACTIONS ====================
    function getHeaders() {
        return {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        };
    }

    async function saveAllProperties() {
        const status = document.getElementById('detailStatus').value;
        const jarviesStatus = document.getElementById('detailJarviesStatus').value;
        const priority = document.getElementById('detailPriority').value;
        const type = document.getElementById('detailType').value;
        const pic = document.getElementById('detailPIC').value;
        const manDays = document.getElementById('detailManDays').value;

        try {
            // Update status via dedicated endpoint
            const statusResponse = await fetch(`/api/tickets/${ticketId}/update-status`, {
                method: 'PUT',
                headers: getHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ status: status })
            });

            // Update all other properties via general update endpoint
            const updateData = {
                jarvies_status: jarviesStatus,
                ticket_priority: priority,
                ticket_type: type || null,
                employee_id: pic || null,
                man_days: manDays ? parseFloat(manDays) : null,
            };

            const response = await fetch(`/api/tickets/${ticketId}`, {
                method: 'PUT',
                headers: getHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify(updateData)
            });

            const result = await response.json();

            if (result.success) {
                showNotification('All properties saved!', 'success');
                // Refresh header info after a short delay
                setTimeout(() => location.reload(), 800);
            } else {
                showNotification(result.message || 'Failed to save', 'error');
            }
        } catch (error) {
            showNotification('Error: ' + error.message, 'error');
        }
    }

    async function deleteTicket() {
        if (!confirm('Are you sure you want to delete this ticket?')) return;
        try {
            const response = await fetch(`/api/tickets/${ticketId}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json', 'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                credentials: 'same-origin'
            });
            const result = await response.json();
            if (result.success) {
                showNotification('Ticket deleted!', 'success');
                setTimeout(() => window.location.href = '/ticket', 500);
            } else {
                showNotification(result.message || 'Failed to delete', 'error');
            }
        } catch (error) {
            showNotification('Error: ' + error.message, 'error');
        }
    }

    // ==================== HELPERS ====================
    function formatTimeAgo(date) {
        const tz = 'Asia/Jakarta';
        const now = new Date();
        const toDay = (d) => new Date(d.toLocaleDateString('en-CA', { timeZone: tz }));
        const todayDate  = toDay(now);
        const targetDate = toDay(date);
        const diffDays = Math.round((todayDate - targetDate) / 86400000);

        if (diffDays === 0) {
            return date.toLocaleTimeString('id-ID', { timeZone: tz, hour: '2-digit', minute: '2-digit', hour12: false });
        }
        if (diffDays === 1) return 'Yest';
        if (diffDays < 7) {
            return date.toLocaleDateString('en-GB', { timeZone: tz, weekday: 'short' });
        }
        if (date.getFullYear() === now.getFullYear()) {
            return date.toLocaleDateString('en-GB', { timeZone: tz, day: '2-digit', month: 'short' });
        }
        return date.toLocaleDateString('en-GB', { timeZone: tz, day: '2-digit', month: 'short', year: 'numeric' });
    }

    // showNotification() is provided globally by the dashboard layout (toast system)

    // ==================== ASSIGN TO DELIVERY SUPPORT ====================
    async function openAssignSupportModal() {
        const modal = document.getElementById('assignSupportModal');
        if (modal) {
            modal.classList.remove('hidden');
            // Load existing delivery supports
            await loadDeliverySupports();
        }
    }

    function closeAssignSupportModal() {
        const modal = document.getElementById('assignSupportModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    function toggleAssignType() {
        const assignType = document.querySelector('input[name="assignType"]:checked')?.value;
        const existingDiv = document.getElementById('existingDeliverySupport');
        const newDiv = document.getElementById('newDeliverySupport');

        if (assignType === 'new') {
            existingDiv.classList.add('hidden');
            newDiv.classList.remove('hidden');
        } else {
            existingDiv.classList.remove('hidden');
            newDiv.classList.add('hidden');
        }
    }

    async function loadDeliverySupports() {
        const select = document.getElementById('deliverySupportSelect');
        if (!select) return;

        select.innerHTML = '<option value="">Loading...</option>';

        try {
            // Load delivery supports, optionally filtered by the same customer
            const response = await fetch('/api/delivery/support/search?client_id=' + (ticketCustomerId || ''), {
                headers: getHeaders(),
                credentials: 'same-origin'
            });

            const data = await response.json();

            if (data.success && data.data) {
                deliverySupportList = data.data;
                select.innerHTML = '<option value="">-- Select Delivery Support --</option>';

                if (data.data.length === 0) {
                    select.innerHTML = '<option value="">No delivery support found</option>';
                    return;
                }

                data.data.forEach(support => {
                    const option = document.createElement('option');
                    option.value = support.id;
                    option.textContent = `${support.name} (${support.client_name || 'Unknown Client'}), ${support.type}`;
                    select.appendChild(option);
                });
            } else {
                select.innerHTML = '<option value="">Failed to load</option>';
            }
        } catch (error) {
            console.error('Error loading delivery supports:', error);
            select.innerHTML = '<option value="">Error loading data</option>';
        }
    }

    async function confirmAssignSupport() {
        const assignType = document.querySelector('input[name="assignType"]:checked')?.value;

        if (assignType === 'existing') {
            await assignToExistingSupport();
        } else {
            await createNewSupportAndAssign();
        }
    }

    async function assignToExistingSupport() {
        const supportId = document.getElementById('deliverySupportSelect').value;

        if (!supportId) {
            showNotification('Please select a delivery support', 'error');
            return;
        }

        try {
            const response = await fetch(`/api/tickets/${ticketId}/assign-to-support`, {
                method: 'POST',
                headers: getHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ support_id: supportId })
            });

            const data = await response.json();

            if (data.success) {
                showNotification('Ticket assigned to delivery support successfully!', 'success');
                closeAssignSupportModal();
                showAssignSuccessModal(`/delivery/support/${supportId}`);
            } else {
                showNotification(data.message || 'Failed to assign ticket', 'error');
            }
        } catch (error) {
            console.error('Error assigning ticket:', error);
            showNotification('Error: ' + error.message, 'error');
        }
    }

    async function createNewSupportAndAssign() {
        const supportName = document.getElementById('newSupportName').value.trim();
        const supportMethod = document.getElementById('newSupportMethod').value;

        if (!supportName) {
            showNotification('Please enter a support name', 'error');
            return;
        }

        try {
            const response = await fetch('/api/tickets/' + ticketId + '/create-delivery-support', {
                method: 'POST',
                headers: getHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({
                    name: supportName,
                    support_method: supportMethod
                })
            });

            const data = await response.json();

            if (data.success) {
                showNotification('Delivery support created and ticket assigned!', 'success');
                closeAssignSupportModal();
                if (data.data?.support_id) {
                    showAssignSuccessModal(`/delivery/support/${data.data.support_id}`);
                }
            } else {
                showNotification(data.message || 'Failed to create delivery support', 'error');
            }
        } catch (error) {
            console.error('Error creating delivery support:', error);
            showNotification('Error: ' + error.message, 'error');
        }
    }


    // ==================== ASSIGN SUCCESS MODAL ====================
    let assignSuccessRedirectUrl = '';

    function showAssignSuccessModal(url) {
        assignSuccessRedirectUrl = url;
        document.getElementById('assignSuccessModal').classList.remove('hidden');
    }

    function closeAssignSuccessModal() {
        document.getElementById('assignSuccessModal').classList.add('hidden');
        assignSuccessRedirectUrl = '';
    }

    function goToDeliverySupport() {
        if (assignSuccessRedirectUrl) {
            window.location.href = assignSuccessRedirectUrl;
        }
    }


    // ==================== MANDAYS — SHARED ====================
    let picMandaysModules  = [];
    let picDraftData       = null;
    let picReadOnly        = false;
    let internalPicData    = null;
    let internalPicPeople  = [];
    let internalPicReadOnly= false;

    const MANDAYS_API = (path) => `/api/tickets/${ticketId}/mandays/${path}`;

    // ==================== PIC: CUSTOMER MANDAYS ====================
    async function openPicMandaysModal() {
        document.getElementById('picMandaysModal').classList.remove('hidden');
        document.getElementById('picMandaysModal').classList.add('flex');
        await picLoadDraft();
    }
    function closePicMandaysModal() {
        document.getElementById('picMandaysModal').classList.add('hidden');
        document.getElementById('picMandaysModal').classList.remove('flex');
    }

    async function picLoadDraft() {
        picDirty = false;
        document.getElementById('picMandaysLoading').classList.remove('hidden');
        document.getElementById('picMandaysTable').classList.add('hidden');
        document.getElementById('picAddRowWrap').classList.add('hidden');
        document.getElementById('picRejectionInfo').classList.add('hidden');

        try {
            // Load modules & draft in parallel
            const [modRes, draftRes] = await Promise.all([
                fetch(MANDAYS_API('modules'), { headers: getHeaders(), credentials: 'same-origin' }),
                fetch(MANDAYS_API('pic-draft'), { headers: getHeaders(), credentials: 'same-origin' }),
            ]);
            const modData   = await modRes.json();
            const draftData = await draftRes.json();

            picDraftData      = draftData.data;
            const status      = draftData.ticket_mandays_status || 'none';
            // Read-only when submitted/reviewed by helpdesk (PIC can't edit after submission unless canceled)
            picReadOnly = ['pending_helpdesk', 'sent_to_chat', 'approved', 'canceled'].includes(status);

            const picStatusLabels = {
                none: 'None', pic_draft: 'Draft', pending_helpdesk: 'Submitted to Helpdesk',
                sent_to_chat: 'Sent to Customer', approved: 'Approved', canceled: 'Canceled by Helpdesk'
            };
            document.getElementById('picMandaysVersion').textContent      = picDraftData?.version ?? 'New';
            document.getElementById('picMandaysStatusLabel').textContent  = picStatusLabels[status] || status;
            // Show "New Version" when proposal is canceled, approved, or sent back (customer rejected)
            const canStartNew = picDraftData && (status === 'canceled' || status === 'approved' || (status === 'pending_helpdesk' && picDraftData?.rejection_reason));
            document.getElementById('picBtnNewVersion').classList.toggle('hidden', !canStartNew);

            const picInfoEl = document.getElementById('picRejectionInfo');
            if (status === 'canceled') {
                picInfoEl.className = 'mb-4 p-3 rounded-lg text-sm bg-gray-100 border border-gray-300 text-gray-700';
                const cancelNotes = picDraftData?.cancel_notes;
                const canceledByName = picDraftData?.canceled_by_name;
                let cancelHtml = '<p class="font-semibold text-gray-800 mb-1">Proposal Canceled by Helpdesk</p>';
                if (cancelNotes) {
                    cancelHtml += '<p class="text-xs text-gray-600">' + cancelNotes.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</p>';
                } else {
                    cancelHtml += '<p class="text-xs text-gray-500 italic">No reason provided.</p>';
                }
                if (canceledByName) {
                    cancelHtml += '<p class="text-xs text-gray-500 mt-1">— ' + canceledByName.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</p>';
                }
                picInfoEl.innerHTML = cancelHtml;
                picInfoEl.classList.remove('hidden');
            } else if (picDraftData?.rejection_reason) {
                picInfoEl.className = 'mb-4 p-3 rounded-lg text-sm bg-red-50 border border-red-200 text-red-700';
                picInfoEl.innerHTML = '<p class="font-semibold mb-1">Customer Rejection</p><p class="text-xs">' + picDraftData.rejection_reason.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</p>';
                picInfoEl.classList.remove('hidden');
            } else {
                picInfoEl.classList.add('hidden');
            }

            // Build valueMap from existing details
            const valueMap = {};
            (picDraftData?.details || []).forEach(d => {
                const act = d.activity || 'General';
                if (!valueMap[act]) valueMap[act] = {};
                valueMap[act][d.module] = d.mandays;
            });
            // If no activities, start with empty table

            // Kolom hanya dari qualification member ticket
            // Jika belum diisi di master data, PIC tambah manual via "+ Column"
            picMandaysModules = modData.data || [];

            picRenderMatrix(valueMap);
        } catch (e) {
            console.error(e);
            showNotification('Failed to load mandays data', 'error');
        } finally {
            document.getElementById('picMandaysLoading').classList.add('hidden');
        }
    }

    function picRenderMatrix(valueMap) {
        const modules = picMandaysModules;
        const activities = Object.keys(valueMap);

        // Header
        let headHtml = '<tr class="bg-gray-50">';
        headHtml += '<th class="px-2 py-2 text-left text-xs font-semibold text-gray-600 border border-gray-200">Activity</th>';
        modules.forEach(m => {
            const mEsc = m.replace(/"/g, '&quot;');
            const removeBtn = !picReadOnly
                ? `<button onclick="picRemoveModuleCol('${mEsc}')" class="ml-1 text-red-300 hover:text-red-600 font-bold leading-none" title="Remove column">×</button>`
                : '';
            headHtml += `<th class="px-2 py-2 text-center text-xs font-semibold text-gray-600 border border-gray-200 whitespace-nowrap">${m}${removeBtn}</th>`;
        });
        headHtml += '</tr>';
        document.getElementById('picMandaysHead').innerHTML = headHtml;

        // Body
        let bodyHtml = '';
        activities.forEach(act => {
            const actEsc = act.replace(/"/g, '&quot;');
            const removeRowBtn = !picReadOnly
                ? `<button onclick="picRemoveActivityRow('${actEsc}')" class="ml-1 text-red-300 hover:text-red-600 font-bold leading-none" title="Remove row">×</button>`
                : '';
            bodyHtml += `<tr data-activity="${act}">`;
            bodyHtml += `<td class="px-2 py-1.5 border border-gray-200 text-xs font-medium text-gray-700 whitespace-nowrap">${act}${removeRowBtn}</td>`;
            modules.forEach(m => {
                const val = valueMap[act]?.[m] || '';
                bodyHtml += `<td class="border border-gray-200 p-0">
                    <input type="number" min="0" step="0.5"
                        class="pic-cell w-full px-2 py-1.5 text-xs text-center focus:outline-none focus:bg-indigo-50 ${picReadOnly ? 'bg-gray-50 cursor-not-allowed' : 'bg-white'}"
                        data-activity="${act}" data-module="${m}" value="${val}"
                        ${picReadOnly ? 'readonly' : ''} oninput="picDirty=true; picUpdateTotal()">
                    </td>`;
            });
            bodyHtml += '</tr>';
        });
        document.getElementById('picMandaysBody').innerHTML = bodyHtml;

        // Footer total
        let footHtml = '<tr class="bg-gray-50 font-bold"><td class="px-2 py-1.5 border border-gray-200 text-xs">Total</td>';
        modules.forEach(m => {
            footHtml += `<td id="picColTotal_${m}" class="px-2 py-1.5 border border-gray-200 text-xs text-center">0</td>`;
        });
        footHtml += '</tr>';
        document.getElementById('picMandaysFoot').innerHTML = footHtml;

        document.getElementById('picMandaysTable').classList.remove('hidden');
        // Show editing controls only when PIC can still edit (draft state)
        document.getElementById('picAddRowWrap').classList.toggle('hidden', picReadOnly);
        document.getElementById('picBtnSaveDraft').classList.toggle('hidden', picReadOnly);
        document.getElementById('picBtnSubmit').classList.toggle('hidden', picReadOnly);

        picUpdateTotal();
    }

    function picUpdateTotal() {
        let grand = 0;
        const colTotals = {};
        document.querySelectorAll('.pic-cell').forEach(inp => {
            const v = parseFloat(inp.value) || 0;
            const m = inp.dataset.module;
            colTotals[m] = (colTotals[m] || 0) + v;
            grand += v;
        });
        document.getElementById('picTotalDisplay').textContent = grand.toFixed(1);
        Object.entries(colTotals).forEach(([m, t]) => {
            const el = document.getElementById(`picColTotal_${m}`);
            if (el) el.textContent = t.toFixed(1);
        });
    }

    function picAddActivityRow() {
        const name = document.getElementById('picNewActivity').value.trim();
        if (!name) { showNotification('Enter an activity name', 'warning'); return; }
        document.getElementById('picNewActivity').value = '';
        const currentMap = picGetCurrentValueMap();
        if (currentMap[name]) { showNotification('Activity already exists', 'warning'); return; }
        currentMap[name] = {};
        picDirty = true;
        picRenderMatrix(currentMap);
    }

    function picRemoveActivityRow(act) {
        const currentMap = picGetCurrentValueMap();
        delete currentMap[act];
        picDirty = true;
        picRenderMatrix(currentMap);
    }

    function picAddModuleCol() {
        const input = document.getElementById('picNewModule');
        const name = input.value.trim();
        if (!name) { showNotification('Enter a module name', 'warning'); return; }
        if (picMandaysModules.includes(name)) { showNotification('Module already exists', 'warning'); return; }
        input.value = '';
        picMandaysModules.push(name);
        picDirty = true;
        picRenderMatrix(picGetCurrentValueMap());
    }

    function picRemoveModuleCol(mod) {
        picMandaysModules = picMandaysModules.filter(m => m !== mod);
        if (picMandaysModules.length === 0) { showNotification('At least one module is required', 'warning'); picMandaysModules.push(mod); return; }
        picDirty = true;
        picRenderMatrix(picGetCurrentValueMap());
    }

    function picGetCurrentValueMap() {
        const map = {};
        document.querySelectorAll('.pic-cell').forEach(inp => {
            const act = inp.dataset.activity;
            const m   = inp.dataset.module;
            if (!map[act]) map[act] = {};
            map[act][m] = parseFloat(inp.value) || 0;
        });
        return map;
    }

    function picGetPayload() {
        const details = [];
        document.querySelectorAll('.pic-cell').forEach(inp => {
            const v = parseFloat(inp.value) || 0;
            if (v > 0) {
                details.push({ activity: inp.dataset.activity, module: inp.dataset.module, mandays: v });
            }
        });
        return { details };
    }

    async function picSaveDraft() {
        const btn = document.getElementById('picBtnSaveDraft');
        btn.disabled = true; btn.textContent = 'Saving...';
        try {
            const res = await fetch(MANDAYS_API('pic-draft'), {
                method: 'POST', headers: getHeaders(), credentials: 'same-origin',
                body: JSON.stringify(picGetPayload()),
            });
            const data = await res.json();
            if (data.success) {
                showNotification('Draft saved!', 'success');
                picDirty = false;
                picMandaysUpdateSidebarBadge(data.ticket_mandays_status);
                picDraftData = data.data;
                document.getElementById('picMandaysVersion').textContent = picDraftData?.version ?? '—';
            } else {
                showNotification(data.message || 'Failed to save', 'error');
            }
        } catch(e) { showNotification('Error: ' + e.message, 'error'); }
        finally { btn.disabled = false; btn.textContent = 'Save Draft'; }
    }

    function picStartNewVersion() {
        if (picDirty) { showNotification('Please save the draft before starting a new version', 'warning'); return; }
        const valueMap = {};
        if (picDraftData?.details?.length) {
            picDraftData.details.forEach(d => {
                const act = d.activity || '';
                if (!act) return;
                if (!valueMap[act]) valueMap[act] = {};
                valueMap[act][d.module] = d.mandays;
            });
        }
        picDraftData = null;
        picReadOnly = false;
        picDirty = false;
        document.getElementById('picMandaysVersion').textContent = 'New';
        document.getElementById('picRejectionInfo').classList.add('hidden');
        document.getElementById('picBtnNewVersion').classList.add('hidden');
        picRenderMatrix(valueMap);
    }

    async function picSubmitDraft() {
        if (picDirty) { showNotification('Please save draft before submitting', 'warning'); return; }
        if (!picDraftData) { showNotification('Save draft first', 'warning'); return; }
        const btn = document.getElementById('picBtnSubmit');
        btn.disabled = true; btn.textContent = 'Submitting...';
        try {
            const res = await fetch(MANDAYS_API('pic-draft/submit'), {
                method: 'POST', headers: getHeaders(), credentials: 'same-origin',
            });
            const data = await res.json();
            if (data.success) {
                showNotification('Proposal submitted to Helpdesk!', 'success');
                picMandaysUpdateSidebarBadge(data.ticket_mandays_status);
                closePicMandaysModal();
            } else {
                showNotification(data.message || 'Failed', 'error');
            }
        } catch(e) { showNotification('Error: ' + e.message, 'error'); }
        finally { btn.disabled = false; btn.textContent = 'Submit to Helpdesk'; }
    }

    function picMandaysUpdateSidebarBadge(status) {
        const badges = {
            'none':             ['bg-gray-100 text-gray-500',   'None'],
            'pic_draft':        ['bg-yellow-100 text-yellow-700','Draft'],
            'pending_helpdesk': ['bg-blue-100 text-blue-700',   'Pending Review'],
            'sent_to_chat':     ['bg-purple-100 text-purple-700','Sent to Chat'],
            'approved':         ['bg-green-100 text-green-700', 'Approved'],
            'canceled':         ['bg-red-100 text-red-700',     'Canceled'],
        };
        const el = document.getElementById('mandaysBadge');
        if (el && badges[status]) {
            el.className = `inline-block px-2 py-0.5 rounded text-[10px] font-semibold ${badges[status][0]}`;
            el.textContent = badges[status][1];
        }
    }


    // ==================== PIC: INTERNAL MANDAYS ====================
    async function openInternalMandaysModal() {
        document.getElementById('picInternalModal').classList.remove('hidden');
        document.getElementById('picInternalModal').classList.add('flex');
        await internalPicLoad();
    }
    function closePicInternalModal() {
        document.getElementById('picInternalModal').classList.add('hidden');
        document.getElementById('picInternalModal').classList.remove('flex');
    }

    async function internalPicLoad() {
        document.getElementById('internalLoading').classList.remove('hidden');
        document.getElementById('internalTable').classList.add('hidden');
        document.getElementById('internalRejectionInfo').classList.add('hidden');

        try {
            const res    = await fetch(MANDAYS_API('internal'), { headers: getHeaders(), credentials: 'same-origin' });
            const data   = await res.json();
            internalPicData    = data.data;
            internalPicPeople  = data.people || [];
            const status       = data.internal_mandays_status || 'none';

            internalPicReadOnly = ['pending_head'].includes(status);

            const statusLabels = {
                'none':         'None',
                'draft':        'Draft',
                'pending_head': 'Pending Head of Support',
                'approved':     'Approved',
                'rejected':     'Needs Revision',
            };
            document.getElementById('internalPicStatusLabel').textContent = statusLabels[status] || status;

            document.getElementById('internalNotes').value = internalPicData?.notes || '';
            document.getElementById('internalNotes').readOnly = internalPicReadOnly;
            document.getElementById('internalNotes').classList.toggle('bg-gray-50', internalPicReadOnly);

            // Show info banner based on status
            const infoEl = document.getElementById('internalRejectionInfo');
            if (status === 'approved') {
                infoEl.className = 'mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700';
                infoEl.innerHTML = '<p class="font-semibold mb-1">Proposal Approved by Head of Support</p>'
                    + (internalPicData?.approved_by_head ? '<p>Approved by: ' + internalPicData.approved_by_head + '</p>' : '')
                    + '<p class="mt-1 text-green-600">You can still update the mandays and re-submit to Head of Support.</p>';
                infoEl.classList.remove('hidden');
            } else if (status === 'pending_head') {
                infoEl.className = 'mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-700';
                infoEl.innerHTML = '<p class="font-semibold">Submitted — awaiting Head of Support review.</p>';
                infoEl.classList.remove('hidden');
            } else if (status === 'rejected' && internalPicData?.rejection_reason) {
                infoEl.className = 'mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700';
                infoEl.innerHTML = '<p class="font-semibold mb-1">Revision Required by Head of Support</p>'
                    + '<p>' + internalPicData.rejection_reason + '</p>';
                infoEl.classList.remove('hidden');
            }

            // Build valueMap from existing details only — start from 0 if none
            const valueMap = {};
            (internalPicData?.details || []).forEach(d => {
                valueMap[d.employee_id] = {
                    mandays:             (valueMap[d.employee_id]?.mandays || 0) + d.mandays,
                    additional_mandays:  (valueMap[d.employee_id]?.additional_mandays || 0) + (d.additional_mandays || 0),
                    approved_additional: (valueMap[d.employee_id]?.approved_additional || 0) + (d.approved_additional || 0),
                    notes:               d.notes || valueMap[d.employee_id]?.notes || '',
                };
            });

            internalPicRenderRows(valueMap);
        } catch(e) {
            console.error(e);
            showNotification('Failed to load internal mandays', 'error');
        } finally {
            document.getElementById('internalLoading').classList.add('hidden');
        }
    }

    function internalPicRenderRows(valueMap) {
        let html = '';
        internalPicPeople.forEach(person => {
            const existing = valueMap[person.employee_id] || {};
            const md  = existing.mandays || 0;
            const add = existing.additional_mandays || 0;
            const appAdd = existing.approved_additional || 0;
            const totalMd = md + appAdd;
            const mdVal  = md  > 0 ? md  : '';
            const addVal = add > 0 ? add : '';
            html += `<tr>
                <td class="px-3 py-2 border border-gray-200 font-medium text-gray-700">${person.name}</td>
                <td class="border border-gray-200 p-0">
                    <input type="number" min="0" step="0.5"
                        class="internal-md-cell w-full px-2 py-1.5 text-xs text-center focus:outline-none focus:bg-teal-50 ${internalPicReadOnly ? 'bg-gray-50 cursor-not-allowed' : 'bg-white'}"
                        data-employee="${person.employee_id}" value="${mdVal}"
                        ${internalPicReadOnly ? 'readonly' : ''} oninput="internalUpdateRowTotal(this)">
                </td>
                <td class="border border-gray-200 p-0">
                    <input type="number" min="0" step="0.5"
                        class="internal-add-cell w-full px-2 py-1.5 text-xs text-center focus:outline-none focus:bg-teal-50 ${internalPicReadOnly ? 'bg-gray-50 cursor-not-allowed' : 'bg-white'}"
                        data-employee="${person.employee_id}" value="${addVal}"
                        ${internalPicReadOnly ? 'readonly' : ''} oninput="internalUpdateRowTotal(this)">
                </td>
                <td class="border border-gray-200 p-0">
                    <input type="text"
                        class="internal-note-cell w-full px-2 py-1.5 text-xs focus:outline-none focus:bg-teal-50 ${internalPicReadOnly ? 'bg-gray-50 cursor-not-allowed' : 'bg-white'}"
                        data-employee="${person.employee_id}" value="${existing.notes || ''}"
                        ${internalPicReadOnly ? 'readonly' : ''} placeholder="notes...">
                </td>
                <td class="px-2 py-1.5 border border-gray-200 text-xs text-center font-semibold bg-gray-50" data-emp-total="${person.employee_id}">${totalMd > 0 ? totalMd.toFixed(1) : '—'}</td>
            </tr>`;
        });
        document.getElementById('internalBody').innerHTML = html;
        document.getElementById('internalTable').classList.remove('hidden');
        document.getElementById('internalBtnSave').classList.toggle('hidden', internalPicReadOnly);
        document.getElementById('internalBtnSubmit').classList.toggle('hidden', internalPicReadOnly);
        internalUpdateTotal();
    }

    function internalUpdateRowTotal(inp) {
        const row = inp.closest('tr');
        const mdVal  = parseFloat(row.querySelector('.internal-md-cell')?.value)  || 0;
        const addVal = parseFloat(row.querySelector('.internal-add-cell')?.value) || 0;
        // For PIC view, approved_additional comes from existing data (not editable here)
        const empId = inp.dataset.employee;
        const existingApproved = (internalPicData?.details || []).find(d => d.employee_id == empId)?.approved_additional || 0;
        const totalMd = mdVal + existingApproved;
        const totalCell = row.querySelector(`[data-emp-total="${empId}"]`);
        if (totalCell) totalCell.textContent = totalMd > 0 ? totalMd.toFixed(1) : '—';
        internalUpdateTotal();
    }

    function internalUpdateTotal() {
        let total = 0;
        document.querySelectorAll('[data-emp-total]').forEach(cell => {
            const v = parseFloat(cell.textContent) || 0;
            total += v;
        });
        document.getElementById('internalTotalDisplay').textContent = total.toFixed(1);
        const footer = document.getElementById('internalFooterTotal');
        if (footer) footer.textContent = total.toFixed(1);
    }

    function internalPicGetPayload() {
        const details = [];
        document.querySelectorAll('.internal-md-cell').forEach(inp => {
            const row   = inp.closest('tr');
            const empId = parseInt(inp.dataset.employee);
            const md    = parseFloat(inp.value) || 0;
            const add   = parseFloat(row.querySelector('.internal-add-cell')?.value) || 0;
            const notes = row.querySelector('.internal-note-cell')?.value || '';
            if (md > 0 || add > 0) {
                details.push({ employee_id: empId, mandays: md, additional_mandays: add, notes });
            }
        });
        return { details, notes: document.getElementById('internalNotes').value };
    }

    async function internalPicSaveDraft() {
        const btn = document.getElementById('internalBtnSave');
        btn.disabled = true; btn.textContent = 'Saving...';
        try {
            const res = await fetch(MANDAYS_API('internal'), {
                method: 'POST', headers: getHeaders(), credentials: 'same-origin',
                body: JSON.stringify(internalPicGetPayload()),
            });
            const data = await res.json();
            if (data.success) {
                showNotification('Internal draft saved!', 'success');
                internalUpdateSidebarBadge(data.internal_mandays_status);
                internalPicData = data.data;
            } else {
                showNotification(data.message || 'Failed', 'error');
            }
        } catch(e) { showNotification('Error: ' + e.message, 'error'); }
        finally { btn.disabled = false; btn.textContent = 'Save Draft'; }
    }

    async function internalPicSubmit() {
        // Save first then submit
        const btn = document.getElementById('internalBtnSubmit');
        btn.disabled = true; btn.textContent = 'Submitting...';
        try {
            // Save
            const saveRes = await fetch(MANDAYS_API('internal'), {
                method: 'POST', headers: getHeaders(), credentials: 'same-origin',
                body: JSON.stringify(internalPicGetPayload()),
            });
            const saveData = await saveRes.json();
            if (!saveData.success) { showNotification(saveData.message || 'Save failed', 'error'); return; }

            // Submit
            const subRes = await fetch(MANDAYS_API('internal/submit'), {
                method: 'POST', headers: getHeaders(), credentials: 'same-origin',
            });
            const subData = await subRes.json();
            if (subData.success) {
                showNotification('Submitted to Head of Support!', 'success');
                internalUpdateSidebarBadge(subData.internal_mandays_status);
                closePicInternalModal();
            } else {
                showNotification(subData.message || 'Submit failed', 'error');
            }
        } catch(e) { showNotification('Error: ' + e.message, 'error'); }
        finally { btn.disabled = false; btn.textContent = 'Submit to Head'; }
    }

    function internalUpdateSidebarBadge(status) {
        const badges = {
            'none':        ['bg-gray-100 text-gray-500',   'None'],
            'draft':       ['bg-yellow-100 text-yellow-700','Draft'],
            'pending_head':['bg-blue-100 text-blue-700',   'Pending Head'],
            'approved':    ['bg-green-100 text-green-700', 'Approved'],
            'rejected':    ['bg-red-100 text-red-700',     'Rejected'],
        };
        const el = document.getElementById('internalBadge');
        if (el && badges[status]) {
            el.className = `inline-block px-2 py-0.5 rounded text-[10px] font-semibold ${badges[status][0]}`;
            el.textContent = badges[status][1];
        }
    }


    // ==================== HELPDESK: CUSTOMER MANDAYS REVIEW ====================
    async function openHdMandaysModal() {
        const modal = document.getElementById('hdMandaysModal');
        if (!modal) return;
        modal.classList.remove('hidden'); modal.classList.add('flex');
        document.getElementById('hdMandaysLoading').classList.remove('hidden');
        document.getElementById('hdMandaysContent').classList.add('hidden');
        document.getElementById('hdMandaysBanner').classList.add('hidden');
        document.getElementById('hdCancelConfirmWrap')?.classList.add('hidden');

        try {
            const [modRes, draftRes] = await Promise.all([
                fetch(MANDAYS_API('modules'), { headers: getHeaders(), credentials: 'same-origin' }),
                fetch(MANDAYS_API('hd-draft'), { headers: getHeaders(), credentials: 'same-origin' }),
            ]);
            const modData   = await modRes.json();
            const draftData = await draftRes.json();
            const modules   = modData.data || [];
            const proposal  = draftData.data;
            const status    = draftData.ticket_mandays_status || 'none';

            // Human-readable status label
            const statusLabels = {
                none: 'None', pic_draft: 'PIC Draft', pending_helpdesk: 'Pending Helpdesk',
                sent_to_chat: 'Awaiting Customer Response', approved: 'Approved', canceled: 'Canceled'
            };
            document.getElementById('hdMandaysStatusLabel').textContent = statusLabels[status] || status;

            if (!proposal) {
                document.getElementById('hdMandaysContent').innerHTML = '<p class="text-sm text-gray-400 text-center py-4">No proposal found.</p>';
                document.getElementById('hdMandaysContent').classList.remove('hidden');
                return;
            }

            // ---- State-specific banner & UI ----
            const banner = document.getElementById('hdMandaysBanner');
            const rejWrap = document.getElementById('hdRejectionReasonWrap');

            banner.className = 'hidden mb-4 rounded-lg px-4 py-3 text-sm font-medium items-start gap-3';
            rejWrap.classList.add('hidden');

            const isCustomerRejected = status === 'pending_helpdesk' && !!proposal.rejection_reason;
            const isPicSubmitted     = status === 'pending_helpdesk' && !proposal.rejection_reason;
            const isSentToChat       = status === 'sent_to_chat';
            const isApproved         = status === 'approved';
            const isCanceled         = status === 'canceled';

            if (isCanceled) {
                let cancelHtml = `<span class="text-gray-600 text-base mt-0.5">✕</span>
                    <div><p class="font-semibold text-gray-800">Proposal Canceled by Helpdesk</p>`;
                if (proposal.cancel_notes) {
                    cancelHtml += `<p class="text-xs font-normal text-gray-600 mt-0.5">${proposal.cancel_notes.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</p>`;
                }
                if (proposal.canceled_by_name) {
                    cancelHtml += `<p class="text-xs font-normal text-gray-500 mt-0.5">— ${proposal.canceled_by_name.replace(/</g,'&lt;').replace(/>/g,'&gt;')}</p>`;
                }
                cancelHtml += '</div>';
                banner.innerHTML = cancelHtml;
                banner.classList.remove('hidden');
                banner.classList.add('flex', 'bg-gray-100', 'border', 'border-gray-300', 'text-gray-700');
            } else if (isApproved) {
                const ts = proposal.customer_response_at
                    ? new Date(proposal.customer_response_at).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta', day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) + ' WIB'
                    : '';
                banner.innerHTML = `<span class="text-green-700 text-base mt-0.5">✓</span>
                    <div><p class="font-semibold text-green-800">Approved by Customer</p>
                    ${ts ? `<p class="text-xs font-normal text-green-700 mt-0.5">${ts}</p>` : ''}</div>`;
                banner.classList.remove('hidden');
                banner.classList.add('flex', 'bg-green-50', 'border', 'border-green-200', 'text-green-800');
            } else if (isCustomerRejected) {
                const ts = proposal.customer_response_at
                    ? new Date(proposal.customer_response_at).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta', day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) + ' WIB'
                    : '';
                banner.innerHTML = `<span class="text-red-700 text-base mt-0.5">✕</span>
                    <div><p class="font-semibold text-red-800">Rejected by Customer</p>
                    ${ts ? `<p class="text-xs font-normal text-red-700 mt-0.5">${ts}</p>` : ''}</div>`;
                banner.classList.remove('hidden');
                banner.classList.add('flex', 'bg-red-50', 'border', 'border-red-200', 'text-red-800');
                document.getElementById('hdRejectionReasonText').textContent = proposal.rejection_reason;
                rejWrap.classList.remove('hidden');
            }

            // Build table
            const valueMap = {};
            const activities = [];
            (proposal.details || []).forEach(d => {
                const act = d.activity || 'General';
                if (!activities.includes(act)) activities.push(act);
                if (!valueMap[act]) valueMap[act] = {};
                valueMap[act][d.module] = d.mandays;
            });
            const mods = modules.length > 0 ? modules : [...new Set((proposal.details||[]).map(d=>d.module))];

            let headHtml = '<tr class="bg-gray-50"><th class="px-2 py-2 text-left text-xs font-semibold border border-gray-200">Activity</th>';
            mods.forEach(m => headHtml += `<th class="px-2 py-2 text-center text-xs font-semibold border border-gray-200">${m}</th>`);
            headHtml += '</tr>';
            document.getElementById('hdMandaysHead').innerHTML = headHtml;

            // Table is editable only when Helpdesk can still make changes
            const isEditable = isPicSubmitted || isCustomerRejected;
            let bodyHtml = '';
            activities.forEach(act => {
                bodyHtml += `<tr><td class="px-2 py-1.5 border border-gray-200 text-xs font-medium">${act}</td>`;
                mods.forEach(m => {
                    const val = valueMap[act]?.[m] || '';
                    bodyHtml += `<td class="border border-gray-200 p-0">
                        <input type="number" min="0" step="0.5" class="hd-cell w-full px-2 py-1.5 text-xs text-center focus:outline-none ${isEditable?'focus:bg-indigo-50 bg-white':'bg-gray-50 cursor-not-allowed'}"
                        data-activity="${act}" data-module="${m}" value="${val}" ${isEditable?'':'readonly'} oninput="hdUpdateTotal()">
                    </td>`;
                });
                bodyHtml += '</tr>';
            });
            document.getElementById('hdMandaysBody').innerHTML = bodyHtml;

            let footHtml = '<tr class="bg-gray-50 font-bold"><td class="px-2 py-1.5 border border-gray-200 text-xs text-right">Total</td>';
            mods.forEach(m => footHtml += `<td id="hdColTotal_${m}" class="px-2 py-1.5 border border-gray-200 text-xs text-center">0</td>`);
            footHtml += '</tr>';
            document.getElementById('hdMandaysFoot').innerHTML = footHtml;

            // Show/hide buttons per state
            ['hdBtnSendToChat','hdBtnReviseResend','hdBtnApprove','hdBtnCancel','hdBtnNewProposal'].forEach(id => {
                document.getElementById(id)?.classList.add('hidden');
            });
            if (isPicSubmitted) {
                document.getElementById('hdBtnSendToChat')?.classList.remove('hidden');
                document.getElementById('hdBtnApprove')?.classList.remove('hidden');
                document.getElementById('hdBtnCancel')?.classList.remove('hidden');
            } else if (isCustomerRejected) {
                document.getElementById('hdBtnReviseResend')?.classList.remove('hidden');
                document.getElementById('hdBtnCancel')?.classList.remove('hidden');
            } else if (isSentToChat) {
                // Helpdesk approve setelah baca chat dari customer
                document.getElementById('hdBtnApprove')?.classList.remove('hidden');
                document.getElementById('hdBtnCancel')?.classList.remove('hidden');
            } else if (isCanceled) {
                document.getElementById('hdBtnNewProposal')?.classList.remove('hidden');
            }
            // approved: no buttons

            document.getElementById('hdMandaysContent').classList.remove('hidden');
            hdUpdateTotal();
        } catch(e) {
            console.error(e);
            showNotification('Failed to load proposal', 'error');
        } finally {
            document.getElementById('hdMandaysLoading').classList.add('hidden');
        }
    }

    function closeHdMandaysModal() {
        const modal = document.getElementById('hdMandaysModal');
        if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
        // Reset cancel confirm section
        document.getElementById('hdCancelConfirmWrap')?.classList.add('hidden');
        const hdCancelNotes = document.getElementById('hdCancelNotes');
        if (hdCancelNotes) hdCancelNotes.value = '';
    }

    function hdUpdateTotal() {
        let grand = 0;
        const colTotals = {};
        document.querySelectorAll('.hd-cell').forEach(inp => {
            const v = parseFloat(inp.value) || 0;
            colTotals[inp.dataset.module] = (colTotals[inp.dataset.module] || 0) + v;
            grand += v;
        });
        Object.entries(colTotals).forEach(([m, t]) => {
            const el = document.getElementById(`hdColTotal_${m}`);
            if (el) el.textContent = t.toFixed(1);
        });
        const totalEl = document.getElementById('hdTotalDisplay');
        if (totalEl) totalEl.textContent = grand.toFixed(1);
    }

    async function hdSaveAndAction(endpoint, method = 'POST', extraBody = {}) {
        // Save edits first (only if cells exist and are editable)
        const details = [];
        document.querySelectorAll('.hd-cell:not([readonly])').forEach(inp => {
            const v = parseFloat(inp.value) || 0;
            if (v > 0) details.push({ activity: inp.dataset.activity, module: inp.dataset.module, mandays: v });
        });
        if (details.length > 0) {
            await fetch(MANDAYS_API('hd-draft'), {
                method: 'PUT', headers: getHeaders(), credentials: 'same-origin',
                body: JSON.stringify({ details }),
            });
        }
        const res = await fetch(MANDAYS_API(endpoint), {
            method, headers: getHeaders(), credentials: 'same-origin',
            body: JSON.stringify(extraBody),
        });
        return res.json();
    }

    async function hdSubmitToChat() {
        const btn = document.getElementById('hdBtnSendToChat');
        if (btn) { btn.disabled = true; btn.textContent = 'Sending...'; }
        try {
            const data = await hdSaveAndAction('hd-draft/submit-chat');
            if (data.success) {
                if (data.email_warning) {
                    showNotification('Status updated. Warning: ' + data.email_warning, 'warning');
                } else {
                    showNotification(data.message || 'Sent to customer via email!', 'success');
                }
                closeHdMandaysModal();
            } else {
                showNotification(data.message || 'Failed to send.', 'error');
            }
        } catch(e) {
            showNotification('Error: ' + e.message, 'error');
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = 'Send to Customer'; }
        }
    }
    async function hdReviseResend() {
        const btn = document.getElementById('hdBtnReviseResend');
        if (btn) { btn.disabled = true; btn.textContent = 'Sending...'; }
        try {
            const data = await hdSaveAndAction('hd-draft/submit-chat');
            if (data.success) {
                if (data.email_warning) {
                    showNotification('Status updated. Warning: ' + data.email_warning, 'warning');
                } else {
                    showNotification(data.message || 'Revised proposal sent to customer!', 'success');
                }
                closeHdMandaysModal();
            } else {
                showNotification(data.message || 'Failed to send.', 'error');
            }
        } catch(e) {
            showNotification('Error: ' + e.message, 'error');
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = 'Revise & Resend'; }
        }
    }
    async function hdApprove() {
        try {
            const data = await hdSaveAndAction('hd-draft/approve');
            if (data.success) {
                showNotification('Customer mandays approved!', 'success');
                closeHdMandaysModal();
            } else showNotification(data.message || 'Failed', 'error');
        } catch(e) { showNotification('Error: '+e.message,'error'); }
    }
    function hdShowCancelConfirm() {
        // Hide action buttons and show cancel confirmation form
        ['hdBtnSendToChat','hdBtnReviseResend','hdBtnApprove','hdBtnCancel','hdBtnNewProposal'].forEach(id => {
            document.getElementById(id)?.classList.add('hidden');
        });
        document.getElementById('hdCancelConfirmWrap').classList.remove('hidden');
        document.getElementById('hdCancelNotes').value = '';
        document.getElementById('hdCancelNotes').focus();
    }
    function hdCancelAbort() {
        document.getElementById('hdCancelConfirmWrap').classList.add('hidden');
        // Restore buttons by re-running the open modal state (reload)
        openHdMandaysModal();
    }
    async function hdCancelConfirm() {
        const cancelNotes = document.getElementById('hdCancelNotes').value.trim();
        const confirmBtn  = document.querySelector('#hdCancelConfirmWrap button:last-child');
        if (confirmBtn) { confirmBtn.disabled = true; confirmBtn.textContent = 'Canceling...'; }
        try {
            const res = await fetch(MANDAYS_API('hd-draft/cancel'), {
                method: 'POST',
                headers: getHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify({ cancel_notes: cancelNotes }),
            });
            const data = await res.json();
            if (data.success) {
                showNotification('Proposal canceled.', 'success');
                closeHdMandaysModal();
            } else {
                showNotification(data.message || 'Failed to cancel.', 'error');
                if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.textContent = 'Confirm Cancel'; }
            }
        } catch(e) {
            showNotification('Error: ' + e.message, 'error');
            if (confirmBtn) { confirmBtn.disabled = false; confirmBtn.textContent = 'Confirm Cancel'; }
        }
    }
    async function hdCreateNewProposal() {
        showNotification('Ask the PIC to submit a new draft.', 'info');
        closeHdMandaysModal();
    }


    // ==================== HEAD OF SUPPORT: INTERNAL MANDAYS ====================
    async function openHeadInternalModal() {
        const modal = document.getElementById('headInternalModal');
        if (!modal) return;
        modal.classList.remove('hidden'); modal.classList.add('flex');
        document.getElementById('headInternalLoading').classList.remove('hidden');
        document.getElementById('headInternalContent').classList.add('hidden');
        document.getElementById('headInternalStatusBanner').classList.add('hidden');
        document.getElementById('headRejectWrap').classList.add('hidden');
        document.getElementById('headBtnConfirmReject').classList.add('hidden');

        try {
            const res  = await fetch(MANDAYS_API('internal'), { headers: getHeaders(), credentials: 'same-origin' });
            const data = await res.json();
            const proposal = data.data;
            const status   = data.internal_mandays_status || 'none';

            const headStatusLabels = {
                'none':         'None',
                'draft':        'Draft',
                'pending_head': 'Pending Review',
                'approved':     'Approved',
                'rejected':     'Needs Revision',
            };
            document.getElementById('headInternalStatusLabel').textContent = headStatusLabels[status] || status;

            if (!proposal) {
                document.getElementById('headInternalContent').innerHTML = '<p class="text-sm text-gray-400 text-center py-4">No proposal submitted yet.</p>';
                document.getElementById('headInternalContent').classList.remove('hidden');
                document.getElementById('headBtnApprove').classList.add('hidden');
                document.getElementById('headBtnToggleReject').classList.add('hidden');
                return;
            }

            // Build per-employee map (sum across modules)
            const empMap = {};
            (proposal.details || []).forEach(d => {
                const eid = d.employee_id;
                if (!empMap[eid]) empMap[eid] = { name: d.employee_name || '—', mandays: 0, additional_mandays: 0, approved_additional: 0, notes: '' };
                empMap[eid].mandays            += parseFloat(d.mandays || 0);
                empMap[eid].additional_mandays += parseFloat(d.additional_mandays || 0);
                empMap[eid].approved_additional+= parseFloat(d.approved_additional || 0);
                if (d.notes) empMap[eid].notes = d.notes;
            });

            const isPending = status === 'pending_head';
            let bodyHtml = '';
            let grandTotal = 0;
            Object.entries(empMap).forEach(([eid, emp]) => {
                const currentApprAdd = emp.approved_additional;
                const rowTotal = emp.mandays + currentApprAdd;
                grandTotal += rowTotal;
                bodyHtml += `<tr>
                    <td class="px-3 py-2 border border-gray-200 text-xs font-medium">${emp.name}</td>
                    <td class="px-3 py-2 border border-gray-200 text-xs text-center">${emp.mandays > 0 ? emp.mandays.toFixed(1) : '—'}</td>
                    <td class="px-3 py-2 border border-gray-200 text-xs text-center">${emp.additional_mandays > 0 ? emp.additional_mandays.toFixed(1) : '—'}</td>
                    <td class="px-3 py-2 border border-gray-200 text-xs text-gray-500">${emp.notes || ''}</td>
                    <td class="border border-gray-200 p-0">
                        ${isPending
                            ? `<input type="number" min="0" step="0.5"
                                class="head-approve-add w-full px-2 py-1.5 text-xs text-center focus:outline-none focus:bg-green-50 bg-white"
                                data-employee="${eid}" data-mandays="${emp.mandays}"
                                value="${currentApprAdd > 0 ? currentApprAdd : ''}"
                                oninput="headUpdateRowTotal(this)">`
                            : `<span class="block px-2 py-1.5 text-xs text-center">${currentApprAdd > 0 ? currentApprAdd.toFixed(1) : '—'}</span>`
                        }
                    </td>
                    <td class="px-2 py-1.5 border border-gray-200 text-xs text-center font-semibold bg-gray-50" data-head-total="${eid}">${rowTotal > 0 ? rowTotal.toFixed(1) : '—'}</td>
                </tr>`;
            });
            document.getElementById('headInternalBody').innerHTML = bodyHtml;
            document.getElementById('headInternalTotal').textContent = grandTotal.toFixed(1);

            if (proposal.proposed_by) {
                document.getElementById('headProposedBy').textContent = 'Proposed by: ' + proposal.proposed_by;
            }
            if (proposal.notes) {
                const nw = document.getElementById('headInternalNoteWrap');
                nw.textContent = 'Notes: ' + proposal.notes;
                nw.classList.remove('hidden');
            }

            // Status info banner
            const bannerEl = document.getElementById('headInternalStatusBanner');
            if (status === 'approved') {
                bannerEl.className = 'mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700';
                bannerEl.innerHTML = '<p class="font-semibold">Proposal Approved</p>'
                    + (proposal.approved_by_head ? '<p class="text-xs mt-0.5">Approved by: ' + proposal.approved_by_head + '</p>' : '');
                bannerEl.classList.remove('hidden');
            } else if (status === 'draft') {
                bannerEl.className = 'mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg text-sm text-yellow-700';
                bannerEl.innerHTML = '<p class="font-semibold">Draft — not yet submitted for review.</p>';
                bannerEl.classList.remove('hidden');
            } else if (status === 'rejected') {
                bannerEl.className = 'mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700';
                bannerEl.innerHTML = '<p class="font-semibold">Proposal sent back for revision.</p>'
                    + (proposal.rejection_reason ? '<p class="text-xs mt-0.5">Reason: ' + proposal.rejection_reason + '</p>' : '');
                bannerEl.classList.remove('hidden');
            }

            // Show approve/reject only if pending
            document.getElementById('headBtnApprove').classList.toggle('hidden', !isPending);
            document.getElementById('headBtnToggleReject').classList.toggle('hidden', !isPending);

            document.getElementById('headInternalContent').classList.remove('hidden');
        } catch(e) {
            console.error(e);
            showNotification('Failed to load internal proposal', 'error');
        } finally {
            document.getElementById('headInternalLoading').classList.add('hidden');
        }
    }

    function closeHeadInternalModal() {
        const modal = document.getElementById('headInternalModal');
        if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
    }

    function headToggleReject() {
        const wrap = document.getElementById('headRejectWrap');
        const btn  = document.getElementById('headBtnConfirmReject');
        const isHidden = wrap.classList.contains('hidden');
        wrap.classList.toggle('hidden', !isHidden);
        btn.classList.toggle('hidden', !isHidden);
    }

    function headUpdateRowTotal(inp) {
        const row      = inp.closest('tr');
        const md       = parseFloat(inp.dataset.mandays) || 0;
        const apprAdd  = parseFloat(inp.value) || 0;
        const total    = md + apprAdd;
        const empId    = inp.dataset.employee;
        const cell     = row.querySelector(`[data-head-total="${empId}"]`);
        if (cell) cell.textContent = total > 0 ? total.toFixed(1) : '—';
        // Update grand total
        let grand = 0;
        document.querySelectorAll('[data-head-total]').forEach(c => grand += parseFloat(c.textContent) || 0);
        document.getElementById('headInternalTotal').textContent = grand.toFixed(1);
    }

    async function headInternalApprove() {
        const btn = document.getElementById('headBtnApprove');
        btn.disabled = true; btn.textContent = 'Approving...';
        try {
            // Collect approved_additional per employee
            const approvedDetails = [];
            document.querySelectorAll('.head-approve-add').forEach(inp => {
                approvedDetails.push({
                    employee_id:        parseInt(inp.dataset.employee),
                    approved_additional: parseFloat(inp.value) || 0,
                });
            });

            const res  = await fetch(MANDAYS_API('internal/approve'), {
                method: 'POST', headers: getHeaders(), credentials: 'same-origin',
                body: JSON.stringify({ approved_details: approvedDetails }),
            });
            const data = await res.json();
            if (data.success) {
                showNotification('Internal proposal approved!', 'success');
                internalUpdateSidebarBadge?.(data.internal_mandays_status);
                closeHeadInternalModal();
            } else showNotification(data.message || 'Failed', 'error');
        } catch(e) { showNotification('Error: '+e.message,'error'); }
        finally { btn.disabled = false; btn.textContent = 'Approve'; }
    }

    async function headInternalReject() {
        const reason = document.getElementById('headRejectReason').value.trim();
        if (!reason) { showNotification('Please provide a rejection reason', 'warning'); return; }
        const btn = document.getElementById('headBtnConfirmReject');
        btn.disabled = true; btn.textContent = 'Rejecting...';
        try {
            const res  = await fetch(MANDAYS_API('internal/reject'), {
                method: 'POST', headers: getHeaders(), credentials: 'same-origin',
                body: JSON.stringify({ rejection_reason: reason }),
            });
            const data = await res.json();
            if (data.success) {
                showNotification('Proposal rejected.', 'success');
                internalUpdateSidebarBadge?.(data.internal_mandays_status);
                closeHeadInternalModal();
            } else showNotification(data.message || 'Failed', 'error');
        } catch(e) { showNotification('Error: '+e.message,'error'); }
        finally { btn.disabled = false; btn.textContent = 'Confirm Reject'; }
    }

