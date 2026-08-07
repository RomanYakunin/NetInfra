<!doctype html><meta charset="utf-8"><body><pre id="out">работаю…</pre>
<script>
const L=[]; const say=m=>{L.push(m);document.getElementById('out').textContent=L.join('\n');};
const wait=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
  const fd=new FormData();fd.append('login','admin@netinfra');fd.append('password','pa$$w0rd');
  if(!(await(await fetch('/index.php?ajax=auth',{method:'POST',body:fd})).json()).success){say('ЛОГИН НЕ УДАЛСЯ');return;}

  say('=== 1. Список устройств ===');
  const dev = await (await fetch('/index.php?ajax=scan_devices')).json();
  say('  success=' + dev.success + ', устройств=' + (dev.devices||[]).length);
  (dev.problems||[]).forEach(p => say('  проблема: ' + p));
  if (dev.hint) say('  подсказка: ' + dev.hint.slice(0,150));

  say('\n=== 2. Диагностика ===');
  const dg = await (await fetch('/index.php?ajax=scan_diagnose')).json();
  if (!dg.success) { say('  ошибка: ' + dg.error); }
  else {
    const d = dg.data;
    say('  сервер: ' + d.server_host + ', SAPI=' + d.php_sapi);
    say('  com_dotnet: ' + d.com.loaded);
    say('  WIA-сканеров: ' + (d.wia.devices||[]).length + (d.wia.error ? ' | ' + d.wia.error : ''));
    say('  NAPS2 доступен: ' + d.naps2.available + (d.naps2.path ? ' (' + d.naps2.path + ')' : ''));
    say('  TWAIN-источников: ' + (d.naps2.devices||[]).length);
    say('  служба WIA: ' + d.windows.wia_service);
    say('  принтеры: ' + (d.windows.printers||[]).length);
    (d.windows.printers||[]).forEach(p => say('    ' + p));
    say('  файлы TWAIN-источников: ' + (d.windows.twain_sources||[]).join(', '));
    say('  можно сканировать: ' + d.can_scan);
    say('  ВЫВОД:');
    (d.verdict||[]).forEach(v => say('    • ' + v));
  }

  say('\n=== 3. Кнопка «Отсканировать» в формах ===');
  const f=document.createElement('iframe');
  f.style.cssText='width:1500px;height:1000px;position:absolute;left:-9999px';
  f.src='/?page=phones'; document.body.appendChild(f);
  await new Promise(r=>f.onload=r); await wait(2600);
  const d2=f.contentDocument, w=f.contentWindow;
  d2.defaultView.addEventListener('error',e=>say('  ОШИБКА JS: '+e.message));

  // Форма добавления
  const addBtn = d2.getElementById('phAddBtn');
  say('  кнопка «Добавить телефон» (#phAddBtn): ' + (addBtn ? 'есть' : 'НЕТ'));
  if (addBtn) {
    addBtn.click(); await wait(1800);
    const scanBtn = d2.getElementById('phoneFormScanBtn');
    say('  в форме ДОБАВЛЕНИЯ кнопка «Отсканировать»: ' +
        (scanBtn ? (scanBtn.hidden ? 'скрыта (ошибка)' : 'видима') : 'НЕТ В РАЗМЕТКЕ'));
    say('  обработчик назначен: ' + (scanBtn && typeof scanBtn.onclick === 'function'));
    if (scanBtn && !scanBtn.hidden) {
      scanBtn.click(); await wait(1600);
      const m = d2.getElementById('scanModal');
      say('  окно сканирования открылось: ' + (m && m.classList.contains('visible')));
      say('  состояние: ' + (d2.getElementById('scanState').textContent||'').replace(/\s+/g,' ').trim().slice(0,120));
      say('  кнопка «Диагностика» в окне: ' + !!d2.getElementById('scanDiagBtn'));
      if (d2.getElementById('scanDiagBtn')) {
        d2.getElementById('scanDiagBtn').click(); await wait(3000);
        say('  диагностика в окне: ' + (d2.getElementById('scanState').textContent||'')
              .replace(/\s+/g,' ').trim().slice(0,200));
      }
      w.closeScanModal(); await wait(400);
    }
    if (typeof w.closePhoneForm === 'function') w.closePhoneForm();
    await wait(500);
  }

  await fetch('pcsave.php',{method:'POST',body:L.join('\n')});
})();
</script></body>
