// Global JS for tooltips, toggles and simple modal behavior
(function(){
  function q(sel, ctx){ return (ctx||document).querySelector(sel); }
  function qa(sel, ctx){ return Array.from((ctx||document).querySelectorAll(sel)); }

  // Tooltip: toggles class 'show' when hovered/focused
  qa('[data-tooltip]').forEach(function(el){
    el.addEventListener('mouseenter', function(){ el.classList.add('show'); });
    el.addEventListener('mouseleave', function(){ el.classList.remove('show'); });
    el.addEventListener('focus', function(){ el.classList.add('show'); });
    el.addEventListener('blur', function(){ el.classList.remove('show'); });
  });

  // Toggle: elements with data-toggle-target="#id"
  qa('[data-toggle-target]').forEach(function(btn){
    btn.addEventListener('click', function(e){
      e.preventDefault();
      var sel = btn.getAttribute('data-toggle-target');
      if(!sel) return;
      var target = document.querySelector(sel);
      if(!target) return;
      target.classList.toggle('on');
      btn.classList.toggle('on');
    });
  });

  // Modal open/close via data-modal-target and data-modal-close
  qa('[data-modal-target]').forEach(function(el){
    el.addEventListener('click', function(e){
      e.preventDefault();
      var sel = el.getAttribute('data-modal-target');
      var modal = document.querySelector(sel);
      if(!modal) return;
      // Safety: don't accidentally move <html> or <body> or very large containers
      if (modal === document.body || modal === document.documentElement) return;

      var backdrop = document.createElement('div');
      backdrop.className = 'modal-backdrop';
      var modalWrap = document.createElement('div');
      modalWrap.className = 'modal';

      // Clone modal inner content into modalWrap instead of moving nodes.
      // This avoids unintentional removal of page nodes and prevents heavy
      // reflows caused by relocating large DOM subtrees.
      try {
        modalWrap.innerHTML = modal.innerHTML;
      } catch (err) {
        // Fallback: lightweight cloning of child nodes
        Array.from(modal.childNodes).forEach(function(ch){ modalWrap.appendChild(ch.cloneNode(true)); });
      }

      backdrop.appendChild(modalWrap);
      document.body.appendChild(backdrop);

      // Close when clicking backdrop (only if click target is backdrop)
      backdrop.addEventListener('click', function(ev){ if(ev.target === backdrop && backdrop.parentNode) backdrop.parentNode.removeChild(backdrop); });

      // Bind close buttons inside the cloned modal
      qa('[data-modal-close]', modalWrap).forEach(function(c){ c.addEventListener('click', function(){ if(backdrop.parentNode) backdrop.parentNode.removeChild(backdrop); }); });
    });
  });
})();
