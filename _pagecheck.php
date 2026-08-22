<!doctype html><meta charset="utf-8"><body><pre id="out">работаю…</pre>
<script>
const found=[];
function hook(w,l){
  w.addEventListener('error',e=>found.push('ОШИБКА '+l+': '+e.message+' @'+(e.filename||'').split('/').pop()+':'+e.lineno));
  w.addEventListener('unhandledrejection',e=>found.push('ОШИБКА '+l+': promise '+(e.reason&&e.reason.message||e.reason)));
  const ce=w.console.error; w.console.error=function(){found.push('ОШИБКА '+l+': console.error '+Array.from(arguments).join(' '));ce.apply(w.console,arguments);};
}
const wait=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
  const fd=new FormData();fd.append('login','admin@netinfra');fd.append('password','pa$$w0rd');
  const j=await(await fetch('/index.php?ajax=auth',{method:'POST',body:fd})).json();
  if(!j.success){document.getElementById('out').textContent='ЛОГИН НЕ УДАЛСЯ';return;}

  const f=document.createElement('iframe');
  f.style.cssText='width:1700px;height:1100px;position:absolute;left:-9999px';
  f.src='/?page=nodes';
  document.body.appendChild(f);
  await new Promise(r=>f.onload=r);
  const w=f.contentWindow,d=f.contentDocument;
  hook(w,'узлы');
  await wait(2400);

  try{
    // ---------- Сайдбар ----------
    const secs=d.querySelectorAll('.nav-section[data-section]');
    found.push('САЙДБАР: секций='+secs.length
      +', ключи='+Array.from(secs).map(s=>s.dataset.section).join(',')
      +', заголовки-кнопки='+d.querySelectorAll('button.nav-section-title').length
      +', стрелок='+d.querySelectorAll('.section-chevron').length);
    const inv=d.querySelector('.nav-section[data-section="inventory"]');
    inv.querySelector('.nav-section-title').click(); await wait(300);
    found.push('СВОРАЧИВАНИЕ: inventory collapsed='+inv.classList.contains('collapsed')
      +', aria='+inv.querySelector('.nav-section-title').getAttribute('aria-expanded')
      +', в localStorage='+w.localStorage.getItem('netinfra-sidebar-sections'));
    inv.querySelector('.nav-section-title').click(); await wait(300);
    found.push('РАЗВОРАЧИВАНИЕ: collapsed='+inv.classList.contains('collapsed')
      +', в localStorage='+w.localStorage.getItem('netinfra-sidebar-sections'));
    // Активная секция не должна быть свёрнута
    const mon=d.querySelector('.nav-section[data-section="monitoring"]');
    found.push('АКТИВНАЯ СЕКЦИЯ (узлы): collapsed='+mon.classList.contains('collapsed')
      +', активный пункт='+(mon.querySelector('.nav-item.active')||{}).textContent?.trim());

    // ---------- Окно стойки ----------
    found.push('ЭКСПОРТ: openRackPanel='+typeof w.openRackPanel
      +', openRackPanelForNode='+typeof w.openRackPanelForNode
      +', closeRackPanel='+typeof w.closeRackPanel);

    await w.openRackPanelForNode(9);
    await wait(2200);
    const modal=d.getElementById('rightPanel');
    found.push('ОКНО: класс='+modal.className+', видимо='+modal.classList.contains('visible')
      +', заголовок='+d.getElementById('panelTitle').textContent);
    found.push('РАМА: крышка="'+(d.querySelector('.rack-frame-cap')||{}).textContent
      +'", ножек='+d.querySelectorAll('.rack-frame-feet span').length
      +', .rack-inner='+d.querySelectorAll('.rack-inner').length
      +', вкладок шкафов='+d.querySelectorAll('.rack-tab').length);
    found.push('СТОЙКА: юнитов='+d.querySelectorAll('.rack-unit-no').length
      +', свободных='+d.querySelectorAll('.rack-slot.empty').length
      +', устройств='+d.querySelectorAll('.rack-device').length
      +', панелей='+d.querySelectorAll('.rack-device.is-panel').length
      +', ручек resize='+d.querySelectorAll('.rack-resize-handle').length);

    // Слот в стеке
    const slotBadges=Array.from(d.querySelectorAll('.rack-badge.slot')).map(b=>b.textContent);
    found.push('СЛОТЫ В СТЕКЕ: значки='+(slotBadges.join(' | ')||'НЕТ')
      +', строк состава='+d.querySelectorAll('.rack-device-slots').length);

    // Патч-панель
    const face=d.querySelector('.rack-panel-face');
    if(face){
      found.push('ПАНЕЛЬ: класс='+face.className
        +', ушей='+face.querySelectorAll('.rack-panel-ear').length
        +', рядов='+face.querySelectorAll('.rack-panel-row').length
        +', групп='+face.querySelectorAll('.rack-port-group').length
        +', полос='+face.querySelectorAll('.rack-port-strip').length
        +', портов='+face.querySelectorAll('.rack-port').length
        +', номеров='+face.querySelectorAll('.rack-port-num').length);
      const nums=Array.from(face.querySelectorAll('.rack-port-num')).map(n=>n.textContent);
      found.push('НОМЕРА ПОРТОВ: '+nums.slice(0,8).join(',')+' … '+nums.slice(-3).join(','));
    } else found.push('ПАНЕЛЬ: не найдена');
    d.querySelectorAll('.rack-panel-face.optic').forEach((optic,i)=>{
      const groups=optic.querySelectorAll('.rack-port-group');
      found.push('ОПТИКА #'+(i+1)+': секций='+groups.length
        +', адаптеров='+optic.querySelectorAll('.rack-port').length
        +', заглушек='+optic.querySelectorAll('.rack-port-blank').length
        +', винтов='+optic.querySelectorAll('.rack-panel-screw').length
        +', рядов='+optic.querySelectorAll('.rack-panel-row').length
        +', метки='+Array.from(optic.querySelectorAll('.rack-section-label')).map(l=>l.textContent).join('')
        +', по секциям=['+Array.from(groups).map(g=>g.querySelectorAll('.rack-port').length+'/'+g.querySelectorAll('.rack-port-blank').length).join(' ')+']');
    });

    // Геометрия: юнит должен быть ~30px, устройство на 2U — вдвое выше
    const u1=d.querySelector('.rack-slot');
    const dev=d.querySelector('.rack-device');
    if(u1&&dev) found.push('ГЕОМЕТРИЯ: юнит='+Math.round(u1.getBoundingClientRect().height)
      +'px, первое устройство='+Math.round(dev.getBoundingClientRect().height)
      +'px, размер='+dev.dataset.unitSize+'U');

    // Ширина окна не должна выходить за экран
    const mc=modal.querySelector('.modal-content').getBoundingClientRect();
    found.push('РАЗМЕР ОКНА: '+Math.round(mc.width)+'×'+Math.round(mc.height)
      +' при вьюпорте '+w.innerWidth+'×'+w.innerHeight);

    // Закрытие
    d.getElementById('panelClose').click(); await wait(400);
    found.push('ЗАКРЫТИЕ крестиком: видимо='+modal.classList.contains('visible'));

    // Открытие по оборудованию с подсветкой
    await w.openRackPanel(41); await wait(2000);
    found.push('ПО ОБОРУДОВАНИЮ: видимо='+modal.classList.contains('visible')
      +', подсвечено='+d.querySelectorAll('.rack-device.highlight').length
      +', заголовок='+d.getElementById('panelTitle').textContent);

    // Escape
    d.dispatchEvent(new w.KeyboardEvent('keydown',{key:'Escape',bubbles:true}));
    await wait(400);
    found.push('ЗАКРЫТИЕ по Escape: видимо='+modal.classList.contains('visible'));
  }catch(e){ found.push('ИСКЛЮЧЕНИЕ: '+e.message+' | '+(e.stack||'').split('\n')[1]); }

  const txt=found.join(String.fromCharCode(10));
  await fetch('/_pcsave.php',{method:'POST',body:txt});
  document.getElementById('out').textContent=txt;
})();
</script></body>
