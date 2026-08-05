<!doctype html><meta charset="utf-8"><body><pre id="out">работаю…</pre>
<script>
const L=[];
const say=m=>{L.push(m);document.getElementById('out').textContent=L.join('\n');};
const wait=ms=>new Promise(r=>setTimeout(r,ms));

async function post(url, obj){
  const fd=new FormData();
  Object.keys(obj||{}).forEach(k=>fd.append(k,obj[k]));
  const r=await fetch(url,{method:'POST',body:fd});
  const t=await r.text();
  try { return {status:r.status, json:JSON.parse(t)}; }
  catch(e){ return {status:r.status, html:t.slice(0,160)}; }
}

(async()=>{
  // Готовим тестового пользователя с признаком смены пароля
  say('=== подготовка ===');
  say('  ' + await (await fetch('pwdsetup.php?act=create')).text());

  // 1. Вход — должен вернуть must_change_password
  say('\n=== 1. Вход ===');
  let r = await post('/index.php?ajax=auth', {login:'_pwd_test', password:'StartPass1'});
  say('  success=' + r.json.success + ', must_change_password=' + r.json.must_change_password);

  // 2. Пока пароль не сменён, API закрыт
  say('\n=== 2. Доступ к API до смены ===');
  const probe = await fetch('/index.php?ajax=get_nodes_list');
  const probeTxt = await probe.text();
  say('  ?ajax=get_nodes_list → HTTP ' + probe.status + ' ' + probeTxt.slice(0,60));

  // 3. Страница отдаёт экран смены пароля, а не интерфейс
  say('\n=== 3. Страница до смены ===');
  const pg = await (await fetch('/?page=nodes')).text();
  say('  экран смены пароля: ' + (pg.indexOf('force-change-form') !== -1 ? 'да' : 'НЕТ'));
  say('  интерфейс приложения: ' + (pg.indexOf('sidebar-nav') !== -1 ? 'ПОКАЗАН (ошибка)' : 'скрыт'));

  // 4. Проверки на стороне сервера
  say('\n=== 4. Проверки смены ===');
  r = await post('/index.php?ajax=change_password',
      {current_password:'StartPass1', new_password:'abc', confirm_password:'abc'});
  say('  короткий пароль: ' + (r.json.error || 'принят (ошибка)'));

  r = await post('/index.php?ajax=change_password',
      {current_password:'StartPass1', new_password:'NewPass123', confirm_password:'Other123'});
  say('  разные пароли: ' + (r.json.error || 'принят (ошибка)'));

  r = await post('/index.php?ajax=change_password',
      {current_password:'НЕВЕРНЫЙ', new_password:'NewPass123', confirm_password:'NewPass123'});
  say('  неверный текущий: ' + (r.json.error || 'принят (ошибка)'));

  // 5. Успешная смена
  say('\n=== 5. Успешная смена ===');
  r = await post('/index.php?ajax=change_password',
      {current_password:'StartPass1', new_password:'NewPass123', confirm_password:'NewPass123'});
  say('  результат: ' + (r.json.success ? 'успех' : r.json.error));
  say('  ' + await (await fetch('pwdsetup.php?act=flag')).text());

  // 6. Теперь приложение доступно
  say('\n=== 6. После смены ===');
  const pg2 = await (await fetch('/?page=nodes')).text();
  say('  интерфейс: ' + (pg2.indexOf('sidebar-nav') !== -1 ? 'доступен' : 'НЕ доступен (ошибка)'));
  const api = await fetch('/index.php?ajax=get_nodes_list');
  say('  ?ajax=get_nodes_list → HTTP ' + api.status);

  // 7. Вход новым паролем
  say('\n=== 7. Вход новым паролем ===');
  await fetch('/?logout=1');
  r = await post('/index.php?ajax=auth', {login:'_pwd_test', password:'NewPass123'});
  say('  success=' + r.json.success + ', must_change_password=' + r.json.must_change_password);
  r = await post('/index.php?ajax=auth', {login:'_pwd_test', password:'StartPass1'});
  say('  старым паролем: ' + (r.json.success ? 'ПУСТИЛО (ошибка)' : r.json.error));

  // 8. Сайдбар: секция администрирования среди остальных
  say('\n=== 8. Сайдбар ===');
  await fetch('/?logout=1');
  await post('/index.php?ajax=auth', {login:'admin@netinfra', password:'pa$$w0rd'});
  const f=document.createElement('iframe');
  f.style.cssText='width:1400px;height:900px;position:absolute;left:-9999px';
  f.src='/?page=nodes'; document.body.appendChild(f);
  await new Promise(r=>f.onload=r); await wait(2200);
  const d=f.contentDocument;
  const inNav = Array.from(d.querySelectorAll('.sidebar-nav .nav-section')).map(s=>s.dataset.section);
  const inFooter = Array.from(d.querySelectorAll('.sidebar-footer .nav-section')).map(s=>s.dataset.section);
  say('  секции в навигации: ' + inNav.join(', '));
  say('  секции в подвале: ' + (inFooter.join(', ') || 'нет'));
  say('  подвал содержит: ' + Array.from(d.querySelectorAll('.sidebar-footer > *'))
        .map(x=>x.className||x.tagName).join(', '));

  // Уборка
  say('\n=== уборка ===');
  say('  ' + await (await fetch('pwdsetup.php?act=drop')).text());

  await fetch('pcsave.php',{method:'POST',body:L.join('\n')});
})();
</script></body>
