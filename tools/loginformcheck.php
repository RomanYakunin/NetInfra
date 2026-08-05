<!doctype html><meta charset="utf-8"><body><pre id="out">работаю…</pre>
<script>
const L=[]; const say=m=>{L.push(m);document.getElementById('out').textContent=L.join('\n');};
const wait=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
  say('  ' + await (await fetch('pwdsetup.php?act=create')).text());
  await fetch('/?logout=1');

  const f=document.createElement('iframe');
  f.style.cssText='width:900px;height:800px;position:absolute;left:-9999px';
  f.src='/'; document.body.appendChild(f);
  await new Promise(r=>f.onload=r); await wait(900);
  let d=f.contentDocument, w=f.contentWindow;
  d.addEventListener('error',e=>say('  ОШИБКА JS: '+e.message));

  say('\n=== шаг 1: форма входа ===');
  say('  форма входа видима: ' + (d.getElementById('login-form').style.display !== 'none'));
  say('  форма смены скрыта: ' + (d.getElementById('change-form').style.display === 'none'));
  say('  заголовок: ' + d.getElementById('login-title').textContent);

  d.querySelector('#login-form [name=login]').value = '_pwd_test';
  d.querySelector('#login-form [name=password]').value = 'StartPass1';
  d.getElementById('login-form').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
  await wait(1800);

  say('\n=== шаг 2: смена пароля ===');
  say('  форма входа скрыта: ' + (d.getElementById('login-form').style.display === 'none'));
  say('  форма смены видима: ' + (d.getElementById('change-form').style.display !== 'none'));
  say('  заголовок: ' + d.getElementById('login-title').textContent);
  say('  подсказка: ' + (d.querySelector('.login-hint')||{}).textContent?.trim().slice(0,70));
  say('  поля: ' + Array.from(d.querySelectorAll('#change-form input'))
        .map(i=>i.previousElementSibling.textContent).join(', '));

  // Несовпадающие пароли — сообщение без запроса на сервер
  d.getElementById('new-password').value = 'NewPass123';
  d.getElementById('confirm-password').value = 'Other999';
  d.getElementById('change-form').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
  await wait(700);
  say('  при разных паролях: ' + d.getElementById('login-error').textContent);

  d.getElementById('confirm-password').value = 'ab';
  d.getElementById('new-password').value = 'ab';
  d.getElementById('change-form').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
  await wait(700);
  say('  при коротком пароле: ' + d.getElementById('login-error').textContent);

  d.getElementById('new-password').value = 'StartPass1';
  d.getElementById('confirm-password').value = 'StartPass1';
  d.getElementById('change-form').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
  await wait(700);
  say('  при совпадении со старым: ' + d.getElementById('login-error').textContent);

  // Успешная смена
  d.getElementById('new-password').value = 'FormPass456';
  d.getElementById('confirm-password').value = 'FormPass456';
  d.getElementById('change-form').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));
  await wait(2500);

  say('\n=== после смены ===');
  d = f.contentDocument;
  say('  в приложении: ' + (d.querySelector('.sidebar-nav') ? 'да' : 'нет'));
  say('  ' + await (await fetch('pwdsetup.php?act=flag')).text());

  say('\n  ' + await (await fetch('pwdsetup.php?act=drop')).text());
  await fetch('pcsave.php',{method:'POST',body:L.join('\n')});
})();
</script></body>
