<?php
// Временная страница финальной браузерной проверки. Удалить после аудита.
?><!DOCTYPE html>
<html lang="ru"><head><meta charset="utf-8"><title>final</title></head>
<body><pre id="out">запуск…</pre>
<iframe id="fr" style="width:1600px;height:1040px;border:1px solid #ccc"></iframe>
<script>
const LOG = []; let PROBLEMS = 0;
const say = m => { LOG.push(m); document.getElementById('out').textContent = LOG.join('\n'); };
const bad = m => { PROBLEMS++; say('  [ПРОБЛЕМА] ' + m); };
const sleep = ms => new Promise(r => setTimeout(r, ms));

function hookErrors(win, page) {
    win.addEventListener('error', e => bad('JS ' + page + ': ' + e.message));
    win.addEventListener('unhandledrejection', e => bad('PROMISE ' + page + ': ' + (e.reason && e.reason.message || e.reason)));
    ['error','warn'].forEach(l => {
        const o = win.console[l];
        win.console[l] = function(){ bad('console.'+l+' '+page+': '+Array.from(arguments).map(String).join(' ').slice(0,200)); o.apply(win.console, arguments); };
    });
}
function loadPage(url, page) {
    return new Promise(res => {
        const fr = document.getElementById('fr');
        fr.onload = () => { const w = fr.contentWindow; hookErrors(w, page); w.confirm = () => true;
                            w.alert = m => say('  [alert] '+m); res(w.document); };
        fr.src = url;
    });
}
const $ = (d,s) => d.querySelector(s);
const n = (d,s) => d.querySelectorAll(s).length;
const expect = (l,g,w) => { if(String(g)===String(w)) say('  ✓ '+l+': '+g); else bad(l+': '+g+', ждали '+w); };

function api(d, path, data) {
    if (!data) return d.defaultView.fetch(path).then(r => r.json());
    const f = data instanceof d.defaultView.FormData ? data : (() => {
        const x = new d.defaultView.FormData();
        Object.keys(data).forEach(k => x.append(k, data[k]));
        return x;
    })();
    return d.defaultView.fetch(path, { method:'POST', body:f }).then(r => r.json());
}
function makePdf(t) {
    return new Blob([`%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF ${t}`],
        { type: 'application/pdf' });
}
function setFile(d, inputId, name, blob) {
    const dt = new d.defaultView.DataTransfer();
    dt.items.add(new d.defaultView.File([blob], name, { type: blob.type }));
    const input = d.getElementById(inputId);
    input.files = dt.files;
    input.dispatchEvent(new d.defaultView.Event('change', { bubbles: true }));
}

