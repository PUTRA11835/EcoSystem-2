/**
 * Uji peramban (Chrome headless + CDP; Node >= 22) halaman User Guide (/user-guide):
 *   daftar isi (klik → gulir + hash, scroll-spy), pencarian (hasil, klik → sorot kata, bersihkan), tombol "/" , bahasa (tombol + ingatan di peramban),
 *   tautan PDF, tanpa galat JavaScript, dan tata letak pada lebar ponsel / tablet / desktop (tanpa gulir mendatar).
 * HANYA MEMBACA.
 *
 *   MSYS_NO_PATHCONV=1 node docs/HRGA/tools/e2e-user-guide.mjs http://127.0.0.1:8765 <cookie> <sid> <kata-cari> [folder-screenshot]
 */
import { spawn } from 'node:child_process';
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const [base, cookieName, sid, word = 'approval', shotDir] = process.argv.slice(2);
if (!base || !cookieName || !sid) { console.error('Pakai: node e2e-user-guide.mjs <base> <cookie> <sid> [kata] [folder]'); process.exit(2); }
if (shotDir) mkdirSync(shotDir, { recursive: true });
const CHROME = process.env.CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const PORT = 9300 + Math.floor(Math.random() * 400);
const dir = mkdtempSync(join(tmpdir(), 'ug-'));
const chrome = spawn(CHROME, ['--headless=new', `--remote-debugging-port=${PORT}`, `--user-data-dir=${dir}`, '--no-first-run', '--disable-gpu', 'about:blank'], { stdio: 'ignore' });
const sleep = ms => new Promise(r => setTimeout(r, ms));
let ok = 0, fail = 0;
const t = (name, cond, detail = '') => { cond ? ok++ : fail++; console.log((cond ? '  [OK]    ' : '  [GAGAL] ') + name + (detail ? '  — ' + detail : '')); };
let ws, id = 0; const pending = new Map(); const jsErrors = [];
const send = (method, params = {}) => new Promise((res, rej) => { const i = ++id; pending.set(i, { res, rej }); ws.send(JSON.stringify({ id: i, method, params })); });
const ev = async expr => { const r = await send('Runtime.evaluate', { expression: expr, returnByValue: true, awaitPromise: true }); if (r.exceptionDetails) throw new Error('JS: ' + (r.exceptionDetails.exception?.description || r.exceptionDetails.text)); return r.result.value; };
const open = async path => { await send('Page.navigate', { url: base + path }); await sleep(400); for (let i = 0; i < 80 && (await ev('document.readyState')) !== 'complete'; i++) await sleep(150); await sleep(1400); };
const shot = async name => { if (!shotDir) return; const r = await send('Page.captureScreenshot', { format: 'png' }); writeFileSync(join(shotDir, name), Buffer.from(r.data, 'base64')); };

