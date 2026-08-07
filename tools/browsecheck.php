<!doctype html><meta charset="utf-8"><body><pre id="out">работаю…</pre>
<script>
const L=[]; const say=m=>{L.push(m);document.getElementById('out').textContent=L.join('\n');};
const wait=ms=>new Promise(r=>setTimeout(r,ms));
const api=async(p)=>await (await fetch('/index.php?ajax=storage_browse&'+p)).json();
(async()=>{
  const fd=new FormData();fd.append('login','admin@netinfra');fd.append('password','pa$$w0rd');
  if(!(await(await fetch('/index.php?ajax=auth',{method:'POST',body:fd})).json()).success){say('ЛОГИН НЕ УДАЛСЯ');return;}

  say('=== 1. Список дисков ===');
  let r = await api('path=');
  say('  success=' + r.success + ', корень=' + r.is_root + ', дисков=' + (r.drives||[]).length);
  say('  диски: ' + (r.drives||[]).map(d=>d.name+(d.writable?'':' (только чтение)')).join(', '));

  say('\n=== 2. Переход в каталог ===');
  r = await api('path=' + encodeURIComponent('C:/Temp'));
  say('  путь=' + r.path + ', родитель=' + r.parent + ', папок=' + (r.folders||[]).length
      + ', запись=' + r.writable);
  say('  первые: ' + (r.folders||[]).slice(0,5).map(f=>f.name).join(', '));

  say('\n=== 3. Разные виды пути ===');
  for (const p of ['C:\\Temp', 'C:/Temp/', 'C://Temp']) {
    const x = await api('path=' + encodeURIComponent(p));
    say('  «' + p + '» → ' + (x.success ? x.path : 'ошибка: ' + x.error));
  }

  say('\n=== 4. Родитель ===');
  for (const p of ['C:/Temp/claude', 'C:/Temp', 'C:/', '//server/share', '//server/share/sub']) {
    const x = await api('path=' + encodeURIComponent(p));
    say('  «' + p + '» → родитель=' + (x.success ? x.parent : 'недоступен'));
  }

  say('\n=== 5. Несуществующий и недоступный ===');
  r = await api('path=' + encodeURIComponent('C:/такого-нет-12345'));
  say('  несуществующий: success=' + r.success + ' | ' + (r.error||'').slice(0,90));
  r = await api('path=' + encodeURIComponent('//нет-такого-сервера/share'));
  say('  сетевой недоступный: success=' + r.success + ' | ' + (r.error||'').slice(0,90));

  say('\n=== 6. Создание папки ===');
  const base = 'C:/Temp/netinfra_fb_test';
  const fd2 = new FormData(); fd2.append('name', 'netinfra_fb_test');
  let m = await (await fetch('/index.php?ajax=storage_browse&action=mkdir&path='
        + encodeURIComponent('C:/Temp'), {method:'POST', body:fd2})).json();
  say('  создание: success=' + m.success + ', путь=' + m.path);
  m = await (await fetch('/index.php?ajax=storage_browse&action=mkdir&path='
        + encodeURIComponent('C:/Temp'), {method:'POST', body:fd2})).json();
  say('  повторно: success=' + m.success + ', уже была=' + m.existed);

  say('\n=== 7. Защита имени папки ===');
  for (const bad of ['..', '../выход', 'a/b', 'C:\\x', '']) {
    const f = new FormData(); f.append('name', bad);
    const x = await (await fetch('/index.php?ajax=storage_browse&action=mkdir&path='
          + encodeURIComponent('C:/Temp'), {method:'POST', body:f})).json();
    say('  «' + bad + '» → ' + (x.success ? 'СОЗДАНО (ошибка!)' : x.error));
  }

  say('\n=== 8. Окно в интерфейсе ===');
  const f3=document.createElement('iframe');
  f3.style.cssText='width:1500px;height:1000px;position:absolute;left:-9999px';
  f3.src='/?page=phones'; document.body.appendChild(f3);
  await new Promise(r=>f3.onload=r); await wait(2600);
  const d=f3.contentDocument, w=f3.contentWindow;
  w.addEventListener('error',e=>say('  ОШИБКА JS: '+e.message));

  say('  кнопка «⚙ Хранилище»: ' + !!d.getElementById('phStorageBtn'));
  w.openStorageSettings(); await wait(1500);
  say('  кнопка «Обзор…»: ' + !!d.getElementById('stBrowseBtn'));
  d.getElementById('stBrowseBtn').click(); await wait(1800);
  const fbm = d.getElementById('folderBrowserModal');
  say('  окно обзора открылось: ' + (fbm && fbm.classList.contains('visible')));
  say('  элементов в списке: ' + d.querySelectorAll('#fbList .fb-item').length);
  say('  путь в строке: «' + d.getElementById('fbPath').value + '»');
  say('  кнопка выбора заблокирована на дисках: ' + d.getElementById('fbSelectBtn').disabled);

  // Заходим в первый диск
  const first = d.querySelector('#fbList .fb-item');
  if (first) {
    first.click(); await wait(1800);
    say('  после клика по диску: путь=«' + d.getElementById('fbPath').value
        + '», элементов=' + d.querySelectorAll('#fbList .fb-item').length
        + ', выбор доступен=' + !d.getElementById('fbSelectBtn').disabled);
    d.getElementById('fbSelectBtn').click(); await wait(600);
    say('  после «Выбрать»: окно закрыто=' + !fbm.classList.contains('visible')
        + ', поле пути=«' + d.getElementById('stDocsRoot').value + '»');
  }

  // Уборка
  await (await fetch('/tools/browsecleanup.php')).text();
  await fetch('pcsave.php',{method:'POST',body:L.join('\n')});
})();
</script></body>
