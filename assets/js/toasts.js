(function(){
  'use strict';
  function ensureHolder(){
    var holder = document.getElementById('app-toast-holder');
    if (!holder){
      holder = document.createElement('div');
      holder.id = 'app-toast-holder';
      holder.style.position = 'fixed';
      holder.style.top = '1rem';
      holder.style.right = '1rem';
      holder.style.zIndex = 2050;
      holder.style.display = 'flex';
      holder.style.flexDirection = 'column';
      holder.style.gap = '.5rem';
      holder.style.pointerEvents = 'none';
      document.body.appendChild(holder);
    }
    return holder;
  }

  function makeToast(message, type){
    var holder = ensureHolder();
    var map = { success:'success', error:'danger', danger:'danger', warning:'warning', info:'info', primary:'primary' };
    var variant = map[type] || 'primary';
    var el = document.createElement('div');
    el.className = 'app-toast alert alert-' + variant;
    el.setAttribute('role','alert');
    el.style.pointerEvents = 'auto';

    var row = document.createElement('div'); row.className = 'app-toast__row';
    var icon = document.createElement('div'); icon.className = 'app-toast__icon';
    icon.innerHTML = '<i class="bi bi-bell-fill"></i>';
    var body = document.createElement('div'); body.className = 'app-toast__body';
    var title = document.createElement('div'); title.className = 'app-toast__title'; title.textContent = '';
    var msg = document.createElement('div'); msg.className = 'app-toast__msg'; msg.textContent = message || '';
    body.appendChild(title); body.appendChild(msg);
    var close = document.createElement('button'); close.className = 'app-toast__close'; close.innerHTML = '<i class="bi bi-x-lg"></i>';
    close.addEventListener('click', function(){ el.classList.add('hide'); });
    var bar = document.createElement('div'); bar.className = 'app-toast__bar';

    row.appendChild(icon); row.appendChild(body); row.appendChild(close);
    el.appendChild(row); el.appendChild(bar);
    holder.appendChild(el);

    // show animation
    requestAnimationFrame(function(){ el.classList.add('show'); });

    var hideAfter = 3500;
    var hideTimer = setTimeout(function(){ el.classList.add('hide'); }, hideAfter);

    el.addEventListener('mouseenter', function(){ clearTimeout(hideTimer); el.classList.remove('hide'); });
    el.addEventListener('mouseleave', function(){ hideTimer = setTimeout(function(){ el.classList.add('hide'); }, 800); });
    el.addEventListener('transitionend', function(ev){ if (el.classList.contains('hide')) { try{ el.remove(); }catch(e){} } });

    return el;
  }

  function globalToast(message, type){ return makeToast(message, type); }
  function showToast(message, type){ return globalToast(message, type); }

  window.globalToast = globalToast;
  window.showToast = showToast;
})();