try {
    let url;
    for (let i = 0; i < 60 && !url; i++) { try { url = (await (await fetch(`http://127.0.0.1:${PORT}/json`)).json()).find(x => x.type === 'page')?.webSocketDebuggerUrl; } catch {} await sleep(250); }
    ws = new WebSocket(url);
    await new Promise(r => ws.addEventListener('open', r));
    ws.addEventListener('message', m => {
        const d = JSON.parse(m.data);
        if (d.id && pending.has(d.id)) { const p = pending.get(d.id); pending.delete(d.id); d.error ? p.rej(new Error(d.error.message)) : p.res(d.result); return; }
        if (d.method === 'Runtime.exceptionThrown') jsErrors.push(d.params.exceptionDetails.exception?.description || d.params.exceptionDetails.text);
    });
    await send('Network.enable'); await send('Page.enable'); await send('Runtime.enable');
    await send('Network.setCookie', { name: cookieName, value: sid, domain: new URL(base).hostname, path: '/', httpOnly: true });
    await send('Emulation.setDeviceMetricsOverride', { width: 1366, height: 900, deviceScaleFactor: 1, mobile: false });

    console.log('== Desktop 1366');
    await open('/user-guide?lang=en');
    const st = await ev(`({ topics: document.querySelectorAll('.ug-topic').length, toc: document.querySelectorAll('#ugToc a[data-topic]').length, chapters: document.querySelectorAll('.ug-chap').length, pdf: !!document.querySelector('a[href*="/user-guide/pdf"]'), idx: document.querySelector('#ugSearch') !== null })`);
    t('halaman memuat topik, daftar isi, bab, kotak cari, tautan PDF', st.topics > 0 && st.toc === st.topics && st.chapters > 0 && st.pdf && st.idx, JSON.stringify(st));
    await shot('1366-guide.png');

    // Klik daftar isi → gulir ke topik + hash + sorot aktif
    const target = await ev(`(() => { const a = [...document.querySelectorAll('#ugToc a[data-topic]')].filter(x => x.offsetParent !== null).slice(-1)[0]; return a ? a.getAttribute('data-topic') : null; })()`);
    await ev(`document.querySelector('#ugToc a[data-topic="${target}"]').click()`); await sleep(1500);
    const sc = await ev(`(() => { const el = document.getElementById('topic-${target}'); const r = el.getBoundingClientRect(); return { top: Math.round(r.top), hash: location.hash, active: document.querySelector('#ugToc a.is-active')?.getAttribute('data-topic') }; })()`);
    t(`klik daftar isi → menggulir ke topik "${target}" (dekat tepi atas), hash terisi`, sc.top >= 0 && sc.top < 220 && sc.hash === '#topic-' + target, JSON.stringify(sc));
    t('scroll-spy: topik yang sedang dibaca tersorot di daftar isi', sc.active === target, 'aktif=' + sc.active);

    // Pencarian
    await ev(`(() => { const i = document.getElementById('ugSearch'); i.focus(); i.value = ${JSON.stringify(word)}; i.dispatchEvent(new Event('input', { bubbles: true })); })()`); await sleep(500);
    const rs = await ev(`({ results: document.querySelectorAll('#ugResults a.ug-res').length, tocHidden: document.getElementById('ugToc').classList.contains('hidden'), shown: !document.getElementById('ugResults').classList.contains('hidden') })`);
    t(`pencarian "${word}" menampilkan hasil dan menyembunyikan daftar isi`, rs.shown && rs.results > 0 && rs.tocHidden, JSON.stringify(rs));
    await shot('1366-guide-search.png');
    await ev(`document.querySelector('#ugResults a.ug-res').click()`); await sleep(1500);
    const hit = await ev(`({ marks: document.querySelectorAll('mark.ug-hit').length, hash: location.hash })`);
    t('klik hasil → gulir ke bagian itu dan kata yang dicari disorot', hit.marks > 0 && hit.hash.length > 1, JSON.stringify(hit));
    await ev(`document.getElementById('ugSearchClear').click()`); await sleep(300);
    const cl = await ev(`({ toc: !document.getElementById('ugToc').classList.contains('hidden'), res: document.getElementById('ugResults').classList.contains('hidden'), marks: document.querySelectorAll('mark.ug-hit').length })`);
    t('bersihkan pencarian → daftar isi kembali, sorotan hilang', cl.toc && cl.res && cl.marks === 0, JSON.stringify(cl));
    await ev(`(() => { const i = document.getElementById('ugSearch'); i.value = 'zzqqxxnotfound'; i.dispatchEvent(new Event('input', { bubbles: true })); })()`); await sleep(500);
    t('kata yang tidak ada → pesan "tidak ditemukan"', (await ev(`document.getElementById('ugResults').textContent`)).length > 5 && (await ev(`document.querySelectorAll('#ugResults a.ug-res').length`)) === 0);
    await ev(`(() => { const i = document.getElementById('ugSearch'); i.value = ''; i.dispatchEvent(new Event('input', { bubbles: true })); i.blur(); })()`);
    await send('Input.dispatchKeyEvent', { type: 'keyDown', key: '/', text: '/' }); await send('Input.dispatchKeyEvent', { type: 'keyUp', key: '/' }); await sleep(300);
    t('tombol "/" memindahkan fokus ke kotak pencarian', (await ev(`document.activeElement && document.activeElement.id`)) === 'ugSearch');

    // Penomoran + akordeon bab
    const nums = await ev(`({ chap: document.querySelector('.ug-chap-no')?.textContent.trim(), top: document.querySelector('.ug-t-no')?.textContent.trim(), contentChap: document.querySelector('.ug-chapter-title .ug-no')?.textContent.trim(), contentTopic: document.querySelector('.ug-topic-no')?.textContent.trim(), label: [...document.querySelectorAll('aside p')].map(p => p.textContent).join('|').includes('Table of Contents') || document.querySelector('#ugTocBox')?.textContent.includes('Table of Contents') })`);
    t('daftar isi berjudul "Table of Contents"; bab dan topik bernomor di daftar isi DAN di isi (1 / 1.1)', nums.label && nums.chap === '1' && nums.top === '1.1' && nums.contentChap === '1' && nums.contentTopic === '1.1', JSON.stringify(nums));
    const acc = await ev(`({ total: document.querySelectorAll('.ug-chap').length, open: document.querySelectorAll('.ug-chap:not(.is-closed)').length })`);
    t('awalnya hanya bab yang sedang dibaca terbuka (daftar isi ringkas)', acc.total > 1 && acc.open >= 1 && acc.open < acc.total, JSON.stringify(acc));
    await ev(`document.getElementById('ugExpandAll').click()`); await sleep(200);
    t('"Expand all" membuka semua bab', (await ev(`document.querySelectorAll('.ug-chap.is-closed').length`)) === 0);
    await ev(`document.getElementById('ugCollapseAll').click()`); await sleep(200);
    t('"Collapse all" menutup semua bab', (await ev(`document.querySelectorAll('.ug-chap:not(.is-closed)').length`)) === 0);
    await ev(`window.scrollBy(0, 9000)`); await sleep(1500);   // jauh, sampai topik berganti (bab yang baru dibaca terbuka otomatis)
    t('menggulir isi membuka otomatis bab yang sedang dibaca', (await ev(`document.querySelectorAll('.ug-chap:not(.is-closed)').length`)) >= 1);
    await ev(`document.querySelector('.ug-chap-btn').click()`); await sleep(200);
    t('bab di daftar isi dapat dilipat/dibuka manual', await ev(`document.querySelector('.ug-chap').classList.contains('is-closed') !== document.querySelector('.ug-chap-btn').getAttribute('aria-expanded').includes('false') ? false : true`));

    // Bahasa
    await ev(`document.querySelector('.ug-seg a[data-lang="id"]').click()`); await sleep(2500);
    const idp = await ev(`({ url: location.search, title: document.querySelector('#ugContent .ug-topic-title')?.textContent, ui: document.querySelector('a[href*="/user-guide/pdf"]')?.textContent.trim(), stored: localStorage.getItem('ecosystem:guide:lang') })`);
    t('tombol bahasa → panduan Indonesia (?lang=id), pilihan tersimpan di peramban', /lang=id/.test(idp.url) && idp.stored === 'id' && /Unduh PDF/.test(idp.ui || ''), JSON.stringify(idp));
    await open('/user-guide');
    t('kunjungan berikutnya tanpa ?lang= tetap berbahasa Indonesia (pilihan tersimpan / preferensi)', (await ev(`document.querySelector('#ugContent .ug-topic-title')?.textContent`)).includes('Tentang panduan ini'));
    await ev(`localStorage.setItem('ecosystem:guide:lang', 'en')`);
    await open('/user-guide?lang=id'); await open('/user-guide');
    t('pilihan tersimpan (en) menimpa preferensi bila tak ada ?lang=', (await ev(`document.querySelector('#ugContent .ug-topic-title')?.textContent`)).includes('About this guide') && /lang=en/.test(await ev('location.search')));
    await ev(`localStorage.removeItem('ecosystem:guide:lang')`);

    // Responsif
    for (const [name, w, h, mobile] of [['ponsel 360', 360, 780, true], ['tablet 768', 768, 1024, true], ['desktop 1280', 1280, 900, false]]) {
        console.log(`\n== ${name}`);
        await send('Emulation.setDeviceMetricsOverride', { width: w, height: h, deviceScaleFactor: 1, mobile });
        await open('/user-guide?lang=en');
        const o = await ev(`(() => { const iw = innerWidth, bad = []; for (const e of document.querySelectorAll('#ugContent *, #ugTocBox *')) { const r = e.getBoundingClientRect(); if (r.width && r.right > iw + 1) { let p = e.parentElement, sc = false; while (p && p.id !== 'ugContent') { const ox = getComputedStyle(p).overflowX; if (ox === 'auto' || ox === 'scroll') { sc = true; break; } p = p.parentElement; } if (!sc) bad.push(e.tagName.toLowerCase() + '.' + String(e.className).slice(0, 24) + ' r=' + Math.round(r.right)); } } return { iw, sw: document.documentElement.scrollWidth, bad: [...new Set(bad)].slice(0, 4), tocOpen: document.getElementById('ugTocBox').open }; })()`);
        t(`${name}: tanpa gulir mendatar dan tanpa elemen keluar layar`, o.sw <= o.iw + 1 && o.bad.length === 0, JSON.stringify(o));
        if (w < 1024) t(`${name}: daftar isi tertutup dulu (isi langsung terlihat), dapat dibuka`, o.tocOpen === false);
        await shot(`${w}-guide.png`);
        if (w < 1024) { await ev(`document.getElementById('ugTocBox').open = true`); await sleep(200); await shot(`${w}-guide-toc.png`); }
    }
    t('tidak ada galat JavaScript', jsErrors.length === 0, jsErrors.slice(0, 2).join(' | '));
} catch (e) { fail++; console.log('  [GAGAL] pengecualian: ' + e.message); }
finally { try { ws?.close(); } catch {} chrome.kill(); await sleep(300); try { rmSync(dir, { recursive: true, force: true }); } catch {} }
console.log(`\nHASIL: ${ok} OK, ${fail} GAGAL`);
process.exit(fail ? 1 : 0);
