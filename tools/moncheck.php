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

  // --- 1. Интеграция выключена ---
  let f=await page('/?page=monitoring','панель-выкл');
  let d=f.contentDocument;
  found.push('СТРАНИЦА: заголовков колонок='+d.querySelectorAll('.mon-table thead th').length
    +', плиток='+d.querySelectorAll('.mon-tile').length);
  found.push('ПЛИТКИ: '+Array.from(d.querySelectorAll('.mon-tile-label')).map(l=>l.textContent).join(', '));
  const st=d.getElementById('monState');
  found.push('ВЫКЛЮЧЕНА: блок виден='+(st.style.display!=='none')
    +', класс='+st.className
    +', текст="'+st.textContent.replace(/\s+/g,' ').trim().slice(0,110)+'"');
  found.push('ТАБЛИЦА: '+d.getElementById('monTableBody').textContent.trim());
  found.push('САЙДБАР: пункт «Панель»='+(Array.from(d.querySelectorAll('.nav-item')).some(a=>a.textContent.includes('Панель')))
    +', активен='+(d.querySelector('.nav-item.active')||{}).textContent?.trim());

  // --- 2. Включаем на заглушке ---
  await fetch('zabbix_switch.php?on=1');
  f=await page('/?page=monitoring','панель-вкл');
  d=f.contentDocument;
  const w=f.contentWindow;
  await wait(1200);
  found.push('ВКЛЮЧЕНА: блок состояния скрыт='+(d.getElementById('monState').style.display==='none')
    +', строк='+d.querySelectorAll('#monTableBody tr[data-sev]').length);
  const row=d.querySelector('#monTableBody tr[data-sev]');
  if(row) found.push('СТРОКА: '+Array.from(row.children).map(c=>c.textContent.replace(/\s+/g,' ').trim()).join(' | '));
  found.push('СЧЁТЧИКИ: '+Array.from(d.querySelectorAll('.mon-tile')).map(t=>
      t.querySelector('.mon-tile-label').textContent+'='+t.querySelector('.mon-tile-count').textContent).join(', '));
  found.push('ПОДВАЛ: '+d.getElementById('monFooter').textContent.trim());
  found.push('ОБНОВЛЕНО: '+d.getElementById('monUpdated').textContent.trim());

  // Фильтр по важности. Плитки перерисовываются при каждой загрузке,
  // поэтому после клика элемент нужно искать заново
  const tileBySev=sev=>Array.from(d.querySelectorAll('.mon-tile')).find(t=>t.dataset.severity===sev);
  tileBySev('3').click(); await wait(1600);
  found.push('ФИЛЬТР «Средняя»: активна='+tileBySev('3').classList.contains('active')
    +', строк='+d.querySelectorAll('#monTableBody tr[data-sev]').length
    +', важности в таблице='+Array.from(d.querySelectorAll('#monTableBody tr[data-sev]')).map(r=>r.dataset.sev).join(','));
  tileBySev('3').click(); await wait(1600);
  found.push('ФИЛЬТР снят: строк='+d.querySelectorAll('#monTableBody tr[data-sev]').length);

  // Только неподтверждённые
  const unack=d.getElementById('monUnackOnly');
  unack.checked=true; unack.dispatchEvent(new w.Event('change'));
  await wait(1600);
  found.push('ТОЛЬКО НЕПОДТВЕРЖДЁННЫЕ: строк='+d.querySelectorAll('#monTableBody tr[data-sev]').length
    +', подтверждения='+Array.from(d.querySelectorAll('.mon-ack')).map(a=>a.textContent).join(','));
  unack.checked=false; unack.dispatchEvent(new w.Event('change')); await wait(1600);

  // Сопоставление с нашим оборудованием
  found.push('СОПОСТАВЛЕНИЕ: ссылок в досье='+d.querySelectorAll('.mon-link[data-equipment]').length
    +', «нет в учёте»='+d.querySelectorAll('.mon-nomatch').length
    +', узлы='+Array.from(d.querySelectorAll('.mon-host')).map(h=>h.textContent.trim()).join(' | '));
  found.push('ДЛИТЕЛЬНОСТИ: '+Array.from(d.querySelectorAll('.mon-duration')).map(x=>x.textContent).join(' | '));
  found.push('ТЕГИ: '+Array.from(d.querySelectorAll('.mon-tag')).map(x=>x.textContent).join(' | '));

  // Поиск
  const s=d.getElementById('monSearch');
  s.value='несуществующая'; s.dispatchEvent(new w.Event('input'));
  await wait(1600);
  found.push('ПОИСК без совпадений: '+d.getElementById('monTableBody').textContent.trim());
  s.value=''; s.dispatchEvent(new w.Event('input')); await wait(1600);

  // Диагностика
  const diag=await (await w.fetch('?ajax=zabbix_ping')).json();
  found.push('ДИАГНОСТИКА: success='+diag.success+', версия='+diag.data.version
    +', узлов='+diag.data.hosts+', проблем='+diag.data.problems+', ошибка="'+diag.data.error+'"');

  await fetch('zabbix_switch.php?on=0');

  const txt=found.join(String.fromCharCode(10));
  await fetch('pcsave.php',{method:'POST',body:txt});
  document.getElementById('out').textContent=txt;
})();
</script></body>
