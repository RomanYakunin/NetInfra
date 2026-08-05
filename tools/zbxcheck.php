<!doctype html><meta charset="utf-8"><body><pre id="out">работаю…</pre>
<script>
const found=[];
function hook(w,l){
  w.addEventListener('error',e=>found.push('ОШИБКА '+l+': '+e.message+' @'+(e.filename||'').split('/').pop()+':'+e.lineno));
  w.addEventListener('unhandledrejection',e=>found.push('ОШИБКА '+l+': promise '+(e.reason&&e.reason.message||e.reason)));
  const ce=w.console.error; w.console.error=function(){found.push('ОШИБКА '+l+': console.error '+Array.from(arguments).join(' '));ce.apply(w.console,arguments);};
}
const wait=ms=>new Promise(r=>setTimeout(r,ms));
async function page(url,label){
  const f=document.createElement('iframe');
  f.style.cssText='width:1500px;height:1000px;position:absolute;left:-9999px';
  f.src=url; document.body.appendChild(f);
  await new Promise(r=>f.onload=r);
  hook(f.contentWindow,label);
  await wait(2600);
  return f;
}
(async()=>{
  const fd=new FormData();fd.append('login','admin@netinfra');fd.append('password','pa$$w0rd');
  const j=await(await fetch('/index.php?ajax=auth',{method:'POST',body:fd})).json();
  if(!j.success){document.getElementById('out').textContent='ЛОГИН НЕ УДАЛСЯ';return;}

  // Все страницы должны грузиться после переноса helpers.php
  for (const p of ['nodes','warehouse','checklist','dashboard','database_manager','users','journal','monitoring']) {
    const f=await page('/?page='+p, p);
    const d=f.contentDocument;
    found.push('СТРАНИЦА '+p+': заголовок='+(d.querySelector('.page-title,h1,h2')||{}).textContent?.trim().slice(0,40)
      +', блоков контента='+d.querySelectorAll('#content > *').length);
    f.remove();
  }

  // Включаем заглушку Zabbix
  found.push('ПЕРЕКЛЮЧЕНИЕ на заглушку: '+await (await fetch('zabbix_switch.php?on=1')).text());

  // --- Чек-лист: автозадача ---
  const f=await page('/?page=checklist','чек-лист');
  const d=f.contentDocument, w=f.contentWindow;
  await wait(2000);
  const task=d.getElementById('zabbixTask');
  found.push('АВТОЗАДАЧА: блок есть='+!!task
    +', видима='+(task&&task.style.display!=='none')
    +', заголовок='+(d.querySelector('.auto-task-title')||{}).textContent
    +', счётчик='+(d.getElementById('zabbixTaskCount')||{}).textContent
    +', примечание='+(d.getElementById('zabbixTaskNote')||{}).textContent);

  d.getElementById('zabbixTaskHead').click();
  await wait(1800);
  found.push('РАСКРЫТИЕ: тело видимо='+(d.getElementById('zabbixTaskBody').style.display!=='none')
    +', стрелка='+(d.querySelector('.auto-task-arrow')||{}).textContent
    +', строк в списке='+d.querySelectorAll('.auto-task-table tbody tr').length);
  const head=Array.from(d.querySelectorAll('.auto-task-table thead th')).map(t=>t.textContent);
  found.push('КОЛОНКИ: '+head.join(' | '));
  const r1=d.querySelector('.auto-task-table tbody tr');
  if(r1) found.push('СТРОКА 1: '+Array.from(r1.children).map(c=>c.textContent.trim()).join(' | '));
  found.push('ОБРАТНАЯ СВЕРКА: подзаголовков='+d.querySelectorAll('.auto-task-sub').length
    +', «есть в Zabbix, нет у нас»='+d.querySelectorAll('.auto-task-list li').length);

  d.getElementById('zabbixTaskHead').click(); await wait(600);
  found.push('СВОРАЧИВАНИЕ: тело скрыто='+(d.getElementById('zabbixTaskBody').style.display==='none'));

  // Сырые данные сверки
  const raw=await (await w.fetch('?ajax=zabbix_missing')).json();
  found.push('API zabbix_missing: success='+raw.success
    +(raw.success ? (', не в мониторинге='+raw.missing_count
        +', под мониторингом='+raw.covered
        +', без имени и адреса='+raw.without_ip
        +', узлов в Zabbix='+raw.zabbix_hosts
        +', нет у нас в учёте='+(raw.unknown_hosts||[]).length)
      : ', ошибка="'+raw.error+'"'));

  // Настройки подключения
  const st=await (await w.fetch('?ajax=zabbix_settings')).json();
  found.push('НАСТРОЙКИ: success='+st.success+', url='+st.data.url+', user='+st.data.user
    +', пароль задан='+st.data.has_password+', файл доступен для записи='+st.data.writable);

  await fetch('zabbix_switch.php?on=0');

  const txt=found.join(String.fromCharCode(10));
  await fetch('pcsave.php',{method:'POST',body:txt});
  document.getElementById('out').textContent=txt;
})();
</script></body>
