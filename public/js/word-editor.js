/*
 * Word-like editor for the contract templates (HR & General → Contract → Templates).
 * Markup: resources/views/hr-general/contracts/components/word-editor.blade.php · styles: public/css/word-editor.css.
 * No library: a contenteditable page + ribbon + ruler. Everything it writes is limited to what the server sanitizer
 * (App\Support\Contracts\ContractHtmlSanitizer) keeps, so what is typed here is what is stored and printed.
 *
 * It owns undo/redo (a snapshot history), because the browser's own undo forgets anything set from code (line spacing,
 * indents, table edits). Selection is kept as node paths so Ctrl+Z puts the cursor back too.
 */
(function () {
    'use strict';

    const PX = 96 / 72;                                  // 1 pt in CSS px
    const CM = 72 / 2.54;                                // 1 cm in pt
    const GAP = 24;                                      // css px of desk between two sheets
    const MARGIN_MIN = 0.5, MARGIN_MAX = 8, TEXT_MIN = 5;   // cm: margin limits and the least text width / height (same as App\Support\Contracts\PageSettings)
    const SIDES = ['top', 'right', 'bottom', 'left'];
    const PRESETS = {                                    // margin presets in cm (default = empty = follows the letterhead)
        default: { top: null, right: null, bottom: null, left: null },
        normal: { top: 2.54, right: 2.54, bottom: 2.54, left: 2.54 },
        narrow: { top: 1.27, right: 1.27, bottom: 1.27, left: 1.27 },
        moderate: { top: 2.54, right: 1.91, bottom: 2.54, left: 1.91 },
        wide: { top: 2.54, right: 5.08, bottom: 2.54, left: 5.08 },
    };
    const BLOCK_SEL = 'p,h1,h2,h3,li,div,blockquote,td,th';
    const FONTS = ['Times New Roman', 'Arial', 'Courier New', 'DejaVu Sans'];   // the faces the PDF engine really has
    const SIZES = [8, 9, 10, 10.5, 11, 12, 14, 16, 18, 20, 24, 28, 36];
    const PB = '{{halaman_baru}}';
    const COLORS = [
        '#000000', '#434343', '#666666', '#999999', '#b7b7b7', '#cccccc', '#d9d9d9', '#efefef', '#f3f3f3', '#ffffff',
        '#980000', '#ff0000', '#ff9900', '#ffff00', '#00ff00', '#00ffff', '#4a86e8', '#0000ff', '#9900ff', '#ff00ff',
        '#e6b8af', '#f4cccc', '#fce5cd', '#fff2cc', '#d9ead3', '#d0e0e3', '#c9daf8', '#cfe2f3', '#d9d2e9', '#ead1dc',
        '#dd7e6b', '#ea9999', '#f9cb9c', '#ffe599', '#b6d7a8', '#a2c4c9', '#a4c2f4', '#9fc5e8', '#b4a7d6', '#d5a6bd',
        '#a61c00', '#cc0000', '#e69138', '#f1c232', '#6aa84f', '#45818e', '#3c78d8', '#3d85c6', '#674ea7', '#a64d79',
    ];

    // What survives a paste from Word / Google Docs (the rest is dropped before it reaches the page).
    const PASTE_TAGS = new Set(['P', 'BR', 'DIV', 'SPAN', 'B', 'STRONG', 'I', 'EM', 'U', 'S', 'STRIKE', 'SUB', 'SUP', 'UL', 'OL', 'LI', 'BLOCKQUOTE',
        'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'TABLE', 'THEAD', 'TBODY', 'TR', 'TD', 'TH']);
    const PASTE_SKIP = new Set(['SCRIPT', 'STYLE', 'META', 'LINK', 'TITLE', 'HEAD', 'XML', 'IMG', 'SVG', 'O:P', 'OBJECT', 'IFRAME']);
    const PASTE_STYLE = new Set(['text-align', 'font-weight', 'font-style', 'text-decoration', 'font-size', 'font-family', 'color', 'background-color',
        'margin-left', 'margin-right', 'margin-top', 'margin-bottom', 'text-indent', 'line-height', 'list-style-type', 'vertical-align', 'width', 'border']);

    const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const escRe = s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const num = v => { const n = parseFloat(v); return isNaN(n) ? 0 : n; };
    const round1 = n => Math.round(n * 10) / 10;

    /** @param {HTMLElement} root */
    function init(root) {
        if (root._we) return root._we;

        const $ = s => root.querySelector(s);
        const $$ = s => Array.from(root.querySelectorAll(s));
        const ed = $('.we-body');
        const page = $('.we-page');
        const stage = $('.we-stage');
        const zoomBox = $('.we-zoom');
        const form = root.dataset.form ? document.getElementById(root.dataset.form) : root.closest('form');
        const hidden = root.dataset.input ? document.getElementById(root.dataset.input) : null;
        const status = $('.we-status-info');
        let saved = null;                                // last selection inside the editor (toolbar clicks may move focus)
        let zoom = 1;
        let marks = [], cur = -1;                        // find results
        const hist = { stack: [], idx: -1, timer: null };
        let composing = false;                           // IME composition in progress: leave the text alone
        const pg = { pages: 1, key: '' };                // result of the last pagination
        const ro = n => root.querySelector('[name="' + n + '"]');

        document.execCommand('styleWithCSS', false, true);
        document.execCommand('defaultParagraphSeparator', false, 'p');

        // ── helpers ──────────────────────────────────────────────────────
        const inEd = n => !!n && ed.contains(n.nodeType === 1 ? n : n.parentNode);
        const selection = () => window.getSelection();
        function saveSel() {
            const s = selection();
            if (s.rangeCount && inEd(s.anchorNode) && inEd(s.focusNode)) saved = s.getRangeAt(0).cloneRange();
        }
        function restoreSel() {
            const s = selection();
            if (s.rangeCount && inEd(s.anchorNode)) return;          // already inside
            ed.focus({ preventScroll: true });
            if (saved) { s.removeAllRanges(); s.addRange(saved); }
        }
        const range = () => { const s = selection(); return s.rangeCount ? s.getRangeAt(0) : null; };
        const closest = (node, sel) => { const el = node && (node.nodeType === 1 ? node : node.parentElement); const f = el && el.closest(sel); return f && ed.contains(f) ? f : null; };

        /** The innermost block elements touched by the selection ("leaf" blocks: no block inside). */
        function selectedBlocks() {
            const r = range(); if (!r) return [];
            const leaf = el => !el.querySelector(BLOCK_SEL);
            if (r.collapsed) { const b = closest(r.startContainer, BLOCK_SEL); return b && leaf(b) ? [b] : (b ? [b] : []); }
            return Array.from(ed.querySelectorAll(BLOCK_SEL)).filter(el => leaf(el) && r.intersectsNode(el) && !el.classList.contains('we-pgap'));
        }

        // Plain text typed straight into the page (no paragraph yet) becomes a paragraph.
        function ensureParagraph() {
            const r = range();
            if (r && (r.startContainer === ed || (r.startContainer.nodeType === 3 && r.startContainer.parentNode === ed))) {
                document.execCommand('formatBlock', false, 'p');
            }
        }

        // ── history ──────────────────────────────────────────────────────
        function pathOf(node) { const p = []; while (node && node !== ed) { p.unshift(Array.prototype.indexOf.call(node.parentNode.childNodes, node)); node = node.parentNode; } return p; }
        function nodeAt(p) { let n = ed; for (const i of p) { n = n && n.childNodes[i]; } return n; }
        function serSel() {
            const r = range(); if (!r || !inEd(r.startContainer)) return null;
            return { s: pathOf(r.startContainer), so: r.startOffset, e: pathOf(r.endContainer), eo: r.endOffset };
        }
        function applySel(o) {
            if (!o) return;
            try {
                const s = nodeAt(o.s), e = nodeAt(o.e); if (!s || !e) return;
                const r = document.createRange();
                r.setStart(s, Math.min(o.so, s.nodeType === 3 ? s.length : s.childNodes.length));
                r.setEnd(e, Math.min(o.eo, e.nodeType === 3 ? e.length : e.childNodes.length));
                const sel = selection(); sel.removeAllRanges(); sel.addRange(r);
            } catch (x) { /* a stale path: leave the cursor where it is */ }
        }
        function clearMarks() {
            if (!marks.length && !ed.querySelector('mark.we-find')) return;
            ed.querySelectorAll('mark.we-find').forEach(m => { const p = m.parentNode; while (m.firstChild) p.insertBefore(m.firstChild, m); p.removeChild(m); p.normalize(); });
            marks = []; cur = -1;
        }
        function getHTML() {
            const c = ed.cloneNode(true);
            c.querySelectorAll('mark.we-find').forEach(m => { const p = m.parentNode; while (m.firstChild) p.insertBefore(m.firstChild, m); p.removeChild(m); });
            c.querySelectorAll('.we-pgap').forEach(g => g.remove());
            c.querySelectorAll('.we-pb').forEach(p => { p.classList.remove('we-pb'); if (!p.getAttribute('class')) p.removeAttribute('class'); });
            c.querySelectorAll('[style=""]').forEach(x => x.removeAttribute('style'));
            return c.innerHTML;
        }
        function commit() {
            clearTimeout(hist.timer); hist.timer = null;
            clearGaps();                                  // the selection paths below must not count the page-gap spacers
            const html = getHTML(), top = hist.stack[hist.idx];
            if (top && top.html === html) { top.sel = serSel() || top.sel; paginate(); return; }
            hist.stack.length = hist.idx + 1;
            hist.stack.push({ html, sel: serSel() });
            if (hist.stack.length > 200) hist.stack.shift();
            hist.idx = hist.stack.length - 1;
            syncUndo();
            paginate();
        }
        const flush = () => { if (hist.timer) commit(); };
        function restore(state) {
            clearMarks();
            ed.innerHTML = state.html;
            decorate();
            ed.focus({ preventScroll: true });
            applySel(state.sel);
            paginate();
            saveSel(); sync(); updateInfo();
        }
        function undo() { flush(); if (hist.idx > 0) { hist.idx--; restore(hist.stack[hist.idx]); } syncUndo(); }
        function redo() { flush(); if (hist.idx < hist.stack.length - 1) { hist.idx++; restore(hist.stack[hist.idx]); } syncUndo(); }
        function syncUndo() {
            const u = $('[data-cmd="undo"]'), r = $('[data-cmd="redo"]');
            if (u) u.disabled = hist.idx <= 0;
            if (r) r.disabled = hist.idx >= hist.stack.length - 1;
        }

        /** Run one editing action as a single undo step. */
        function run(fn) {
            flush(); restoreSel(); clearMarks(); clearGaps();
            ensureParagraph();
            fn();
            normalizeBlocks(); decorate(); commit(); saveSel(); sync(); updateInfo(); updateRuler();
        }
        const exec = (c, v) => document.execCommand(c, false, v === undefined ? null : v);

        // Paragraphs that hold only {{halaman_baru}} are drawn as a page break.
        function decorate() { ed.querySelectorAll('p').forEach(p => p.classList.toggle('we-pb', p.textContent.trim() === PB)); }

        // ── block / inline commands ──────────────────────────────────────
        function setBlockStyle(prop, value) { selectedBlocks().forEach(b => { if (value === null || value === '') b.style.removeProperty(prop); else b.style[prop] = value; }); }

        let applying = false;
        function applySize(pt) {
            const rg = range(); root._pendingSize = rg && rg.collapsed ? pt : null;      // a caret only: the next typed text gets this size
            applying = true;
            try { exec('fontSize', '7'); } finally { applying = false; }
            const fresh = [];
            ed.querySelectorAll('font[size="7"]').forEach(f => { const s = document.createElement('span'); while (f.firstChild) s.appendChild(f.firstChild); f.replaceWith(s); fresh.push(s); });
            ed.querySelectorAll('span').forEach(sp => { if (/xxx-large/.test(sp.style.fontSize)) fresh.push(sp); });
            fresh.forEach(sp => {
                sp.style.fontSize = pt + 'pt';
                // sizes set earlier on text inside the new span no longer apply
                sp.querySelectorAll('span[style*="font-size"]').forEach(x => x.style.removeProperty('font-size'));
            });
            // spans left without any style are just noise
            ed.querySelectorAll('span:not([style])').forEach(x => { const p = x.parentNode; while (x.firstChild) p.insertBefore(x.firstChild, x); p.removeChild(x); });
        }
        function currentSizePt() {
            const r = range(); if (!r) return 11;
            const el = r.startContainer.nodeType === 1 ? r.startContainer : r.startContainer.parentElement;
            return el ? round1(num(getComputedStyle(el).fontSize) / PX * 2) / 2 : 11;
        }
        function bumpSize(dir) { const cur = currentSizePt(); const list = SIZES; let n; if (dir > 0) n = list.find(s => s > cur + .01) || cur + 2; else n = [...list].reverse().find(s => s < cur - .01) || Math.max(6, cur - 1); run(() => applySize(n)); }

        function retag(list, tag) {
            if (list.tagName === tag) return list;
            const n = document.createElement(tag); n.setAttribute('style', list.getAttribute('style') || '');
            while (list.firstChild) n.appendChild(list.firstChild);
            list.replaceWith(n); return n;
        }
        function setListStyle(type) {
            const lists = new Set(); selectedBlocks().forEach(b => { const l = closest(b, 'ol,ul'); if (l) lists.add(l); });
            if (!lists.size) { exec(type === 'decimal' || /alpha|roman/.test(type) ? 'insertOrderedList' : 'insertUnorderedList'); selectedBlocks().forEach(b => { const l = closest(b, 'ol,ul'); if (l) lists.add(l); }); }
            lists.forEach(l => { const isBullet = ['disc', 'circle', 'square'].includes(type); const t = retag(l, isBullet ? 'UL' : 'OL'); t.style.listStyleType = type; });
        }

        function indentBlocks(dir) {
            const blocks = selectedBlocks();
            if (blocks.length && blocks.every(b => closest(b, 'li'))) { exec(dir > 0 ? 'indent' : 'outdent'); return; }
            blocks.forEach(b => { const v = Math.max(0, num(getComputedStyle(b).marginLeft) / PX + dir * 36); b.style.marginLeft = v ? round1(v) + 'pt' : ''; });
        }

        function clearFormatting() {
            exec('removeFormat');
            selectedBlocks().forEach(b => b.removeAttribute('style'));
        }

        function insertPageBreak() {
            const r = range(); let top = r && r.startContainer; while (top && top.parentNode !== ed) top = top.parentNode;
            const html = '<p>' + PB + '</p><p><br></p>';
            if (top && top.nodeType === 1) { top.insertAdjacentHTML('afterend', html); const next = top.nextElementSibling.nextElementSibling; const rr = document.createRange(); rr.setStart(next, 0); rr.collapse(true); const s = selection(); s.removeAllRanges(); s.addRange(rr); }
            else { ed.insertAdjacentHTML('beforeend', html); }
        }

        // ── tables ───────────────────────────────────────────────────────
        const BORDER = '0.75pt solid #000000';
        function insertTable(rows, cols) {
            let h = '<table style="width:100%;border-collapse:collapse"><tbody>';
            for (let r = 0; r < rows; r++) { h += '<tr>'; for (let c = 0; c < cols; c++) h += '<td style="border:' + BORDER + '"><br></td>'; h += '</tr>'; }
            exec('insertHTML', h + '</tbody></table><p><br></p>');
        }
        const cellOf = () => { const r = range(); return r ? closest(r.startContainer, 'td,th') : null; };
        function cellIndex(td) { return Array.prototype.indexOf.call(td.parentNode.children, td); }
        function blankCell(like) { const c = document.createElement(like ? like.tagName.toLowerCase() : 'td'); if (like && like.getAttribute('style')) c.setAttribute('style', like.getAttribute('style')); c.innerHTML = '<br>'; return c; }
        function tableOp(op) {
            const td = cellOf(); if (!td) { window.showNotification && window.showNotification('Put the cursor inside a table cell first.', 'info'); return; }
            const tr = td.parentNode, tbl = td.closest('table'), ci = cellIndex(td);
            const caretBack = () => { if (td.isConnected) { const rr = document.createRange(); rr.selectNodeContents(td); rr.collapse(true); const s = selection(); s.removeAllRanges(); s.addRange(rr); } };
            if (op === 'row-above' || op === 'row-below') { const n = document.createElement('tr'); Array.from(tr.children).forEach(c => n.appendChild(blankCell(c))); tr.parentNode.insertBefore(n, op === 'row-above' ? tr : tr.nextSibling); }
            else if (op === 'col-left' || op === 'col-right') { Array.from(tbl.rows).forEach(row => { const ref = row.children[ci]; const n = blankCell(ref); row.insertBefore(n, ref ? (op === 'col-left' ? ref : ref.nextSibling) : null); }); }
            else if (op === 'del-row') { if (tbl.rows.length <= 1) tbl.remove(); else tr.remove(); }
            else if (op === 'del-col') { if (tr.children.length <= 1) tbl.remove(); else Array.from(tbl.rows).forEach(row => row.children[ci] && row.children[ci].remove()); }
            else if (op === 'del-table') tbl.remove();
            else if (op === 'borders') { const cells = tbl.querySelectorAll('td,th'); const has = Array.from(cells).some(c => c.style.border); cells.forEach(c => { if (has) c.style.removeProperty('border'); else c.style.border = BORDER; }); }
            else if (op === 'merge-right') { const nx = td.nextElementSibling; if (nx) { td.colSpan = (td.colSpan || 1) + (nx.colSpan || 1); if (nx.textContent.trim()) td.innerHTML += ' ' + nx.innerHTML; nx.remove(); } }
            caretBack();
        }

        // ── clipboard ────────────────────────────────────────────────────
        function cleanPaste(html) {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const out = document.createElement('div');
            (function walk(src, dst) {
                src.childNodes.forEach(n => {
                    if (n.nodeType === 3) { dst.appendChild(document.createTextNode(n.data)); return; }
                    if (n.nodeType !== 1) return;
                    const tag = n.tagName.toUpperCase();
                    if (PASTE_SKIP.has(tag)) return;
                    if (!PASTE_TAGS.has(tag)) { walk(n, dst); return; }
                    if ((tag === 'B' || tag === 'STRONG') && /normal|400/.test(n.style.fontWeight || '')) { walk(n, dst); return; }      // Google Docs wrapper
                    const t = /^H[4-6]$/.test(tag) ? 'H3' : tag;
                    const el = document.createElement(t);
                    for (let i = 0; i < n.style.length; i++) {
                        const p = n.style[i]; let v = n.style.getPropertyValue(p);
                        if (!PASTE_STYLE.has(p) || !v) continue;
                        if (p === 'color' && /windowtext|inherit|initial/.test(v)) continue;
                        if (p === 'background-color' && /^(white|#fff(fff)?|rgb\(255,\s*255,\s*255\)|transparent|inherit)$/i.test(v)) continue;
                        if (p === 'font-family') { const f = FONTS.find(x => v.toLowerCase().includes(x.toLowerCase())); if (!f) continue; v = f; }
                        if (p === 'font-size' && /px$/.test(v)) v = (Math.round(num(v) / PX * 2) / 2) + 'pt';
                        el.style.setProperty(p, v);
                    }
                    if ((tag === 'TD' || tag === 'TH')) { if (n.colSpan > 1) el.colSpan = n.colSpan; if (n.rowSpan > 1) el.rowSpan = n.rowSpan; }
                    if (tag === 'B' || tag === 'STRONG' || tag === 'I' || tag === 'EM') { /* plain */ }
                    dst.appendChild(el); walk(n, el);
                });
            })(doc.body, out);
            return out.innerHTML;
        }
        function textToHtml(t) {
            const lines = t.replace(/\r\n?/g, '\n').split('\n');
            return lines.length === 1 ? esc(lines[0]) : lines.map(l => '<p>' + (esc(l) || '<br>') + '</p>').join('');
        }
        ed.addEventListener('paste', e => {
            const cd = e.clipboardData; if (!cd) return;
            const html = cd.getData('text/html'), text = cd.getData('text/plain');
            if (!html && !text) return;
            e.preventDefault(); flush(); clearMarks(); clearGaps(); ensureParagraph();
            let out = html ? cleanPaste(html) : '';
            if (!out.replace(/<[^>]*>/g, '').trim()) out = textToHtml(text);
            insertClean(out);
            normalizeBlocks(); decorate(); commit(); saveSel(); sync(); updateInfo();
        });

        /**
         * Insert already-cleaned HTML at the cursor ourselves: the browser's insertHTML copies the source paragraph's style onto
         * spans and fuses paragraphs oddly. One pasted paragraph merges into the current one (as in Word); several blocks split the
         * current paragraph around them.
         */
        const BLOCKY = /^(P|H[1-6]|DIV|UL|OL|TABLE|BLOCKQUOTE)$/;
        function insertClean(h) {
            const tpl = document.createElement('template'); tpl.innerHTML = h; let nodes = Array.from(tpl.content.childNodes);
            const r = range(); if (!r) return;
            if (!r.collapsed) r.deleteContents();
            const placeAfter = n => { const rr = document.createRange(); rr.setStartAfter(n); rr.collapse(true); const s = selection(); s.removeAllRanges(); s.addRange(rr); };
            const blocky = nodes.some(n => n.nodeType === 1 && BLOCKY.test(n.tagName));
            if (!blocky) { const frag = document.createDocumentFragment(); nodes.forEach(n => frag.appendChild(n)); const last = frag.lastChild; r.insertNode(frag); if (last) placeAfter(last); return; }
            if (nodes.length === 1 && /^(P|DIV|H[1-6])$/.test(nodes[0].tagName)) {
                const frag = document.createDocumentFragment(); Array.from(nodes[0].childNodes).forEach(n => frag.appendChild(n)); const last = frag.lastChild; r.insertNode(frag); if (last) placeAfter(last); return;
            }
            // loose inline runs between blocks become paragraphs
            const wrapped = []; let run = null;
            nodes.forEach(n => { if (n.nodeType === 1 && BLOCKY.test(n.tagName)) { run = null; wrapped.push(n); } else if (n.nodeType === 3 && !n.data.trim()) { /* blank between blocks */ } else { if (!run) { run = document.createElement('p'); wrapped.push(run); } run.appendChild(n); } });
            nodes = wrapped;
            const block = closest(r.startContainer, 'p,h1,h2,h3,blockquote,li,td,th,div');
            if (!block || /^(LI|TD|TH)$/.test(block.tagName) || block.querySelector(BLOCK_SEL)) {          // lists / cells: let the browser do it
                exec('insertHTML', h); return;
            }
            const tail = document.createRange(); tail.setStart(r.startContainer, r.startOffset); tail.setEnd(block, block.childNodes.length);
            const rest = block.cloneNode(false); rest.appendChild(tail.extractContents());
            const empty = el => !el.textContent.trim() && !el.querySelector('br,img,table');
            if (empty(block)) block.innerHTML = '<br>';
            let anchor = block;
            nodes.forEach(n => { anchor.after(n); anchor = n; });
            if (!empty(rest)) { anchor.after(rest); anchor = rest; }
            if (empty(block) && block.parentNode) block.remove();
            const target = nodes[nodes.length - 1]; const rr = document.createRange(); rr.selectNodeContents(target); rr.collapse(false); const s = selection(); s.removeAllRanges(); s.addRange(rr);
        }

        /** Browsers sometimes put a list / table / heading inside a paragraph; the saved HTML must not. */
        function normalizeBlocks() {
            let moved = false;
            const sl = selection(), keepSel = sl.rangeCount && inEd(sl.anchorNode) ? [sl.anchorNode, sl.anchorOffset, sl.focusNode, sl.focusOffset] : null;
            ed.querySelectorAll('p').forEach(p => {
                if (!p.querySelector(':scope > ul, :scope > ol, :scope > table, :scope > h1, :scope > h2, :scope > h3, :scope > p, :scope > div, :scope > blockquote')) return;
                const parts = []; let buf = null;
                Array.from(p.childNodes).forEach(c => {
                    if (c.nodeType === 1 && BLOCKY.test(c.tagName)) { buf = null; parts.push(c); }
                    else { if (!buf) { buf = p.cloneNode(false); parts.push(buf); } buf.appendChild(c); }
                });
                moved = true;
                const keep = parts.filter(x => !(x.tagName === 'P' && !x.textContent.trim() && !x.querySelector('br,img')));
                p.replaceWith(...keep);
            });
            // only when something moved: re-setting the selection would drop a pending typing style (bold / size chosen at a bare caret)
            if (moved && keepSel && keepSel[0].isConnected && keepSel[2].isConnected) { try { sl.setBaseAndExtent(...keepSel); } catch (x) { /* node moved away */ } }
        }
        ed.addEventListener('compositionstart', () => { composing = true; });
        ed.addEventListener('compositionend', () => { composing = false; schedulePaginate(); });
        ed.addEventListener('drop', e => { if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) e.preventDefault(); });
        ed.addEventListener('beforeinput', e => {
            if (e.inputType === 'historyUndo') { e.preventDefault(); undo(); return; }
            if (e.inputType === 'historyRedo') { e.preventDefault(); redo(); return; }
            if (marks.length) clearMarks();
        });

        // ── typing → history ─────────────────────────────────────────────
        ed.addEventListener('input', () => {
            clearTimeout(hist.timer); hist.timer = setTimeout(() => { if (composing) return; decorate(); commit(); }, 450);
            schedulePaginate();
            // size chosen with the cursor collapsed: Chrome leaves a size-7 marker on the next typed text
            if (!applying && root._pendingSize && ed.querySelector('font[size="7"],span[style*="xxx-large"]')) { const p = root._pendingSize; applySize(p); root._pendingSize = p; }
            updateInfo();
        });

        // ── keyboard ─────────────────────────────────────────────────────
        const KEYS = {
            b: () => exec('bold'), i: () => exec('italic'), u: () => exec('underline'),
            l: () => exec('justifyLeft'), e: () => exec('justifyCenter'), r: () => exec('justifyRight'), j: () => exec('justifyFull'),
        };
        root.addEventListener('keydown', e => {
            const mod = e.ctrlKey || e.metaKey, k = (e.key || '').toLowerCase();
            const inside = ed.contains(e.target) || e.target === ed;
            if (mod && !e.altKey && (k === 'f' || k === 'h')) { e.preventDefault(); openFind(k === 'h'); return; }
            if (mod && !e.altKey && k === 's') { e.preventDefault(); if (form) form.requestSubmit(); return; }
            if (mod && !e.altKey && k === 'p') { e.preventDefault(); preview(); return; }
            if (e.key === 'F1') { e.preventDefault(); openModal('keys'); return; }
            if (e.key === 'Escape') {
                if ($('.we-find').classList.contains('open')) { closeFind(); ed.focus(); return; }
                if ($$('.we-modal.open').length) { closeModals(); return; }
                if ($$('.we-pop.open').length) { closePops(); return; }
                if (root.classList.contains('we-full')) { toggleFull(false); return; }
            }
            if (!inside) return;

            if (mod && !e.altKey && !e.shiftKey && KEYS[k]) { e.preventDefault(); run(KEYS[k]); return; }
            if (mod && !e.altKey && k === 'z' && !e.shiftKey) { e.preventDefault(); undo(); return; }
            if (mod && !e.altKey && (k === 'y' || (k === 'z' && e.shiftKey))) { e.preventDefault(); redo(); return; }
            if (mod && e.shiftKey && !e.altKey && k === 'l') { e.preventDefault(); run(() => exec('insertUnorderedList')); return; }
            if (mod && !e.shiftKey && !e.altKey && k === 'm') { e.preventDefault(); run(() => indentBlocks(1)); return; }
            if (mod && e.shiftKey && !e.altKey && k === 'm') { e.preventDefault(); run(() => indentBlocks(-1)); return; }
            if (mod && e.altKey && /^Digit[0-3]$/.test(e.code)) {
                e.preventDefault(); const n = e.code.slice(5); run(() => exec('formatBlock', n === '0' ? 'p' : 'h' + n)); return;
            }
            if (mod && e.code === 'BracketRight') { e.preventDefault(); bumpSize(1); return; }
            if (mod && e.code === 'BracketLeft') { e.preventDefault(); bumpSize(-1); return; }
            if (mod && e.shiftKey && e.code === 'Period') { e.preventDefault(); bumpSize(1); return; }
            if (mod && e.shiftKey && e.code === 'Comma') { e.preventDefault(); bumpSize(-1); return; }
            if (mod && e.code === 'Equal') { e.preventDefault(); run(() => exec(e.shiftKey ? 'superscript' : 'subscript')); return; }
            if (mod && e.code === 'Space') { e.preventDefault(); run(clearFormatting); return; }
            if (mod && e.key === 'Enter') { e.preventDefault(); run(insertPageBreak); return; }
            if (e.key === 'Tab' && !mod) {
                const td = cellOf(), li = closest(range() && range().startContainer, 'li');
                e.preventDefault();
                if (td) {
                    const cells = Array.from(td.closest('table').querySelectorAll('td,th')), i = cells.indexOf(td), n = cells[i + (e.shiftKey ? -1 : 1)];
                    if (n) { const rr = document.createRange(); rr.selectNodeContents(n); rr.collapse(false); const s = selection(); s.removeAllRanges(); s.addRange(rr); }
                    else if (!e.shiftKey) { run(() => tableOp('row-below')); const nn = cellOf() && Array.from(td.closest('table').querySelectorAll('td,th'))[i + 1]; if (nn) { const rr = document.createRange(); rr.selectNodeContents(nn); rr.collapse(true); const s = selection(); s.removeAllRanges(); s.addRange(rr); } }
                } else if (li) run(() => exec(e.shiftKey ? 'outdent' : 'indent'));
                else if (!e.shiftKey) run(() => exec('insertText', '        '));
            }
        });

        // ── toolbar ──────────────────────────────────────────────────────
        // A ribbon click must not take the focus (and so the selection) from the text.
        root.addEventListener('mousedown', e => { if (e.target.closest('.we-btn, .we-pop-item, .we-swatch, .we-field, .we-grid span')) e.preventDefault(); });
        document.addEventListener('selectionchange', () => { if (inEd(selection().anchorNode)) { saveSel(); sync(); updateRuler(); updatePageNo(); } });

        const CMD = {
            undo, redo,
            bold: () => run(() => exec('bold')), italic: () => run(() => exec('italic')), underline: () => run(() => exec('underline')),
            strike: () => run(() => exec('strikeThrough')), sub: () => run(() => exec('subscript')), sup: () => run(() => exec('superscript')),
            left: () => run(() => exec('justifyLeft')), center: () => run(() => exec('justifyCenter')), right: () => run(() => exec('justifyRight')), justify: () => run(() => exec('justifyFull')),
            ul: () => run(() => exec('insertUnorderedList')), ol: () => run(() => exec('insertOrderedList')),
            indent: () => run(() => indentBlocks(1)), outdent: () => run(() => indentBlocks(-1)),
            clear: () => run(clearFormatting), pagebreak: () => run(insertPageBreak),
            'size-up': () => bumpSize(1), 'size-down': () => bumpSize(-1),
            find: () => openFind(false), preview, keys: () => openModal('keys'), full: () => toggleFull(),
            orient: () => { ps.orient = ps.orient === 'landscape' ? 'portrait' : 'landscape'; applyPage(); },
            pagesetup: openPageSetup, fit: fitWidth,
            ruler: () => { const off = root.classList.toggle('we-noruler'); const b = $('[data-cmd="ruler"]'); if (b) b.classList.toggle('on', !off); },
        };
        $$('[data-cmd]').forEach(b => b.addEventListener('click', () => { const f = CMD[b.dataset.cmd]; if (f) f(); closePops(); }));

        function ctl(name) { return $('[data-ctl="' + name + '"]'); }
        const withSel = fn => v => { run(() => fn(v)); };
        ctl('block').addEventListener('change', e => { const v = e.target.value; run(() => { if (v === 'title') { exec('formatBlock', 'h1'); setBlockStyle('textAlign', 'center'); } else exec('formatBlock', v); }); });
        ctl('font').addEventListener('change', e => { const v = e.target.value; if (v) run(() => exec('fontName', v)); });
        const sizeIn = ctl('size');
        const applySizeFromInput = () => { const v = parseFloat(String(sizeIn.value).replace(',', '.')); if (v >= 4 && v <= 96) { run(() => applySize(v)); } };
        sizeIn.addEventListener('change', applySizeFromInput);
        sizeIn.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); applySizeFromInput(); ed.focus(); } });
        ctl('spacing').addEventListener('change', e => { const v = e.target.value; run(() => setBlockStyle('lineHeight', v === 'default' ? '' : v)); });
        ctl('liststyle').addEventListener('change', e => { const v = e.target.value; if (v) run(() => setListStyle(v)); e.target.value = ''; });
        ctl('zoom').addEventListener('input', e => setZoom(e.target.value / 100));
        ctl('papersize').addEventListener('change', e => { ps.size = e.target.value; applyPage(); });
        ctl('marginpreset').addEventListener('change', e => { const p = PRESETS[e.target.value]; if (p) { Object.assign(ps.m, p); applyPage(); } else e.target.value = presetOf(); });

        // popovers
        function closePops() { $$('.we-pop.open').forEach(p => p.classList.remove('open')); }
        $$('[data-pop-toggle]').forEach(b => b.addEventListener('click', e => {
            e.stopPropagation();
            const pop = $('[data-pop="' + b.dataset.popToggle + '"]'), was = pop.classList.contains('open');
            closePops(); if (!was) { saveSel(); pop.classList.add('open'); const f = pop.querySelector('input[type=text]'); if (f) f.focus(); }
        }));
        document.addEventListener('click', e => { if (!e.target.closest('.we-pop-wrap')) closePops(); });

        // colour palettes
        let lastText = '#000000', lastHi = '#ffff00';
        $$('[data-palette]').forEach(pop => {
            const kind = pop.dataset.palette;                               // text | highlight
            const grid = pop.querySelector('.we-swatches');
            COLORS.forEach(c => { const s = document.createElement('button'); s.type = 'button'; s.className = 'we-swatch'; s.style.background = c; s.title = c; s.dataset.color = c; grid.appendChild(s); });
            const apply = c => { run(() => exec(kind === 'text' ? 'foreColor' : 'hiliteColor', c)); if (kind === 'text') { lastText = c; $('[data-bar="text"]').style.background = c; } else { lastHi = c; $('[data-bar="highlight"]').style.background = c; } closePops(); };
            pop.addEventListener('click', e => { const s = e.target.closest('[data-color]'); if (s) apply(s.dataset.color); const n = e.target.closest('[data-none]'); if (n) apply(kind === 'text' ? '#000000' : 'transparent'); });
            const more = pop.querySelector('input[type=color]'); if (more) more.addEventListener('change', () => apply(more.value));
        });
        $('[data-apply="text"]').addEventListener('click', () => run(() => exec('foreColor', lastText)));
        $('[data-apply="highlight"]').addEventListener('click', () => run(() => exec('hiliteColor', lastHi)));

        // table grid picker
        const grid = $('.we-grid');
        for (let i = 0; i < 60; i++) { const s = document.createElement('span'); s.dataset.r = Math.floor(i / 10) + 1; s.dataset.c = (i % 10) + 1; grid.appendChild(s); }
        grid.addEventListener('mouseover', e => { const t = e.target.closest('span'); if (!t) return; grid.querySelectorAll('span').forEach(s => s.classList.toggle('hot', +s.dataset.r <= +t.dataset.r && +s.dataset.c <= +t.dataset.c)); $('.we-grid-label').textContent = t.dataset.c + ' × ' + t.dataset.r; });
        grid.addEventListener('click', e => { const t = e.target.closest('span'); if (t) { run(() => insertTable(+t.dataset.r, +t.dataset.c)); closePops(); } });
        $$('[data-table]').forEach(b => b.addEventListener('click', () => { run(() => tableOp(b.dataset.table)); closePops(); }));

        // fields (placeholders)
        // read each time: a host page (letter template designer) may change the list while editing and ping 'we-fields'
        const readFields = () => JSON.parse(root.dataset.fields || '{}');
        const typeSel = root.dataset.typeSelect ? document.getElementById(root.dataset.typeSelect) : null;
        const fieldList = $('.we-field-list'), fieldFilter = $('.we-fields input');
        function renderFields() {
            const ext = typeSel && typeSel.value === 'EXTERNAL', set = readFields()[ext ? 'external' : 'employee'] || {}, q = (fieldFilter.value || '').toLowerCase();
            fieldList.innerHTML = Object.entries(set).filter(([k, m]) => !q || k.includes(q) || m.toLowerCase().includes(q))
                .map(([k, m]) => '<button type="button" class="we-field" data-token="{{' + k + '}}"><code>{{' + k + '}}</code><small>' + esc(m) + '</small></button>').join('') || '<p class="text-xs text-gray-400 p-2">No field matches.</p>';
        }
        fieldFilter.addEventListener('input', renderFields);
        // Enter inside the form would save the template: here it inserts the first matching field instead
        fieldFilter.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); const f = fieldList.querySelector('[data-token]'); if (f) f.click(); } });
        if (typeSel) typeSel.addEventListener('change', renderFields);
        root.addEventListener('we-fields', renderFields);
        renderFields();
        fieldList.addEventListener('click', e => { const f = e.target.closest('[data-token]'); if (f) { run(() => exec('insertText', f.dataset.token)); closePops(); } });

        // ── page: paper size, orientation, margins ───────────────────────
        // The sheet shows the real paper: its size and margins are CSS variables on the editor root. Empty margin = default
        // (clear of the letterhead when it is on), the same rule as App\Support\Contracts\PageSettings on the server.
        const sizes = JSON.parse(root.dataset.sizes || '{}');            // key => [width pt, height pt, label]
        const defaults = JSON.parse(root.dataset.pageDefaults || '{}');  // plain | letterhead => {top,right,bottom,left} in cm
        const lhToggle = root.dataset.lhToggle ? document.querySelector(root.dataset.lhToggle) : null;
        const lhOn = () => !!root.dataset.letterhead && (!lhToggle || lhToggle.checked);
        const round2 = n => Math.round(n * 100) / 100;
        const valOf = n => { const i = ro(n); return i ? i.value : ''; };
        const ps = { size: (valOf('page_size') || 'A4').toUpperCase(), orient: valOf('page_orientation') === 'landscape' ? 'landscape' : 'portrait', m: {} };
        SIDES.forEach(s => { const v = parseFloat(valOf('margin_' + s)); ps.m[s] = isNaN(v) ? null : v; });
        if (!sizes[ps.size]) ps.size = 'A4';

        function pageGeom() {
            const dim = sizes[ps.size] || [595.28, 841.89], land = ps.orient === 'landscape';
            const w = land ? dim[1] : dim[0], h = land ? dim[0] : dim[1];
            const def = defaults[lhOn() ? 'letterhead' : 'plain'] || { top: 1.98, right: 1.98, bottom: 1.98, left: 1.98 };
            const cm = {};
            SIDES.forEach(s => { const v = ps.m[s]; cm[s] = v === null || v === undefined ? def[s] : round2(Math.min(MARGIN_MAX, Math.max(MARGIN_MIN, v))); });
            const maxX = w / CM - TEXT_MIN, maxY = h / CM - TEXT_MIN;
            if (cm.left + cm.right > maxX) cm.left = cm.right = round2(Math.max(MARGIN_MIN, maxX / 2));
            if (cm.top + cm.bottom > maxY) cm.top = cm.bottom = round2(Math.max(MARGIN_MIN, maxY / 2));
            return { w, h, cm, def, mt: cm.top * CM, mr: cm.right * CM, mb: cm.bottom * CM, ml: cm.left * CM };
        }
        let G = pageGeom();

        function presetOf() {
            for (const [name, p] of Object.entries(PRESETS)) {
                if (SIDES.every(s => (p[s] === null && ps.m[s] === null) || (p[s] !== null && ps.m[s] !== null && Math.abs(p[s] - ps.m[s]) < .006))) return name;
            }
            return 'custom';
        }
        /** Put the current settings on the sheet, the rulers, the form and the ribbon (repaginate = false while a margin is being dragged). */
        function applyPage(repag) {
            G = pageGeom();
            const st = root.style;
            st.setProperty('--we-w', G.w + 'pt'); st.setProperty('--we-h', G.h + 'pt');
            st.setProperty('--we-mt', G.mt + 'pt'); st.setProperty('--we-mr', G.mr + 'pt'); st.setProperty('--we-mb', G.mb + 'pt'); st.setProperty('--we-ml', G.ml + 'pt');
            const set = (n, v) => { const i = ro(n); if (i) i.value = v; };
            set('page_size', ps.size); set('page_orientation', ps.orient);
            SIDES.forEach(s => set('margin_' + s, ps.m[s] === null ? '' : String(ps.m[s])));
            ctl('papersize').value = ps.size; ctl('marginpreset').value = presetOf();
            const ob = $('[data-cmd="orient"]'); if (ob) { ob.classList.toggle('on', ps.orient === 'landscape'); ob.title = ps.orient === 'landscape' ? 'Landscape (click for portrait)' : 'Portrait (click for landscape)'; }
            buildRulers(); updateRuler(); updatePageNo();
            if (repag !== false) repaginate();
        }
        function syncPage() {
            const lh = lhOn();
            if (lh) root.style.setProperty('--we-lh', 'url("' + root.dataset.letterhead + '")'); else root.style.removeProperty('--we-lh');
            sheets.classList.toggle('lh', lh);
            applyPage();
        }
        if (lhToggle) lhToggle.addEventListener('change', syncPage);
        function setZoom(z) { zoom = Math.max(.5, Math.min(2, z)); zoomBox.style.zoom = zoom; ctl('zoom').value = Math.round(zoom * 100); $('.we-zoom-label').textContent = Math.round(zoom * 100) + '%'; updateRuler(); }
        stage.addEventListener('wheel', e => { if (e.ctrlKey) { e.preventDefault(); setZoom(zoom + (e.deltaY < 0 ? .1 : -.1)); } }, { passive: false });
        function fitWidth() { setZoom(Math.floor((stage.clientWidth - 68) / (G.w * PX) * 20) / 20); }

        // page setup dialog
        const dlg = () => $('[data-modal="pagesetup"]');
        const dq = k => dlg().querySelector('[data-ps="' + k + '"]');
        function dlgNote() {
            const lh = lhOn(), def = G.def, note = dq('note'); let warn = false;
            const val = s => parseFloat(dq(s).value);
            let txt = lh ? 'The letterhead is on: the default margins (top ' + round2(def.top) + ' cm, bottom ' + round2(def.bottom) + ' cm) keep the text clear of it.' : 'No letterhead: the default margin is ' + round2(def.top) + ' cm.';
            if (lh && (val('top') < def.top - .05 || val('bottom') < def.bottom - .05)) { txt += ' A smaller top or bottom margin lets the text run over the letterhead.'; warn = true; }
            note.textContent = txt; note.classList.toggle('warn', warn);
        }
        function openPageSetup() {
            dq('size').value = ps.size;
            dlg().querySelectorAll('input[name="we-ps-orient"]').forEach(r => { r.checked = r.value === ps.orient; });
            SIDES.forEach(s => { const i = dq(s); i.value = round2(G.cm[s]); i.placeholder = round2(G.def[s]); });
            dlgNote(); openModal('pagesetup');
        }
        SIDES.forEach(s => dq(s).addEventListener('input', dlgNote));
        dq('reset').addEventListener('click', () => { SIDES.forEach(s => { dq(s).value = round2(G.def[s]); }); dlgNote(); });
        dq('ok').addEventListener('click', () => {
            ps.size = dq('size').value;
            const o = dlg().querySelector('input[name="we-ps-orient"]:checked'); ps.orient = o ? o.value : 'portrait';
            SIDES.forEach(s => {
                const v = parseFloat(String(dq(s).value).replace(',', '.'));
                ps.m[s] = isNaN(v) || Math.abs(v - G.def[s]) < .005 ? null : round2(Math.min(MARGIN_MAX, Math.max(MARGIN_MIN, v)));
            });
            closeModals(); applyPage();
        });

        // ── pagination ───────────────────────────────────────────────────
        // One continuous text is drawn over several sheets. Where a sheet ends, a spacer (class we-pgap, never saved) pushes the
        // next line / block / table row over the gap and the margins to the top of the next sheet. Measured in css px of the text
        // area; sheet n holds the text between n·pitch and n·pitch + c.
        const sheets = $('.we-sheets');
        const isGap = el => !!el && el.nodeType === 1 && el.classList.contains('we-pgap');
        const realKids = el => Array.from(el.children).filter(x => !isGap(x));
        let pgTimer = null;

        function clearGaps() {
            const gaps = ed.querySelectorAll('.we-pgap'); if (!gaps.length) return;
            gaps.forEach(g => g.remove());
            ed.normalize();                                  // text split around an inline spacer joins again (the cursor follows)
        }
        function schedulePaginate() { clearTimeout(pgTimer); pgTimer = setTimeout(() => { if (!composing) repaginate(); }, 220); }
        function repaginate() { clearTimeout(pgTimer); clearGaps(); paginate(); updateInfo(); }

        function pushHit(cx, el, idx) {                      // move `el` (or the container it opens) to the top of the next sheet
            const o = cx.rect(el).t - idx * cx.pitch;
            if (o <= num(getComputedStyle(el).marginTop) + 2) return null;      // already at the top of its sheet: nothing to gain
            let t = el;
            while (t.parentElement && t.parentElement !== ed && realKids(t.parentElement)[0] === t) t = t.parentElement;
            const prev = (() => { let p = t.previousElementSibling; while (p && isGap(p)) p = p.previousElementSibling; return p; })();
            if (prev && /^H[1-3]$/.test(prev.tagName) && cx.rect(prev).t - idx * cx.pitch > num(getComputedStyle(prev).marginTop) + 2) t = prev;   // a heading stays with its text
            return { kind: t.tagName === 'TR' ? 'row' : 'block', el: t, target: (idx + 1) * cx.pitch };
        }
        function inspectLines(cx, el) {
            const w = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, { acceptNode: n => (n.parentElement && n.parentElement.closest('.we-pgap')) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT });
            const rg = document.createRange(); let first = true;
            while (w.nextNode()) {
                const node = w.currentNode; if (!node.data.length) continue;
                rg.selectNodeContents(node);
                const lhh = parseFloat(getComputedStyle(node.parentElement).lineHeight);
                for (const q of rg.getClientRects()) {
                    if (q.width < .5 || q.height < .5) continue;
                    const extra = isNaN(lhh) ? 0 : Math.max(0, (lhh - q.height / cx.k) / 2);      // the line box is taller than the glyphs
                    const t = cx.Y(q.top) - extra, b = cx.Y(q.bottom) + extra;
                    const idx = Math.floor((t + .5) / cx.pitch), base = idx * cx.pitch;
                    if (b - base > cx.c + .5 || t - base > cx.c - 1) {
                        if (first) return 'first';
                        return { kind: 'inline', el, node, lineTop: q.top, target: (idx + 1) * cx.pitch };
                    }
                    first = false;
                }
            }
            return null;
        }
        const hasBlocks = el => !!el.querySelector(':scope > ul, :scope > ol, :scope > p, :scope > div:not(.we-pgap), :scope > table, :scope > blockquote, :scope > h1, :scope > h2, :scope > h3');
        function inspect(cx, el) {
            if (cx.bad.has(el) || isGap(el)) return null;
            const r = cx.rect(el), idx = Math.floor((r.t + .5) / cx.pitch), base = idx * cx.pitch;
            if (r.t - base > cx.c - 1) return pushHit(cx, el, idx);              // starts below the last line of its sheet (or inside the gap)
            if (r.b - base <= cx.c + .5) return null;                            // fits on its sheet
            const tag = el.tagName;
            if (tag === 'TABLE') { for (const row of el.rows) { const h = inspect(cx, row); if (h) return h; } return null; }
            if (tag === 'TR' || tag === 'TD' || tag === 'TH' || /^H[1-6]$/.test(tag)) return pushHit(cx, el, idx);   // rows and headings do not split
            if (/^(UL|OL|DIV|BLOCKQUOTE|LI)$/.test(tag) && (tag === 'UL' || tag === 'OL' || hasBlocks(el))) {
                for (const ch of realKids(el)) { const h = inspect(cx, ch); if (h) return h; }
                return null;
            }
            const res = inspectLines(cx, el);
            return res === 'first' ? pushHit(cx, el, idx) : res;
        }
        function findBreak(cx) {
            let forced = null;
            for (const el of ed.children) {
                if (isGap(el)) continue;
                const r = cx.rect(el);
                if (forced !== null) {                                            // after a page break the next block opens a new sheet
                    if (r.t + .5 < forced && !cx.bad.has(el)) return { kind: 'block', el, target: forced };
                    forced = null;
                }
                if (el.matches('p.we-pb')) { forced = (Math.floor((r.t + .5) / cx.pitch) + 1) * cx.pitch; continue; }
                const h = inspect(cx, el); if (h) return h;
            }
            return null;
        }
        /** Where the text of `node` starts the line whose top (client px) is `lineTop`. */
        function lineStart(node, lineTop) {
            const rg = document.createRange();
            const topOf = o => { for (let j = o; j < Math.min(node.length, o + 8); j++) { rg.setStart(node, j); rg.setEnd(node, j + 1); const q = rg.getClientRects()[0]; if (q && q.height > 0) return q.top; } return Infinity; };
            let lo = 0, hi = node.length;
            while (lo < hi) { const mid = (lo + hi) >> 1; if (topOf(mid) >= lineTop - 1) hi = mid; else lo = mid + 1; }
            return lo;
        }
        function placeGap(cx, hit) {
            let sp;
            if (hit.kind === 'inline') {
                sp = document.createElement('span'); sp.className = 'we-pgap in'; sp.contentEditable = 'false';
                const rg = document.createRange(); rg.setStart(hit.node, lineStart(hit.node, hit.lineTop)); rg.collapse(true); rg.insertNode(sp);
            } else if (hit.kind === 'row') {
                sp = document.createElement('tr'); sp.className = 'we-pgap we-pgap-row'; sp.contentEditable = 'false';
                const td = document.createElement('td'); td.colSpan = Array.from(hit.el.closest('table').rows[0].cells).reduce((n, c) => n + (c.colSpan || 1), 0) || 1; sp.appendChild(td);
                hit.el.parentNode.insertBefore(sp, hit.el);
            } else {
                sp = document.createElement('div'); sp.className = 'we-pgap'; sp.contentEditable = 'false';
                hit.el.parentNode.insertBefore(sp, hit.el);
            }
            const setH = h => { (hit.kind === 'row' ? sp.firstChild : sp).style.height = h + 'px'; };
            let H = Math.max(0, hit.target - cx.rect(sp).t);
            for (let i = 0; i < 4; i++) {                    // margins and line boxes make the first guess a few px off: measure and correct
                setH(H);
                const err = hit.target - cx.rect(sp).b;
                if (Math.abs(err) < .5) break;
                H += err; if (H < 0) break;
            }
            if (H < 1) { sp.remove(); ed.normalize(); return false; }
            return true;
        }
        function paginate() {
            if (composing) return;
            const k = scale(), c = (G.h - G.mt - G.mb) * PX, Z = (G.mt + G.mb) * PX + GAP;
            const cx = { c, pitch: c + Z, k, bad: new Set(), Y: v => (v - ed.getBoundingClientRect().top) / k };
            cx.rect = el => { const r = el.getBoundingClientRect(); return { t: cx.Y(r.top), b: cx.Y(r.bottom) }; };
            for (let n = 0; n < 200; n++) {
                const hit = findBreak(cx);
                if (!hit) break;
                if (!placeGap(cx, hit)) cx.bad.add(hit.el);
            }
            const kids = realKids(ed), last = kids[kids.length - 1];
            const bottom = last ? cx.rect(last).b : 0;
            pg.pages = Math.max(1, Math.min(60, Math.floor(Math.max(0, bottom - 1) / cx.pitch) + 1));
            ed.style.minHeight = (pg.pages * c + (pg.pages - 1) * Z) + 'px';
            const H = G.h * PX;
            while (sheets.children.length > pg.pages) sheets.lastChild.remove();
            while (sheets.children.length < pg.pages) { const s = document.createElement('div'); s.className = 'we-sheet'; s.innerHTML = '<span class="we-sheet-no"></span>'; sheets.appendChild(s); }
            Array.from(sheets.children).forEach((s, i) => { s.style.top = i * (H + GAP) + 'px'; s.firstChild.textContent = 'Page ' + (i + 1) + ' of ' + pg.pages; });
            updatePageNo();
        }
        /** Index (0-based) of the sheet the cursor is on. */
        function curPageIdx() {
            const r = range(); if (!r || !inEd(r.startContainer)) return Math.min(pg.cur || 0, pg.pages - 1);
            let q = null;
            const rc = r.cloneRange(); rc.collapse(true);
            const rs = rc.getClientRects(); if (rs.length) q = rs[0];
            let atBottom = false;
            if (!q) {
                const sc = r.startContainer;
                if (sc.nodeType === 1) {
                    let ch = sc.childNodes[r.startOffset]; while (ch && isGap(ch)) ch = ch.nextSibling;
                    let pv = sc.childNodes[r.startOffset - 1]; while (pv && isGap(pv)) pv = pv.previousSibling;
                    const el = ch || pv || sc; atBottom = !ch && !!pv;
                    q = (el.nodeType === 1 ? el : el.parentElement).getBoundingClientRect();
                } else q = sc.parentElement.getBoundingClientRect();
            }
            if (!q) return 0;
            const c = (G.h - G.mt - G.mb) * PX, pitch = c + (G.mt + G.mb) * PX + GAP;
            const y = ((atBottom ? q.bottom - 4 : q.top) - ed.getBoundingClientRect().top) / scale();
            pg.cur = Math.max(0, Math.min(pg.pages - 1, Math.floor((y + 2) / pitch)));
            return pg.cur;
        }
        const pageLabel = $('.we-status-page');
        function updatePageNo() {
            const idx = curPageIdx();
            pageLabel.textContent = ps.size + ' · ' + (ps.orient === 'landscape' ? 'Landscape' : 'Portrait') + ' · Page ' + (idx + 1) + ' of ' + pg.pages;
            vr.style.top = (idx * (G.h * PX + GAP)) + 'px'; vr.style.height = G.h + 'pt';
        }

        function updateInfo() {
            const t = ed.innerText.replace(/ /g, ' ').trim();
            const words = t ? t.split(/\s+/).filter(w => !w.startsWith('{{halaman')).length : 0;
            status.textContent = words + ' words · ' + t.length + ' characters';
            updatePageNo();
        }

        // ── rulers: indents (top) and margins (top + left) ───────────────
        const rulerbar = $('.we-rulerbar'), ruler = $('.we-ruler'), vr = $('.we-vruler'), page_ = $('.we-page');
        const tip = document.createElement('div'); tip.className = 'we-tip'; rulerbar.appendChild(tip);
        const tipV = document.createElement('div'); tipV.className = 'we-tip v'; page_.appendChild(tipV);
        let rulerSig = '';
        /** Tick marks every 0.25 cm counted from the margin edge, numbers every cm (both directions, as in Word). */
        function ticksHtml(axis, len) {
            const q = CM / 4, from = -Math.floor(MARGIN_MAX * 4), to = Math.ceil(len / q), pos = axis === 'x' ? 'left' : 'top';
            let h = '';
            for (let i = from; i <= to; i++) {
                const p = pos + ':' + round1(i * q * 100) / 100 + 'pt';
                if (i % 4 === 0) { if (i) h += '<div class="we-tick-n" style="' + p + '">' + Math.abs(i / 4) + '</div>'; }
                else h += '<div class="we-tick ' + (i % 2 ? 'q' : 'h') + '" style="' + p + '"></div>';
            }
            return h;
        }
        function buildRulers() {
            const sig = ps.size + ps.orient;
            if (sig !== rulerSig) {
                rulerSig = sig;
                ruler.innerHTML = '<div class="we-rz l" data-edge="left"></div><div class="we-rz r" data-edge="right"></div><div class="we-ticks">' + ticksHtml('x', G.w) + '</div>'
                    + '<div class="we-edge" data-edge="left" title="Left margin: drag"></div><div class="we-edge" data-edge="right" title="Right margin: drag"></div>'
                    + ['first', 'left', 'both', 'right'].map(k => '<div class="we-mk we-mk-' + k + '" data-mk="' + k + '" title="' + { first: 'First-line indent', left: 'Hanging indent (left)', both: 'Left indent', right: 'Right indent' }[k] + '"></div>').join('');
                vr.innerHTML = '<div class="we-rz t" data-edge="top"></div><div class="we-rz b" data-edge="bottom"></div><div class="we-ticks">' + ticksHtml('y', G.h) + '</div>'
                    + '<div class="we-edge" data-edge="top" title="Top margin: drag"></div><div class="we-edge" data-edge="bottom" title="Bottom margin: drag"></div>';
            }
            ruler.querySelector('.we-edge[data-edge="left"]').style.left = G.ml + 'pt';
            ruler.querySelector('.we-edge[data-edge="right"]').style.left = (G.w - G.mr) + 'pt';
            vr.querySelector('.we-edge[data-edge="top"]').style.top = G.mt + 'pt';
            vr.querySelector('.we-edge[data-edge="bottom"]').style.top = (G.h - G.mb) + 'pt';
        }
        const mk = k => ruler.querySelector('[data-mk="' + k + '"]');
        const scale = () => { const w = ed.offsetWidth; return w ? ed.getBoundingClientRect().width / w : 1; };

        /** Geometry of the reference block, in css px relative to the text area. */
        function geom(b) {
            const k = scale(), eb = ed.getBoundingClientRect(), r = b.getBoundingClientRect(), cs = getComputedStyle(b);
            const left = (r.left - eb.left) / k + num(cs.paddingLeft) + num(cs.borderLeftWidth);
            const right = (r.right - eb.left) / k - num(cs.paddingRight) - num(cs.borderRightWidth);
            return { left, right, first: left + num(cs.textIndent), k, width: eb.width / k };
        }
        function placeMarkers(g) {
            const o = G.ml * PX;
            mk('first').style.left = (o + g.first) + 'px'; mk('left').style.left = (o + g.left) + 'px'; mk('both').style.left = (o + g.left) + 'px'; mk('right').style.left = (o + g.right) + 'px';
        }
        function updateRuler() {
            if (!mk('first')) return;
            const bl = selectedBlocks(), b = bl[0];
            ['first', 'left', 'both', 'right'].forEach(k => mk(k).classList.toggle('off', !b));
            if (!b) { placeMarkers({ first: 0, left: 0, right: (G.w - G.ml - G.mr) * PX }); return; }
            if (drag) return;
            placeMarkers(geom(b));
        }
        let drag = null;
        ruler.addEventListener('pointerdown', e => {
            const m = e.target.closest('[data-mk]'); if (!m) return;
            e.preventDefault(); restoreSel(); flush();
            const bl = selectedBlocks(); if (!bl.length) return;
            const g = geom(bl[0]);
            drag = { kind: m.dataset.mk, x0: e.clientX, g, blocks: bl.map(b => { const cs = getComputedStyle(b); return { b, ml: num(cs.marginLeft), mr: num(cs.marginRight), ti: num(cs.textIndent) }; }) };
            m.setPointerCapture(e.pointerId);
        });
        ruler.addEventListener('pointermove', e => {
            if (!drag) return;
            const snap = e.altKey ? 0 : CM / 8;
            const toPt = px => { const v = px / PX; return snap ? Math.round(v / snap) * snap : v; };
            const dx = (e.clientX - drag.x0) / drag.g.k;
            let shown = 0;
            drag.blocks.forEach(o => {
                if (drag.kind === 'first') { const ti = toPt(o.ti + dx); o.b.style.textIndent = round1(ti) + 'pt'; shown = ti; }
                else if (drag.kind === 'left') { const ml = Math.max(0, toPt(o.ml + dx)), d = ml * PX - o.ml; o.b.style.marginLeft = round1(ml) + 'pt'; o.b.style.textIndent = round1((o.ti - d) / PX) + 'pt'; shown = ml; }
                else if (drag.kind === 'both') { const ml = Math.max(0, toPt(o.ml + dx)); o.b.style.marginLeft = round1(ml) + 'pt'; shown = ml; }
                else { const mr = Math.max(0, toPt(o.mr - dx)); o.b.style.marginRight = round1(mr) + 'pt'; shown = mr; }
            });
            tip.style.display = 'block'; tip.style.left = mk(drag.kind).style.left || '0'; tip.textContent = (shown / CM).toFixed(2) + ' cm';
            placeMarkers(geom(drag.blocks[0].b));
        });
        const endDrag = () => { if (!drag) return; drag = null; tip.style.display = 'none'; commit(); updateRuler(); };
        ruler.addEventListener('pointerup', endDrag); ruler.addEventListener('pointercancel', endDrag);

        // drag the grey ends of the rulers to set the margins (a margin set this way becomes a custom value)
        let mdrag = null;
        function bindMargins(el, axis) {
            el.addEventListener('pointerdown', e => {
                const h = e.target.closest('[data-edge]'); if (!h || e.target.closest('[data-mk]')) return;
                e.preventDefault(); clearGaps();
                mdrag = { side: h.dataset.edge, axis, p0: axis === 'x' ? e.clientX : e.clientY, start: G.cm[h.dataset.edge], el: h };
                h.setPointerCapture(e.pointerId); el.querySelectorAll('.we-edge[data-edge="' + mdrag.side + '"]').forEach(x => x.classList.add('active'));
            });
            el.addEventListener('pointermove', e => {
                if (!mdrag || mdrag.axis !== axis) return;
                const d = ((axis === 'x' ? e.clientX : e.clientY) - mdrag.p0) / scale() / PX / CM;
                const s = mdrag.side, grows = s === 'left' || s === 'top';
                let v = mdrag.start + (grows ? d : -d);
                if (!e.altKey) v = Math.round(v * 20) / 20;                       // 0.05 cm steps
                const opp = { left: 'right', right: 'left', top: 'bottom', bottom: 'top' }[s], total = (axis === 'x' ? G.w : G.h) / CM;
                v = Math.max(MARGIN_MIN, Math.min(MARGIN_MAX, total - TEXT_MIN - G.cm[opp], v));
                ps.m[s] = round2(v);
                applyPage(false);
                const t = axis === 'x' ? tip : tipV;
                t.style.display = 'block'; t.textContent = s.charAt(0).toUpperCase() + s.slice(1) + ' margin ' + round2(v).toFixed(2) + ' cm';
                if (axis === 'x') t.style.left = (s === 'left' ? G.ml : G.w - G.mr) + 'pt'; else t.style.top = (parseFloat(vr.style.top) || 0) + (s === 'top' ? G.mt : G.h - G.mb) * PX + 'px';
            });
            const end = () => {
                if (!mdrag) return;
                el.querySelectorAll('.we-edge.active').forEach(x => x.classList.remove('active'));
                mdrag = null; tip.style.display = 'none'; tipV.style.display = 'none';
                repaginate();
            };
            el.addEventListener('pointerup', end); el.addEventListener('pointercancel', end);
        }
        bindMargins(ruler, 'x'); bindMargins(vr, 'y');

        // ── toolbar state ────────────────────────────────────────────────
        function sync() {
            const r = range(); if (!r || !inEd(r.startContainer)) return;
            const st = c => { try { return document.queryCommandState(c); } catch (x) { return false; } };
            const on = (cmd, v) => { const b = $('[data-cmd="' + cmd + '"]'); if (b) b.classList.toggle('on', !!v); };
            on('bold', st('bold')); on('italic', st('italic')); on('underline', st('underline')); on('strike', st('strikeThrough')); on('sub', st('subscript')); on('sup', st('superscript'));
            on('left', st('justifyLeft')); on('center', st('justifyCenter')); on('right', st('justifyRight')); on('justify', st('justifyFull'));
            on('ul', st('insertUnorderedList')); on('ol', st('insertOrderedList'));
            const b = selectedBlocks()[0], el = r.startContainer.nodeType === 1 ? r.startContainer : r.startContainer.parentElement;
            if (b) { const t = b.tagName.toLowerCase(); ctl('block').value = /^h[1-3]$/.test(t) ? t : 'p'; }
            if (el) {
                const cs = getComputedStyle(el), fam = (cs.fontFamily || '').split(',')[0].replace(/["']/g, '').trim();
                ctl('font').value = FONTS.find(f => f.toLowerCase() === fam.toLowerCase()) || '';
                if (document.activeElement !== sizeIn) sizeIn.value = currentSizePt();
                const lh = b ? num(getComputedStyle(b).lineHeight) / num(cs.fontSize) : 0;
                const opt = [1, 1.15, 1.5, 2, 2.5].find(v => Math.abs(v - lh) < .02);
                ctl('spacing').value = b && b.style.lineHeight && opt ? String(opt) : 'default';
            }
        }

        // ── find & replace ───────────────────────────────────────────────
        const fp = $('.we-find'), fIn = fp.querySelector('[data-f="find"]'), rIn = fp.querySelector('[data-f="replace"]');
        const fCase = fp.querySelector('[data-f="case"]'), fWord = fp.querySelector('[data-f="word"]'), fCnt = fp.querySelector('.we-cnt');
        function openFind(replaceFocus) {
            const s = String(selection()).trim(); if (s && !/\n/.test(s) && s.length < 80) fIn.value = s;
            closePops(); fp.classList.add('open'); (replaceFocus ? rIn : fIn).focus(); (replaceFocus ? rIn : fIn).select(); doFind();
        }
        function closeFind() { fp.classList.remove('open'); clearMarks(); fCnt.textContent = ''; }
        function doFind(keep) {
            const keepIdx = keep ? cur : -1; clearMarks();
            const q = fIn.value; if (!q) { fCnt.textContent = ''; return; }
            let src = escRe(q); if (fWord.checked) src = '\\b' + src + '\\b';
            const re = new RegExp(src, fCase.checked ? 'g' : 'gi');
            const w = document.createTreeWalker(ed, NodeFilter.SHOW_TEXT), nodes = []; while (w.nextNode()) nodes.push(w.currentNode);
            nodes.forEach(node => {
                const ms = Array.from(node.data.matchAll(re)).filter(m => m[0] !== '');
                for (let i = ms.length - 1; i >= 0; i--) { const rg = document.createRange(); rg.setStart(node, ms[i].index); rg.setEnd(node, ms[i].index + ms[i][0].length); const m = document.createElement('mark'); m.className = 'we-find'; rg.surroundContents(m); }
            });
            marks = Array.from(ed.querySelectorAll('mark.we-find'));
            cur = marks.length ? Math.min(Math.max(keepIdx, 0), marks.length - 1) : -1; showCur();
        }
        function showCur() {
            marks.forEach((m, i) => m.classList.toggle('cur', i === cur));
            fCnt.textContent = marks.length ? (cur + 1) + ' of ' + marks.length : (fIn.value ? 'No result' : '');
            if (marks[cur]) marks[cur].scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
        function step(d) { if (!marks.length) return; cur = (cur + d + marks.length) % marks.length; showCur(); }
        function replaceOne() {
            if (cur < 0 || !marks[cur]) return;
            flush(); const m = marks[cur]; m.replaceWith(document.createTextNode(rIn.value)); ed.normalize(); const keep = cur; marks.splice(cur, 1);
            decorate(); commit(); doFind(true); cur = Math.min(keep, marks.length - 1); showCur();
        }
        function replaceAll() {
            if (!marks.length) return; flush(); const n = marks.length;
            marks.forEach(m => m.replaceWith(document.createTextNode(rIn.value))); ed.normalize(); marks = []; cur = -1;
            decorate(); commit(); doFind(); fCnt.textContent = n + ' replaced';
        }
        fIn.addEventListener('input', () => doFind());
        [fCase, fWord].forEach(c => c.addEventListener('change', () => doFind()));
        fp.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); if (e.target === rIn) replaceOne(); else step(e.shiftKey ? -1 : 1); } });
        fp.querySelector('[data-f="next"]').addEventListener('click', () => step(1));
        fp.querySelector('[data-f="prev"]').addEventListener('click', () => step(-1));
        fp.querySelector('[data-f="one"]').addEventListener('click', replaceOne);
        fp.querySelector('[data-f="all"]').addEventListener('click', replaceAll);
        fp.querySelector('[data-f="close"]').addEventListener('click', () => { closeFind(); ed.focus(); });

        // ── modals, preview, full screen ─────────────────────────────────
        const modal = n => $('[data-modal="' + n + '"]');
        function openModal(n) { closePops(); modal(n).classList.add('open'); }
        function closeModals() { $$('.we-modal.open').forEach(m => m.classList.remove('open')); }
        $$('[data-modal-close]').forEach(b => b.addEventListener('click', closeModals));
        $$('.we-modal').forEach(m => m.addEventListener('mousedown', e => { if (e.target === m) closeModals(); }));
        function toggleFull(force) {
            const on = force === undefined ? !root.classList.contains('we-full') : force;
            root.classList.toggle('we-full', on); document.body.style.overflow = on ? 'hidden' : '';
            const i = $('[data-cmd="full"] i'); if (i) i.className = on ? 'fas fa-compress' : 'fas fa-expand';
        }
        let blobUrl = null;
        async function preview() {
            const url = root.dataset.previewUrl; if (!url) return;
            flush(); clearMarks(); closePops();
            const fd = new FormData();
            fd.append('_token', (document.querySelector('meta[name="csrf-token"]') || {}).content || (form && form.querySelector('[name="_token"]') ? form.querySelector('[name="_token"]').value : ''));
            fd.append('body_html', getHTML());
            fd.append('contract_type', typeSel ? typeSel.value : 'PKWT');
            fd.append('use_letterhead', !lhToggle || lhToggle.checked ? '1' : '0');
            // host page: more inputs to send along (e.g. the letter language and field definitions)
            if (root.dataset.previewExtra) document.querySelectorAll(root.dataset.previewExtra).forEach(i => { if (i.name) fd.append(i.name, i.value); });
            fd.append('page_size', ps.size); fd.append('page_orientation', ps.orient);
            SIDES.forEach(x => fd.append('margin_' + x, ps.m[x] === null ? '' : String(ps.m[x])));
            const box = modal('preview'), frame = box.querySelector('iframe'), msg = box.querySelector('.we-prev-msg');
            openModal('preview'); msg.textContent = 'Preparing the PDF…'; msg.style.display = 'block'; frame.style.visibility = 'hidden';
            try {
                const res = await fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const blob = await res.blob(); if (blobUrl) URL.revokeObjectURL(blobUrl);
                blobUrl = URL.createObjectURL(blob); frame.src = blobUrl + '#view=FitH'; frame.style.visibility = 'visible'; msg.style.display = 'none';
            } catch (err) { msg.textContent = 'The preview could not be created (' + err.message + '). Check the text and try again.'; }
        }

        // ── form ─────────────────────────────────────────────────────────
        if (form && hidden) {
            form.addEventListener('submit', e => {
                if (e.defaultPrevented) return;
                flush(); clearMarks();
                hidden.value = getHTML();
                if (!root.dataset.allowEmpty && !ed.textContent.trim()) { e.preventDefault(); if (window.showNotification) window.showNotification('The contract text cannot be empty.', 'error'); }
            });
        }

        // ── start ────────────────────────────────────────────────────────
        // loose text at the top level becomes paragraphs so every line has a block to carry its indent / spacing
        (function normalize() {
            const kids = Array.from(ed.childNodes), blockish = n => n.nodeType === 1 && /^(P|H[1-6]|DIV|UL|OL|TABLE|BLOCKQUOTE)$/.test(n.tagName);
            let buf = null;
            kids.forEach(n => { if (blockish(n)) { buf = null; return; } if (n.nodeType === 3 && !n.data.trim() && !buf) return; if (!buf) { buf = document.createElement('p'); ed.insertBefore(buf, n); } buf.appendChild(n); });
            if (!ed.firstChild) ed.innerHTML = '<p><br></p>';
        })();
        decorate(); syncPage(); setZoom(1); commit(); updateInfo(); syncUndo();
        window.addEventListener('resize', updateRuler);
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(() => { if (!composing) repaginate(); });

        const api = { el: ed, root, getHTML, setHTML(h) { run(() => { ed.innerHTML = h; }); }, undo, redo, exec: CMD, tableOp: op => run(() => tableOp(op)), insertTable: (r, c) => run(() => insertTable(r, c)), find: q => { fIn.value = q; openFind(false); return marks.length; }, geom, selectedBlocks, setZoom, cleanPaste, history: hist,
            page: ps, pageInfo: () => ({ pages: pg.pages, cur: curPageIdx() + 1, geom: G }), setPage: p => { if (p.size) ps.size = p.size; if (p.orient) ps.orient = p.orient; if (p.m) Object.assign(ps.m, p.m); applyPage(); }, repaginate };
        root._we = api;
        return api;
    }

    function boot() { document.querySelectorAll('[data-word-editor]').forEach(init); }
    window.WordEditor = { init, boot };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
