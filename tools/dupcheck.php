<!doctype html><meta charset="utf-8"><body><pre id="out">работаю…</pre>
<script>
const L=[]; const say=m=>{L.push(m);document.getElementById('out').textContent=L.join('\n');};
const wait=ms=>new Promise(r=>setTimeout(r,ms));
const chk=async(field,value,id)=>await (await fetch('/index.php?ajax=check_phone_duplicate&field='
    +encodeURIComponent(field)+'&value='+encodeURIComponent(value)+(id?'&id='+id:''))).json();
(async()=>{
  const fd=new FormData();fd.append('login','admin@netinfra');fd.append('password','pa$$w0rd');
  if(!(await(await fetch('/index.php?ajax=auth',{method:'POST',body:fd})).json()).success){say('ЛОГИН НЕ УДАЛСЯ');return;}

  say('  ' + await (await fetch('dupsetup.php?act=create')).text());
  const info = await (await fetch('dupsetup.php?act=info')).json();
  say('  тестовый телефон id=' + info.id + ' с/н=' + info.serial + ' mac=' + info.mac
      + ' номер=' + info.number);

  say('\n=== 1. Занятые значения ===');
  for (const [f,v] of [['serial_number',info.serial],['mac_address',info.mac],['phone_number',info.number]]) {
    const r = await chk(f, v);
    say('  ' + f + ': занято=' + r.taken + ', строгость=' + r.severity + ' | ' + (r.message||''));
  }

  say('\n=== 2. Свободные значения ===');
  for (const [f,v] of [['serial_number','НЕТ-ТАКОГО-999'],['mac_address','aaaa.bbbb.cccc'],['phone_number','99999']]) {
    const r = await chk(f, v);
    say('  ' + f + ' = ' + v + ': занято=' + r.taken);
  }

  say('\n=== 3. Исключение себя при редактировании ===');
  let r = await chk('serial_number', info.serial, info.id);
  say('  свой серийник при своём id: занято=' + r.taken + ' (должно быть false)');
  r = await chk('serial_number', info.serial, info.id + 100000);
  say('  свой серийник при чужом id: занято=' + r.taken + ' (должно быть true)');

  say('\n=== 4. Непроверяемые поля и пустое значение ===');
  r = await chk('user_fio', 'Иванов');
  say('  user_fio: checked=' + r.checked + ' (проверять не должны)');
  r = await chk('notes', 'что угодно');
  say('  notes: checked=' + r.checked);
  r = await chk('serial_number', '');
  say('  пустой серийник: checked=' + r.checked + ', занято=' + r.taken);

  say('\n=== 5. Сверка с оборудованием ===');
  const eq = await (await fetch('dupsetup.php?act=equip')).json();
  say('  оборудование: mac=' + eq.mac + ' ip=' + eq.ip + ' host=' + eq.hostname);
  if (eq.mac) { r = await chk('mac_address', eq.mac);
    say('  MAC оборудования: занято=' + r.taken + ', строгость=' + r.severity + ' | ' + (r.message||'')); }
  if (eq.ip) { r = await chk('ip_address', eq.ip);
    say('  IP оборудования: занято=' + r.taken + ', строгость=' + r.severity + ' | ' + (r.message||'')); }

  say('\n=== 6. Поведение формы ===');
  const f=document.createElement('iframe');
  f.style.cssText='width:1500px;height:1000px;position:absolute;left:-9999px';
  f.src='/?page=phones'; document.body.appendChild(f);
  await new Promise(r2=>f.onload=r2); await wait(2600);
  const d=f.contentDocument, w=f.contentWindow;
  w.addEventListener('error',e=>say('  ОШИБКА JS: '+e.message));

  d.getElementById('phAddBtn').click(); await wait(1800);

  // Занятый серийник → красная подсветка и блокировка сохранения
  const ser = d.getElementById('phoneFormSerial');
  ser.value = info.serial;
  ser.dispatchEvent(new w.Event('input',{bubbles:true}));
  await wait(1400);
  say('  серийник занят: класс=' + ser.className
      + ', подсказка=«' + (d.getElementById('phoneFormSerialDupHint')||{}).textContent + '»');

  d.getElementById('phoneForm').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
  await wait(1200);
  say('  попытка сохранить: ошибка=«' + (d.getElementById('phoneFormError')||{}).textContent + '»');

  // Освобождаем — подсветка должна уйти
  ser.value = 'СВОБОДНЫЙ-12345';
  ser.dispatchEvent(new w.Event('input',{bubbles:true}));
  await wait(1400);
  say('  после смены на свободный: класс=«' + ser.className
      + '», подсказка=' + (d.getElementById('phoneFormSerialDupHint') ? 'осталась' : 'убрана'));

  // Мягкий дубль по номеру → жёлтый, сохранение не блокируется
  const num = d.getElementById('phoneFormNumber');
  num.value = info.number;
  num.dispatchEvent(new w.Event('input',{bubbles:true}));
  await wait(1400);
  say('  номер занят: класс=' + num.className
      + ', подсказка=«' + (d.getElementById('phoneFormNumberDupHint')||{}).textContent + '»');

  w.closePhoneForm && w.closePhoneForm();
  say('\n  ' + await (await fetch('dupsetup.php?act=drop')).text());
  await fetch('pcsave.php',{method:'POST',body:L.join('\n')});
})();
</script></body>
