(function(){var d=document,B=d.body,C=B.dataset.csrf,$=function(s,r){return (r||d).querySelector(s)},$$=function(s,r){return Array.prototype.slice.call((r||d).querySelectorAll(s))};
var A=window.A={toast:function(m){var t=d.createElement('div');t.textContent=m;$('#toast').appendChild(t);setTimeout(function(){t.remove()},3500)},
modal:function(id){var m=d.getElementById(id);if(!m)return;m.classList.add('on');var s=$('#init-scope');if(s){var n=A.sel().length;s.textContent=n?n+' lead(s) selected.':'Select leads in the table first.'}},
close:function(){$$('.md.on').forEach(function(m){m.classList.remove('on')})},
sel:function(){return $$('input[name="ids[]"]:checked')},
count:function(){var c=$('#selc');if(c)c.textContent=A.sel().length},
pick:function(st){$$('tr[data-stage]').forEach(function(r){var c=$('input[name="ids[]"]',r);if(c)c.checked=r.dataset.stage===st});A.count()},
bulk:function(a,q){if(!A.sel().length){A.toast('Select at least one lead first');return}if(q&&!confirm(q))return;$('#bulk-a').value=a;$('#bulk').submit()},
post:function(o){var f=new FormData();f.append('_csrf',C);for(var k in o)f.append(k,o[k]);fetch(location.href,{method:'POST',body:f,credentials:'same-origin'}).then(function(){setTimeout(function(){location.reload()},1500)})},
copy:function(t){(navigator.clipboard?navigator.clipboard.writeText(t):Promise.reject()).then(function(){A.toast('Copied')},function(){prompt('Copy:',t)})}};
d.addEventListener('change',function(e){var t=e.target;if(t.matches('[data-all]')){$$('input[name="ids[]"]').forEach(function(c){c.checked=t.checked})}if(t.matches('[data-all],input[name="ids[]"]'))A.count()});
d.addEventListener('click',function(e){if(e.target.classList.contains('md'))A.close();$$('.dd.open').forEach(function(x){if(!x.contains(e.target))x.classList.remove('open')})});
d.addEventListener('keydown',function(e){if(e.key==='Escape')A.close()});
var q=$('#qbox'),qt=$('#qtxt'),busy=false,idle=0;
function tick(){if(busy||!q)return;busy=true;var f=new FormData();f.append('_csrf',C);fetch('index.php?r=api&do=tick',{method:'POST',body:f,credentials:'same-origin',headers:{'X-CSRF':C}}).then(function(r){return r.json()}).then(function(s){busy=false;var w=s.due+s.running;if(w>0){q.hidden=false;qt.textContent='Processing '+w+' task'+(w>1?'s':'')+'…';idle=0;setTimeout(tick,600)}else if(s.later>0){q.hidden=false;qt.textContent=s.later+' scheduled'+(s.next?' · next in '+(s.next>90?Math.round(s.next/60)+'m':s.next+'s'):'');setTimeout(tick,Math.min(60,Math.max(3,s.next||10))*1000)}else{q.hidden=true;idle++}}).catch(function(){busy=false;setTimeout(tick,15000)})}
if(q)tick();
var L=$('#live[data-live]');
if(L){var last='',stop=0;(function lv(){setTimeout(function(){var ch=A.sel().map(function(c){return c.value});fetch(L.dataset.live,{credentials:'same-origin'}).then(function(r){return r.text()}).then(function(h){if(h!==last){last=h;L.innerHTML=h;ch.forEach(function(v){var c=$('input[name="ids[]"][value="'+v+'"]',L);if(c)c.checked=true});A.count();stop=0}else stop++;if(stop<6||!q.hidden)lv()})},4000)})()}
var R=$('[data-reload]');if(R)setTimeout(function(){if(!$('.md.on')&&!(d.activeElement&&/INPUT|TEXTAREA|SELECT/.test(d.activeElement.tagName)))location.reload();else setTimeout(function(){location.reload()},8000)},R.dataset.reload*1000);
})();