async function run() {
    const fd = new FormData();
    fd.append('login','_audit_tmp'); fd.append('password','AuditTmp12345');
    if (!(await (await fetch('api/auth.php',{method:'POST',body:fd})).json()).success) { say('вход не удался'); return send(); }

    // ---------- Все страницы ----------
    say('=== 1. Все страницы ===');
    for (const p of ['nodes','dashboard','warehouse','checklist','phones','journal','users','database_manager']) {
        const doc = await loadPage('index.php?page='+p, p);
        await sleep(1700);
        const h1 = $(doc, '.header h1');
        say('  ' + p + ' → ' + (h1 ? h1.textContent.trim() : '[БЕЗ ЗАГОЛОВКА]'));
        if (!h1) bad('нет заголовка на ' + p);
    }

    // ---------- Накладная прямо в форме телефона ----------
    say('\n=== 2. Накладная при добавлении телефона ===');
    const d = await loadPage('index.php?page=phones', 'phones');
    await sleep(2600);

    // Коробка на 2 аппарата: накладная одна на обоих
    const refs = await api(d, '?ajax=get_phone_refs');
    const modelId = refs.phone_models[0].id;
    const del = await api(d, '?ajax=add_delivery', {
        delivery_date:'2026-07-28', doc_number:'ФИН-ДОК-2',
        item_type:'phone', box_model_id:modelId, box_count:1, qty_per_box:3
    });
    const dl = await api(d, '?ajax=get_deliveries');
    const delivery = dl.deliveries.find(x => x.doc_number === 'ФИН-ДОК-2');
    const boxId = delivery.boxes[0].id;
    await api(d, '?ajax=get_phone_refs');   // обновим справочники в интерфейсе

    d.getElementById('phAddBtn').click();
    await sleep(2200);

    expect('блок накладной в форме', d.getElementById('phoneFormDocsBlock') ? 'есть':'нет', 'есть');
    expect('поля документа скрыты до выбора файла',
        d.getElementById('phoneFormDocFields').hidden ? 'да':'нет', 'да');

    d.getElementById('phoneFormSerial').value = 'FORM-SN-1';
    d.getElementById('phoneFormNumber').value = '9701';
    const mSel = d.getElementById('phoneFormModel');
    mSel.value = modelId;
    mSel.dispatchEvent(new d.defaultView.Event('change', {bubbles:true}));
    const bSel = d.getElementById('phoneFormBox');
    bSel.value = boxId;
    bSel.dispatchEvent(new d.defaultView.Event('change', {bubbles:true}));
    await sleep(500);
    expect('«ко всей коробке» появилось после выбора коробки',
        d.getElementById('phoneFormDocWholeBoxWrap').hidden ? 'нет':'да', 'да');

    setFile(d, 'phoneFormDocFile', 'Накладная формы.pdf', makePdf('form'));
    await sleep(500);
    expect('имя файла показано', d.getElementById('phoneFormDocName').textContent.trim(), 'Накладная формы.pdf');
    expect('поля документа раскрылись',
        d.getElementById('phoneFormDocFields').hidden ? 'нет':'да', 'да');

    d.getElementById('phoneFormDocNumber').value = 'НК-99';
    d.getElementById('phoneFormDocWholeBox').checked = true;

    d.getElementById('phoneForm').dispatchEvent(
        new d.defaultView.Event('submit', {bubbles:true, cancelable:true}));
    await sleep(3200);

    const err = d.getElementById('phoneFormError');
    say('  ошибка формы: ' + (err && err.style.display !== 'none' ? err.textContent.trim() : 'нет'));

    const created = await api(d, '?ajax=get_phones&search=FORM-SN-1&per_page=5');
    expect('телефон создан', created.total, 1);
    const phId = created.data[0].id;
    const docs = await api(d, '?ajax=get_phone_documents&phone_id=' + phId);
    expect('накладная прикреплена', docs.data.length, 1);
    expect('привязана к коробке', docs.data[0].link_kind, 'box');
    say('  документ: ' + docs.data[0].original_name + ' · № ' + (docs.data[0].doc_number||'—'));

    // ---------- Накладная при редактировании ----------
    say('\n=== 3. Накладная при редактировании ===');
    const s = d.getElementById('phSearch');
    s.value = 'FORM-SN-1';
    s.dispatchEvent(new d.defaultView.Event('input', {bubbles:true}));
    await sleep(1600);
    const row = $(d, '#phTableBody tr[data-id]');
    const r = row.getBoundingClientRect();
    const o = { bubbles:true, cancelable:true, clientX:r.left+120, clientY:r.top+10, button:0 };
    row.dispatchEvent(new d.defaultView.MouseEvent('mousedown', o));
    row.dispatchEvent(new d.defaultView.MouseEvent('mouseup', o));
    row.dispatchEvent(new d.defaultView.MouseEvent('click', o));
    await sleep(700);
    const editItem = d.defaultView.document.querySelector('.ph-menu-item[data-act="edit"]');
    expect('пункт «Редактировать» в меню', editItem ? 'есть':'нет', 'есть');
    editItem.click();
    await sleep(2600);

    expect('уже прикреплённые показаны', n(d, '#phoneFormDocsList .ph-doc-chip'), 1);
    say('  метка: ' + $(d, '#phoneFormDocsList .ph-doc-chip').textContent.replace(/\s+/g,' ').trim());

    setFile(d, 'phoneFormDocFile', 'Расписка формы.pdf', makePdf('raspiska'));
    await sleep(400);
    d.getElementById('phoneFormDocType').value = 'расписка';
    d.getElementById('phoneFormDocWholeBox').checked = false;
    d.getElementById('phoneForm').dispatchEvent(
        new d.defaultView.Event('submit', {bubbles:true, cancelable:true}));
    await sleep(3200);

    const docs2 = await api(d, '?ajax=get_phone_documents&phone_id=' + phId);
    expect('документов стало', docs2.data.length, 2);

    // ---------- Удаление документа из карточки ----------
    say('\n=== 4. Удаление документа из карточки ===');
    row.dispatchEvent(new d.defaultView.MouseEvent('mousedown', o));
    row.dispatchEvent(new d.defaultView.MouseEvent('mouseup', o));
    row.dispatchEvent(new d.defaultView.MouseEvent('click', o));
    await sleep(700);
    const detItem = d.defaultView.document.querySelector('.ph-menu-item[data-act="detail"]');
    detItem.click();
    await sleep(2400);
    expect('документов в карточке', n(d, '#phoneDetailBody .ph-doc'), 2);

    // Личная расписка — удаляется без вопросов
    const personal = Array.from(d.querySelectorAll('#phoneDetailBody .ph-doc'))
        .find(x => x.dataset.link === 'phone');
    expect('личный документ найден', personal ? 'да':'нет', 'да');
    $(personal, '[data-act="del"]').click();
    await sleep(2600);
    say('  после удаления документов: ' + n(d, '#phoneDetailBody .ph-doc'));
    expect('расписка удалена без ошибки', n(d, '#phoneDetailBody .ph-doc'), 1);

    // Коробочная — тоже должна удаляться, но от коробки
    const boxDoc = Array.from(d.querySelectorAll('#phoneDetailBody .ph-doc'))
        .find(x => x.dataset.link === 'box');
    expect('коробочный документ найден', boxDoc ? 'да':'нет', 'да');
    $(boxDoc, '[data-act="del"]').click();
    await sleep(2600);
    expect('накладная откреплена от коробки', n(d, '#phoneDetailBody .ph-doc'), 0);
    d.defaultView.closePhoneDetail(); await sleep(500);

    // ---------- Уборка ----------
    say('\n=== 5. Уборка ===');
    await api(d, '?ajax=delete_phone', { id: phId });
    await api(d, '?ajax=delete_box', { id: boxId });
    const rd = await api(d, '?ajax=delete_delivery', { id: delivery.id });
    say('  поставка удалена: ' + (rd.success ? 'да' : rd.error));

    say('\n' + '─'.repeat(48));
    say(PROBLEMS === 0 ? 'ПРОБЛЕМ НЕ НАЙДЕНО' : 'НАЙДЕНО ПРОБЛЕМ: ' + PROBLEMS);
    await send();
}
async function send(){ await fetch('_pcsave.php',{method:'POST',body:LOG.join('\n')}); document.title='DONE'; }
run().catch(async e => { say('ФАТАЛЬНО: '+e.message+'\n'+e.stack); await send(); });
</script>
</body></html>
