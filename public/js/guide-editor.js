/*
 * User Guide Editor: penyunting visual (mirip Word) yang tetap MENYIMPAN Markdown.
 *
 * - Tampilan sunting memakai gaya yang sama dengan halaman baca (.guide-body), jadi "yang terlihat = yang dibaca".
 * - Penyimpanan tetap Markdown (kompatibel dengan berkas bawaan, PDF, pencarian, dan aturan isi di server).
 * - Konversi HTML -> Markdown ditulis sendiri untuk himpunan bagian yang dipakai panduan: judul (##, ###), paragraf, tebal/miring/coret,
 *   nama layar (`kode`), daftar bertingkat, kotak catatan (> [!NOTE]), tabel, tautan topik ([[key]]), gambar lokal, garis pemisah.
 * - Tanpa library luar; tidak ada HTML mentah yang pernah tersimpan (hanya Markdown yang dikirim ke server).
 *
 * API: GuideEditor.init(cfg) dan GuideEditor.toMarkdown(rootElement, titles) (dipakai uji peramban).
 */
(function (global) {
    'use strict';

    var BLOCK = { P: 1, H1: 1, H2: 1, H3: 1, H4: 1, H5: 1, H6: 1, UL: 1, OL: 1, BLOCKQUOTE: 1, DIV: 1, SECTION: 1, ARTICLE: 1, FIGURE: 1, TABLE: 1, HR: 1, PRE: 1 };
    var CALLOUT_TYPES = ['note', 'tip', 'important', 'warning'];

    /* ───────────────────────── HTML -> Markdown ───────────────────────── */

    function toMarkdown(root, titles) {
        titles = titles || {};
        var inTable = false;

        function esc(t) {
            t = t.replace(/([\\`*_\[\]~<])/g, function (m) { return '\\' + m; });
            t = t.replace(/&(?=#?\w+;)/g, function () { return '\\&'; });
            if (inTable) { t = t.replace(/\|/g, function () { return '\\|'; }); }
            return t;
        }

        /* Awal baris yang akan terbaca sebagai judul, kutipan, daftar, atau garis jika tidak di-escape. */
        function fixStart(s) {
            return s.split('\n').map(function (l) {
                return l
                    .replace(/^(\s*)(#{1,6})(\s|$)/, function (m, a, b, c) { return a + '\\' + b + c; })
                    .replace(/^(\s*)>/, function (m, a) { return a + '\\>'; })
                    .replace(/^(\s*)([-+])(\s)/, function (m, a, b, c) { return a + '\\' + b + c; })
                    .replace(/^(\s*)(\d+)([.)])(\s|$)/, function (m, a, b, c, d) { return a + b + '\\' + c + d; })
                    .replace(/^(\s*)(-{3,}|={3,})\s*$/, function (m, a, b) { return a + '\\' + b; });
            }).join('\n');
        }

        function trimEdges(s) {
            return s.replace(/^(?:[ \t]|\\\n)+/, '').replace(/(?:[ \t]|\\\n)+$/, '');
        }

        function wrap(el, mark) {
            var inner = inline(el);
            if (!inner.trim()) { return inner; }
            var lead = inner.match(/^\s*/)[0], trail = inner.match(/\s*$/)[0];
            return lead + mark + inner.trim() + mark + trail;
        }

        function code(text) {
            text = text.replace(/\s+/g, ' ');
            if (inTable) { text = text.replace(/\|/g, function () { return '\\|'; }); }
            if (!text.trim()) { return ''; }
            var longest = (text.match(/`+/g) || []).reduce(function (m, s) { return Math.max(m, s.length); }, 0);
            var fence = new Array(longest + 2).join('`');
            var pad = /^`|`$/.test(text) ? ' ' : '';
            return fence + pad + text + pad + fence;
        }

        function link(el) {
            var href = el.getAttribute('href') || '';
            var m = /^#topic-([a-z0-9-]+)$/.exec(href);
            var plain = el.textContent.replace(/\s+/g, ' ').trim();
            if (m) {
                if (!plain || plain === titles[m[1]]) { return '[[' + m[1] + ']]'; }
                return '[[' + m[1] + '|' + plain.replace(/[\]|]/g, '') + ']]';
            }
            var label = inline(el);
            if (!label.trim()) { return ''; }
            return '[' + label + '](' + href + ')';
        }

        function image(el) {
            var src = el.getAttribute('src') || '';
            var m = /images\/guide\/([A-Za-z0-9._-]+)(?:[?#].*)?$/.exec(src);
            if (m) { src = 'images/guide/' + m[1]; }
            return '![' + esc(el.getAttribute('alt') || '') + '](' + src + ')';
        }

        function inlineNode(n) {
            if (n.nodeType === 3) { return esc(n.nodeValue.replace(/ /g, ' ').replace(/\s+/g, ' ')); }
            if (n.nodeType !== 1) { return ''; }
            switch (n.tagName) {
                case 'STRONG': case 'B': return wrap(n, '**');
                case 'EM': case 'I': return wrap(n, '*');
                case 'DEL': case 'S': case 'STRIKE': return wrap(n, '~~');
                case 'CODE': return code(n.textContent);
                case 'A': return link(n);
                case 'BR': return inTable ? ' ' : '\\\n';
                case 'IMG': return image(n);
                default: return inline(n);
            }
        }

        function inline(el) {
            var s = '';
            Array.prototype.forEach.call(el.childNodes, function (c) { s += inlineNode(c); });
            return s;
        }

        function quote(s) {
            return s.split('\n').map(function (l) { return l ? '> ' + l : '>'; }).join('\n');
        }

        function callout(el) {
            var m = /callout-(note|tip|important|warning)/.exec(el.className);
            var type = m ? m[1] : 'note';
            var content = blocks(el).join('\n\n');
            return '> [!' + type.toUpperCase() + ']' + (content ? '\n' + quote(content) : '');
        }

        function table(el) {
            var rows = Array.prototype.map.call(el.querySelectorAll('tr'), function (tr) {
                return Array.prototype.map.call(tr.children, function (td) {
                    inTable = true;
                    var s = inline(td).replace(/\\\n/g, ' ').replace(/\s+/g, ' ').trim();
                    inTable = false;
                    return s;
                });
            }).filter(function (r) { return r.length; });
            if (!rows.length) { return ''; }
            var cols = rows.reduce(function (m, r) { return Math.max(m, r.length); }, 1);
            rows = rows.map(function (r) { while (r.length < cols) { r.push(''); } return r; });
            var line = function (r) { return '| ' + r.join(' | ') + ' |'; };
            var sep = '| ' + rows[0].map(function () { return '---'; }).join(' | ') + ' |';
            return [line(rows[0]), sep].concat(rows.slice(1).map(line)).join('\n');
        }

        function liParts(li) {
            var parts = [], run = [];
            function flush() {
                if (!run.length) { return; }
                var s = '';
                run.forEach(function (n) { s += inlineNode(n); });
                run = [];
                s = fixStart(trimEdges(s));
                if (s) { parts.push({ k: 'text', s: s }); }
            }
            Array.prototype.forEach.call(li.childNodes, function (c) {
                if (c.nodeType === 3) { run.push(c); return; }
                if (c.nodeType !== 1) { return; }
                if (c.tagName === 'UL' || c.tagName === 'OL') { flush(); parts.push({ k: 'list', s: list(c) }); return; }
                if (BLOCK[c.tagName]) {
                    flush();
                    var b = block(c);
                    if (b) { parts.push({ k: 'text', s: b }); }
                    return;
                }
                run.push(c);
            });
            flush();
            return parts;
        }

        function list(el) {
            var ordered = el.tagName === 'OL';
            var n = parseInt(el.getAttribute('start') || '1', 10) || 1;
            var items = [];
            Array.prototype.forEach.call(el.children, function (c) {
                if (c.tagName === 'LI') { items.push({ li: c, extra: [] }); return; }
                if (c.tagName === 'UL' || c.tagName === 'OL') {
                    if (!items.length) { items.push({ li: null, extra: [] }); }
                    items[items.length - 1].extra.push(c);   /* peramban kadang menaruh daftar bersarang langsung di dalam daftar */
                }
            });
            var lines = [];
            items.forEach(function (it) {
                var marker = ordered ? (n++) + '. ' : '- ';
                var pad = new Array(marker.length + 1).join(' ');
                var parts = it.li ? liParts(it.li) : [];
                it.extra.forEach(function (l) { parts.push({ k: 'list', s: list(l) }); });
                var text = '';
                parts.forEach(function (p, i) {
                    text += i === 0 ? p.s : (p.k === 'list' ? '\n' : '\n\n') + p.s;
                });
                if (parts.length && parts[0].k === 'list') { text = '\n' + text; }
                var ls = text.split('\n');
                lines.push((marker + ls[0]).replace(/\s+$/, ''));
                ls.slice(1).forEach(function (l) { lines.push(l ? pad + l : ''); });
            });
            return lines.join('\n');
        }

        function block(el) {
            switch (el.tagName) {
                case 'P':
                    /* peramban kadang menaruh daftar/tabel di dalam <p>; perlakukan isinya sebagai blok */
                    if (Array.prototype.some.call(el.children, function (c) { return BLOCK[c.tagName]; })) { return blocks(el).join('\n\n'); }
                    return fixStart(trimEdges(inline(el)));
                case 'H1': case 'H2': { var h2 = trimEdges(inline(el)).replace(/\\\n/g, ' '); return h2 ? '## ' + h2 : ''; }
                case 'H3': case 'H4': case 'H5': case 'H6': { var h3 = trimEdges(inline(el)).replace(/\\\n/g, ' '); return h3 ? '### ' + h3 : ''; }
                case 'UL': case 'OL': return list(el);
                case 'BLOCKQUOTE': { var q = blocks(el).join('\n\n'); return q ? quote(q) : ''; }
                case 'TABLE': return table(el);
                case 'HR': return '---';
                case 'PRE': return '```\n' + el.textContent.replace(/\n$/, '') + '\n```';
                case 'DIV': return el.classList.contains('callout') ? callout(el) : blocks(el).join('\n\n');
                default: return blocks(el).join('\n\n');
            }
        }

        function blocks(parent) {
            var out = [], run = [];
            function flush() {
                if (!run.length) { return; }
                var s = '';
                run.forEach(function (n) { s += inlineNode(n); });
                run = [];
                s = fixStart(trimEdges(s));
                if (s) { out.push(s); }
            }
            Array.prototype.forEach.call(parent.childNodes, function (c) {
                if (c.nodeType === 3) { run.push(c); return; }
                if (c.nodeType !== 1) { return; }
                if (c.classList && c.classList.contains('callout-title')) { return; }
                if (!BLOCK[c.tagName]) { run.push(c); return; }
                flush();
                var b = block(c);
                if (b) { out.push(b); }
            });
            flush();
            return out;
        }

        return blocks(root).join('\n\n').replace(/\n{3,}/g, '\n\n').trim();
    }

    /* ───────────────────────── Penyunting ───────────────────────── */

    function init(cfg) {
        var visual = cfg.visual, area = cfg.textarea, preview = cfg.preview, form = cfg.form;
        var toolbar = cfg.toolbar, tablebar = cfg.tablebar, status = cfg.status;
        var titles = cfg.titles || {}, topics = cfg.topics || [], calloutTitles = cfg.calloutTitles || {};
        var mode = 'visual', dirty = false, mdChanged = false, anyChange = false, submitting = false;
        var savedRange = null, history = [], future = [], snapTimer = null;

        try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) { /* tidak penting */ }
        try { document.execCommand('styleWithCSS', false, false); } catch (e) { /* tidak penting */ }

        /* ── pembantu ── */
        function sel() { return global.getSelection(); }
        function inVisual(node) { return node && visual.contains(node); }
        function anchorEl() { var s = sel(); if (!s || !s.anchorNode) { return null; } var n = s.anchorNode; return n.nodeType === 3 ? n.parentNode : n; }
        function closest(node, selector) { var el = node && node.nodeType === 3 ? node.parentNode : node; return el && el.closest ? (el.closest(selector) && visual.contains(el.closest(selector)) ? el.closest(selector) : null) : null; }
        function topBlock(node) { var n = node; while (n && n.parentNode !== visual) { n = n.parentNode; } return n && n.nodeType === 1 ? n : null; }
        function say(text) { if (status) { status.textContent = text; clearTimeout(say.t); say.t = setTimeout(function () { status.textContent = ''; }, 3500); } }
        function placeCaret(node, atEnd) {
            var r = document.createRange();
            r.selectNodeContents(node);
            r.collapse(!atEnd);
            var s = sel(); s.removeAllRanges(); s.addRange(r);
        }
        function caretAfter(node) { var r = document.createRange(); r.setStartAfter(node); r.collapse(true); var s = sel(); s.removeAllRanges(); s.addRange(r); }
        function emptyP() { var p = document.createElement('p'); p.appendChild(document.createElement('br')); return p; }

        /* Jalur simpul (indeks anak) untuk memulihkan kursor setelah undo/redo. */
        function pathOf(node) { var p = []; while (node && node !== visual) { p.unshift(Array.prototype.indexOf.call(node.parentNode.childNodes, node)); node = node.parentNode; } return p; }
        function nodeAt(path) { var n = visual; for (var i = 0; i < path.length; i++) { if (!n.childNodes[path[i]]) { return n; } n = n.childNodes[path[i]]; } return n; }
        function caretState() {
            var s = sel();
            if (!s || !s.rangeCount || !inVisual(s.anchorNode)) { return null; }
            return { path: pathOf(s.anchorNode), off: s.anchorOffset };
        }
        function restoreCaret(c) {
            if (!c) { return; }
            try {
                var n = nodeAt(c.path), r = document.createRange();
                r.setStart(n, Math.min(c.off, n.nodeType === 3 ? n.nodeValue.length : n.childNodes.length));
                r.collapse(true);
                var s = sel(); s.removeAllRanges(); s.addRange(r);
            } catch (e) { /* kursor tidak kritis */ }
        }

        /* ── riwayat (undo/redo sendiri, karena operasi kotak catatan/tabel tidak masuk riwayat bawaan peramban) ── */
        function snap() {
            clearTimeout(snapTimer);
            var html = visual.innerHTML;
            if (history.length && history[history.length - 1].html === html) { return; }
            history.push({ html: html, caret: caretState() });
            if (history.length > 100) { history.shift(); }
            future = [];
        }
        function snapSoon() { clearTimeout(snapTimer); snapTimer = setTimeout(snap, 400); }
        function undo() {
            snap();
            if (history.length < 2) { return; }
            future.push(history.pop());
            var prev = history[history.length - 1];
            visual.innerHTML = prev.html; restoreCaret(prev.caret); markDirty(true);
        }
        function redo() {
            if (!future.length) { return; }
            var next = future.pop();
            history.push(next);
            visual.innerHTML = next.html; restoreCaret(next.caret); markDirty(true);
        }

        /* Chrome menaruh daftar/tabel di dalam <p> (<p><ul>..</ul></p>). Pecah <p> seperti itu supaya tiap blok berdiri sendiri
           (kotak catatan, spasi, dan Markdown memakai blok yang benar). Simpul tidak dibuat ulang, jadi kursor dipulihkan. */
        var BLOCK_RE = /^(UL|OL|TABLE|DIV|H[1-6]|P|BLOCKQUOTE|PRE|HR)$/;
        function fixNesting() {
            var s = sel(), keep = s.rangeCount && inVisual(s.anchorNode) ? [s.anchorNode, s.anchorOffset, s.focusNode, s.focusOffset] : null;
            var changed = false;
            Array.prototype.slice.call(visual.querySelectorAll('p')).forEach(function (p) {
                if (!p.parentNode || !Array.prototype.some.call(p.children, function (c) { return BLOCK_RE.test(c.tagName); })) { return; }
                var parent = p.parentNode, out = [], run = null;
                Array.prototype.slice.call(p.childNodes).forEach(function (c) {
                    if (c.nodeType === 1 && BLOCK_RE.test(c.tagName)) { run = null; out.push(c); return; }
                    if (c.nodeType === 3 && !c.nodeValue.trim()) { return; }
                    if (!run) { run = document.createElement('p'); out.push(run); }
                    run.appendChild(c);
                });
                out.forEach(function (n) { parent.insertBefore(n, p); });
                parent.removeChild(p); changed = true;
            });
            if (changed && keep && visual.contains(keep[0]) && visual.contains(keep[2])) {
                try { s.setBaseAndExtent(keep[0], keep[1], keep[2], keep[3]); } catch (e) { /* kursor tidak kritis */ }
            }
        }

        function markDirty(skipSnap) { dirty = true; anyChange = true; fixNesting(); if (!skipSnap) { snapSoon(); } ensureEdges(); refreshState(); }

        /* ── normalisasi isi hasil render server ── */
        function normalize() {
            Array.prototype.forEach.call(visual.querySelectorAll('[id]'), function (e) { e.removeAttribute('id'); });
            Array.prototype.forEach.call(visual.querySelectorAll('.callout-title'), function (e) { e.setAttribute('contenteditable', 'false'); });
            if (!visual.firstChild) { visual.appendChild(emptyP()); }
            ensureEdges();
        }
        function ensureEdges() {
            var first = visual.firstElementChild, last = visual.lastElementChild;
            if (!first) { visual.appendChild(emptyP()); return; }
            if (first.tagName !== 'P' && !/^H[1-6]$/.test(first.tagName)) { visual.insertBefore(emptyP(), first); }
            if (last.tagName !== 'P' && !/^H[1-6]$/.test(last.tagName)) { visual.appendChild(emptyP()); }
        }

        /* ── konversi Visual <-> Markdown ── */
        function trailer() { return (area.value.match(/<!--[\s\S]*?-->/g) || []).join('\n'); }
        function visualToMarkdown() {
            var md = toMarkdown(visual, titles), t = trailer();
            return md + (t ? '\n\n' + t : '') + '\n';
        }
        function syncToTextarea() { if (mode === 'visual' && dirty) { area.value = visualToMarkdown(); dirty = false; } }

        function render(md) {
            return fetch(cfg.previewUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf() },
                body: JSON.stringify({ body: md, key: cfg.key(), locale: cfg.locale })
            }).then(function (r) { if (!r.ok) { throw new Error('http ' + r.status); } return r.json(); }).then(function (j) { return j.html || ''; });
        }

        function setMode(next) {
            if (next === mode) { return; }
            var prev = mode;
            if (prev === 'visual') { syncToTextarea(); }
            mode = next;
            Array.prototype.forEach.call(cfg.tabs, function (b) { b.classList.toggle('is-on', b.dataset.pane === next); b.setAttribute('aria-selected', b.dataset.pane === next ? 'true' : 'false'); });
            visual.hidden = next !== 'visual'; toolbar.hidden = next !== 'visual'; area.hidden = next !== 'markdown'; preview.hidden = next !== 'preview';
            if (tablebar) { tablebar.hidden = true; }
            if (next === 'visual' && mdChanged) {
                visual.textContent = 'Loading...';
                render(area.value).then(function (html) { visual.innerHTML = html; normalize(); mdChanged = false; history = []; snap(); })
                    .catch(function () { say('Could not load the visual editor. Staying in Markdown mode.'); visual.innerHTML = ''; setMode('markdown'); });
            }
            if (next === 'preview') {
                preview.textContent = 'Loading...';
                render(area.value).then(function (html) { preview.innerHTML = html || '<em>Nothing to preview.</em>'; })
                    .catch(function () { preview.textContent = 'Preview failed. Try again.'; });
            }
            if (next === 'markdown') { area.focus(); }
        }

        /* ── perintah ── */
        function exec(cmd, val) { visual.focus(); restoreSaved(); document.execCommand(cmd, false, val || null); markDirty(); }
        function restoreSaved() { var s = sel(); if (savedRange && (!s.rangeCount || !inVisual(s.anchorNode))) { s.removeAllRanges(); s.addRange(savedRange); } }

        function toggleCode() {
            visual.focus(); restoreSaved();
            var s = sel(); if (!s.rangeCount) { return; }
            var existing = closest(s.anchorNode, 'code');
            if (existing) {
                var parent = existing.parentNode;
                while (existing.firstChild) { parent.insertBefore(existing.firstChild, existing); }
                parent.removeChild(existing); markDirty(); return;
            }
            var r = s.getRangeAt(0), el = document.createElement('code');
            if (r.collapsed) { el.textContent = 'Screen name'; r.insertNode(el); var sr = document.createRange(); sr.selectNodeContents(el); s.removeAllRanges(); s.addRange(sr); }
            else { var text = s.toString(); r.deleteContents(); el.textContent = text; r.insertNode(el); caretAfter(el); }
            markDirty();
        }

        function setBlock(tag) { visual.focus(); restoreSaved(); document.execCommand('formatBlock', false, tag.toUpperCase()); markDirty(); }

        function inList() { return closest(anchorEl(), 'li'); }
        function indent(outdent) {
            if (!inList()) { say('Indent works inside lists. Make a list first.'); return; }
            exec(outdent ? 'outdent' : 'indent');
            Array.prototype.forEach.call(visual.querySelectorAll('blockquote'), function (b) { while (b.firstChild) { b.parentNode.insertBefore(b.firstChild, b); } b.remove(); });
        }

        function makeCallout(type) {
            var box = document.createElement('div');
            box.className = 'callout callout-' + type;
            var t = document.createElement('p');
            t.className = 'callout-title'; t.setAttribute('contenteditable', 'false'); t.textContent = calloutTitles[type] || type;
            box.appendChild(t);
            return box;
        }
        function setCallout(type) {
            visual.focus(); restoreSaved();
            var cur = closest(anchorEl(), '.callout');
            if (cur) {
                CALLOUT_TYPES.forEach(function (c) { cur.classList.remove('callout-' + c); });
                cur.classList.add('callout-' + type);
                cur.querySelector('.callout-title').textContent = calloutTitles[type] || type;
                markDirty(); return;
            }
            var blk = topBlock(anchorEl());
            if (!blk) { return; }
            var box = makeCallout(type);
            blk.parentNode.insertBefore(box, blk);
            box.appendChild(blk);
            placeCaret(blk, true); markDirty();
        }
        function removeCallout() {
            var cur = closest(anchorEl(), '.callout');
            if (!cur) { say('Place the cursor inside a call-out box first.'); return; }
            var first = null;
            Array.prototype.slice.call(cur.childNodes).forEach(function (c) {
                if (c.nodeType === 1 && c.classList.contains('callout-title')) { return; }
                cur.parentNode.insertBefore(c, cur); first = first || c;
            });
            cur.remove(); if (first && first.nodeType === 1) { placeCaret(first, true); }
            markDirty();
        }

        function insertTable(rows, cols) {
            visual.focus(); restoreSaved();
            rows = Math.max(2, Math.min(30, rows | 0)); cols = Math.max(1, Math.min(8, cols | 0));
            var wrap = document.createElement('div'); wrap.className = 'g-table';
            var html = '<table><thead><tr>';
            for (var c = 0; c < cols; c++) { html += '<th>Heading</th>'; }
            html += '</tr></thead><tbody>';
            for (var r = 1; r < rows; r++) { html += '<tr>' + new Array(cols + 1).join('<td><br></td>') + '</tr>'; }
            wrap.innerHTML = html + '</tbody></table>';
            var blk = topBlock(anchorEl()) || visual.lastElementChild;
            if (blk && blk.nextSibling) { visual.insertBefore(wrap, blk.nextSibling); } else { visual.appendChild(wrap); }
            ensureEdges();
            var th = wrap.querySelector('th'); placeCaret(th, true);
            var rg = document.createRange(); rg.selectNodeContents(th); sel().removeAllRanges(); sel().addRange(rg);
            markDirty();
        }
        function cellOps(op) {
            var cell = closest(anchorEl(), 'td,th'); if (!cell) { return; }
            var row = cell.parentNode, tbl = cell.closest('table'), idx = Array.prototype.indexOf.call(row.children, cell);
            var rowsAll = tbl.querySelectorAll('tr');
            if (op === 'row-add') {
                var nr = document.createElement('tr');
                Array.prototype.forEach.call(row.children, function () { var td = document.createElement('td'); td.appendChild(document.createElement('br')); nr.appendChild(td); });
                if (row.parentNode.tagName === 'THEAD') { var tb = tbl.tBodies[0] || tbl.appendChild(document.createElement('tbody')); tb.insertBefore(nr, tb.firstChild); }
                else { row.parentNode.insertBefore(nr, row.nextSibling); }
            } else if (op === 'row-del') {
                if (rowsAll.length <= 2) { say('A table needs a heading row and at least one more row.'); return; }
                if (row.parentNode.tagName === 'THEAD') { say('The heading row cannot be removed.'); return; }
                row.remove();
            } else if (op === 'col-add') {
                Array.prototype.forEach.call(rowsAll, function (tr) {
                    var isHead = tr.parentNode.tagName === 'THEAD' || tr.children[0].tagName === 'TH';
                    var c = document.createElement(isHead ? 'th' : 'td'); if (isHead) { c.textContent = 'Heading'; } else { c.appendChild(document.createElement('br')); }
                    tr.insertBefore(c, tr.children[idx + 1] || null);
                });
            } else if (op === 'col-del') {
                if (row.children.length <= 1) { say('A table needs at least one column.'); return; }
                Array.prototype.forEach.call(rowsAll, function (tr) { if (tr.children[idx]) { tr.removeChild(tr.children[idx]); } });
            } else if (op === 'table-del') {
                var w = tbl.closest('.g-table') || tbl; w.remove();
            }
            ensureEdges(); markDirty();
        }

        function insertTopicLink(key) {
            visual.focus(); restoreSaved();
            var s = sel(); if (!s.rangeCount) { return; }
            var title = titles[key] || key, r = s.getRangeAt(0);
            if (r.collapsed) {
                var a = document.createElement('a'); a.setAttribute('href', '#topic-' + key); a.textContent = title;
                r.insertNode(a); caretAfter(a);
            } else {
                document.execCommand('createLink', false, '#topic-' + key);
            }
            markDirty();
        }
        function unlink() { visual.focus(); restoreSaved(); document.execCommand('unlink'); markDirty(); }

        /* ── tempelan: bersihkan gaya dari Word/Google Docs/web ── */
        function sanitize(html) {
            var box = document.createElement('div'); box.innerHTML = html;
            var out = document.createElement('div');
            function walk(src, dst) {
                Array.prototype.forEach.call(src.childNodes, function (n) {
                    if (n.nodeType === 3) { dst.appendChild(document.createTextNode(n.nodeValue)); return; }
                    if (n.nodeType !== 1) { return; }
                    var tag = n.tagName, style = (n.getAttribute('style') || '').toLowerCase(), el = null;
                    if (/^(SCRIPT|STYLE|META|LINK|HEAD|TITLE|XML|O:P)$/.test(tag)) { return; }
                    if (tag === 'H1' || tag === 'H2') { el = document.createElement('h2'); }
                    else if (/^H[3-6]$/.test(tag)) { el = document.createElement('h3'); }
                    else if (tag === 'P' || tag === 'DIV') { el = document.createElement('p'); }
                    else if (/^(UL|OL|LI|TABLE|THEAD|TBODY|TR|TH|TD)$/.test(tag)) { el = document.createElement(tag.toLowerCase()); }
                    else if (tag === 'STRONG' || (tag === 'B' && !/font-weight:\s*(normal|400)/.test(style)) || (tag === 'SPAN' && /font-weight:\s*(bold|[6-9]00)/.test(style))) { el = document.createElement('strong'); }
                    else if (tag === 'EM' || tag === 'I' || (tag === 'SPAN' && /font-style:\s*italic/.test(style))) { el = document.createElement('em'); }
                    else if (tag === 'DEL' || tag === 'S' || tag === 'STRIKE') { el = document.createElement('del'); }
                    else if (tag === 'CODE') { el = document.createElement('code'); }
                    else if (tag === 'BR') { dst.appendChild(document.createElement('br')); return; }
                    else if (tag === 'A') { var h = n.getAttribute('href') || ''; if (/^#topic-[a-z0-9-]+$/.test(h)) { el = document.createElement('a'); el.setAttribute('href', h); } }
                    if (el) { dst.appendChild(el); walk(n, el); } else { walk(n, dst); }
                });
            }
            walk(box, out);
            /* paragraf di dalam li dipipihkan supaya daftar tetap rapat */
            Array.prototype.forEach.call(out.querySelectorAll('li > p'), function (p) { while (p.firstChild) { p.parentNode.insertBefore(p.firstChild, p); } p.remove(); });
            return out.innerHTML;
        }
        visual.addEventListener('paste', function (e) {
            var cd = e.clipboardData; if (!cd) { return; }
            e.preventDefault();
            var html = cd.getData('text/html'), text = cd.getData('text/plain');
            if (html) { document.execCommand('insertHTML', false, sanitize(html)); }
            else if (text) {
                var esc = function (t) { return t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); };
                document.execCommand('insertHTML', false, text.split(/\r?\n{2,}/).map(function (p) { return '<p>' + esc(p).replace(/\r?\n/g, '<br>') + '</p>'; }).join(''));
            }
            markDirty();
        });
        ['dragover', 'drop'].forEach(function (ev) { visual.addEventListener(ev, function (e) { if (e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types || [], 'Files') !== -1) { e.preventDefault(); say('Pictures cannot be dropped here.'); } }); });

        /* ── papan ketik ── */
        visual.addEventListener('keydown', function (e) {
            var mod = e.ctrlKey || e.metaKey, k = e.key.toLowerCase();
            if (mod && k === 'z' && !e.shiftKey) { e.preventDefault(); undo(); return; }
            if (mod && (k === 'y' || (k === 'z' && e.shiftKey))) { e.preventDefault(); redo(); return; }
            if (mod && k === 's') { e.preventDefault(); form.requestSubmit ? form.requestSubmit() : form.submit(); return; }
            var cell = closest(anchorEl(), 'td,th');
            if (e.key === 'Tab' && cell) {
                e.preventDefault();
                var cells = Array.prototype.slice.call(cell.closest('table').querySelectorAll('th,td')), i = cells.indexOf(cell);
                var target = cells[i + (e.shiftKey ? -1 : 1)];
                if (!target && !e.shiftKey) { cellOps('row-add'); cells = Array.prototype.slice.call(cell.closest('table').querySelectorAll('th,td')); target = cells[i + 1]; }
                if (target) { var rg = document.createRange(); rg.selectNodeContents(target); sel().removeAllRanges(); sel().addRange(rg); }
                return;
            }
            if (e.key === 'Enter' && cell) { e.preventDefault(); return; }   /* sel tabel hanya satu baris */
            if (e.key === 'Tab' && inList()) { e.preventDefault(); indent(e.shiftKey); return; }
            if (e.key === 'Enter' && !e.shiftKey) {
                var box = closest(anchorEl(), '.callout'), p = closest(anchorEl(), 'p');
                if (box && p && !p.textContent.trim() && box.lastElementChild === p && p.parentNode === box) {
                    e.preventDefault(); p.remove();
                    var after = box.nextElementSibling && box.nextElementSibling.tagName === 'P' ? box.nextElementSibling : null;
                    if (!after) { after = emptyP(); box.parentNode.insertBefore(after, box.nextSibling); }
                    placeCaret(after, false); markDirty();
                }
            }
        });
        visual.addEventListener('input', function () { markDirty(); });
        visual.addEventListener('keyup', function () { saveRange(); refreshState(); });
        visual.addEventListener('mouseup', function () { saveRange(); refreshState(); });
        visual.addEventListener('blur', saveRange);
        function saveRange() { var s = sel(); if (s.rangeCount && inVisual(s.anchorNode)) { savedRange = s.getRangeAt(0).cloneRange(); } }

        /* ── keadaan tombol ── */
        var blockSel = toolbar.querySelector('[data-cmd="block"]');
        function refreshState() {
            if (mode !== 'visual') { return; }
            var s = sel(); if (!s.rangeCount || !inVisual(s.anchorNode)) { return; }
            var el = anchorEl(), set = function (cmd, on) { var b = toolbar.querySelector('[data-cmd="' + cmd + '"]'); if (b) { b.classList.toggle('is-on', !!on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); } };
            var st = function (c) { try { return document.queryCommandState(c); } catch (e) { return false; } };
            set('bold', st('bold')); set('italic', st('italic')); set('strike', st('strikeThrough'));
            set('code', closest(el, 'code')); set('ul', closest(el, 'ul')); set('ol', closest(el, 'ol'));
            if (blockSel) { var h = closest(el, 'h2,h3'); blockSel.value = h ? h.tagName.toLowerCase() : 'p'; }
            if (tablebar) { tablebar.hidden = !closest(el, 'td,th'); }
        }
        document.addEventListener('selectionchange', function () { if (mode === 'visual' && inVisual(sel().anchorNode)) { saveRange(); refreshState(); } });

        /* ── toolbar ── */
        toolbar.addEventListener('mousedown', function (e) { if (!/^(SELECT|INPUT|OPTION)$/.test(e.target.tagName)) { e.preventDefault(); } });
        function closePops() { Array.prototype.forEach.call(document.querySelectorAll('.uge-pop'), function (p) { p.hidden = true; }); }
        function openPop(id, btn) {
            var pop = document.getElementById(id), was = !pop.hidden;
            closePops();
            if (was) { return; }
            pop.hidden = false;
            pop.style.left = Math.max(0, Math.min(btn.offsetLeft, toolbar.clientWidth - pop.offsetWidth - 4)) + 'px';
            var q = pop.querySelector('input[type=search]'); if (q) { q.value = ''; q.dispatchEvent(new Event('input')); q.focus(); }
        }
        document.addEventListener('mousedown', function (e) { if (!e.target.closest('.uge-pop') && !e.target.closest('[data-pop]')) { closePops(); } });

        toolbar.addEventListener('click', function (e) {
            var b = e.target.closest('[data-cmd],[data-pop],[data-callout],[data-table-new]'); if (!b || b.tagName === 'SELECT') { return; }
            if (b.dataset.pop) { saveRange(); openPop(b.dataset.pop, b); return; }
            if (b.dataset.callout) { closePops(); if (b.dataset.callout === 'remove') { removeCallout(); } else { setCallout(b.dataset.callout); } return; }
            if (b.hasAttribute('data-table-new')) {
                var pop = document.getElementById('uge-pop-table');
                closePops(); insertTable(pop.querySelector('[name=rows]').value, pop.querySelector('[name=cols]').value); return;
            }
            switch (b.dataset.cmd) {
                case 'bold': exec('bold'); break;
                case 'italic': exec('italic'); break;
                case 'strike': exec('strikeThrough'); break;
                case 'code': toggleCode(); break;
                case 'ul': exec('insertUnorderedList'); break;
                case 'ol': exec('insertOrderedList'); break;
                case 'indent': indent(false); break;
                case 'outdent': indent(true); break;
                case 'clear': exec('removeFormat'); break;
                case 'unlink': unlink(); break;
                case 'undo': undo(); break;
                case 'redo': redo(); break;
            }
        });
        if (blockSel) { blockSel.addEventListener('change', function () { setBlock(blockSel.value); }); }
        if (tablebar) { tablebar.addEventListener('mousedown', function (e) { e.preventDefault(); }); tablebar.addEventListener('click', function (e) { var b = e.target.closest('[data-cell]'); if (b) { cellOps(b.dataset.cell); } }); }

        /* daftar topik untuk tautan */
        var topicList = document.getElementById('uge-pop-topics');
        if (topicList) {
            var q = topicList.querySelector('input[type=search]'), ul = topicList.querySelector('ul');
            topics.forEach(function (t) {
                var li = document.createElement('li'), btn = document.createElement('button');
                btn.type = 'button'; btn.dataset.key = t.key; btn.dataset.text = (t.title + ' ' + t.key).toLowerCase();
                btn.innerHTML = '<strong></strong><small></small>'; btn.firstChild.textContent = t.title; btn.lastChild.textContent = t.chapter + ' · ' + t.key;
                li.appendChild(btn); ul.appendChild(li);
            });
            q.addEventListener('input', function () {
                var term = q.value.trim().toLowerCase(), shown = 0;
                Array.prototype.forEach.call(ul.querySelectorAll('button'), function (b) { var hit = !term || b.dataset.text.indexOf(term) !== -1; b.parentNode.hidden = !hit; if (hit) { shown++; } });
                topicList.querySelector('.uge-none').hidden = shown > 0;
            });
            ul.addEventListener('mousedown', function (e) { e.preventDefault(); });
            ul.addEventListener('click', function (e) { var b = e.target.closest('button'); if (b) { closePops(); insertTopicLink(b.dataset.key); } });
        }

        /* ── tab, formulir, peringatan keluar ── */
        Array.prototype.forEach.call(cfg.tabs, function (b) { b.addEventListener('click', function () { setMode(b.dataset.pane); }); });
        area.addEventListener('input', function () { mdChanged = true; anyChange = true; });
        Array.prototype.forEach.call(form.querySelectorAll('input,select'), function (f) { f.addEventListener('change', function () { anyChange = true; }); });
        form.addEventListener('submit', function (e) {
            if (mode === 'visual' && dirty) { area.value = visualToMarkdown(); dirty = false; }
            submitting = true;
        });
        global.addEventListener('beforeunload', function (e) { if (anyChange && !submitting) { e.preventDefault(); e.returnValue = ''; } });

        normalize(); snap(); refreshState();
        return { toMarkdown: function () { return visualToMarkdown(); } };
    }

    global.GuideEditor = { init: init, toMarkdown: toMarkdown };
})(window);
