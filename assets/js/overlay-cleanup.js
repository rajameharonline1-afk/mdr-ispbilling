(function(){
  function cleanup(force){
    try{
      const modalVisible = !!document.querySelector('.modal.show');
      const offcanvasVisible = !!document.querySelector('.offcanvas.show');

      // If a modal/offcanvas is actively visible and we're not forcing cleanup,
      // leave them alone so we don't interfere with normal Bootstrap behavior.
      if (!force && (modalVisible || offcanvasVisible)) return;

      // Remove stale backdrops only when there is no corresponding visible pane.
      document.querySelectorAll('.modal-backdrop').forEach(function(el){ if (!modalVisible) el.remove(); });
      document.querySelectorAll('.offcanvas-backdrop').forEach(function(el){ if (!offcanvasVisible) el.remove(); });

      // Reset body/page locks/styles that prevent interaction or scrolling
      document.body.classList.remove('offcanvas-open','modal-open');
      document.body.style.paddingRight = '';
      document.body.style.overflow = '';
      document.body.style.overflowX = '';
      document.body.style.overflowY = '';
      document.documentElement.style.overflow = '';
      document.documentElement.style.overflowY = '';
      document.body.style.position = '';
      document.body.style.height = '';
      document.body.style.minHeight = '';

      var sb = document.getElementById('sidebarOffcanvas');
      if (sb) {
        sb.classList.remove('showing','show','hiding');
        sb.setAttribute('aria-hidden','true');
        sb.removeAttribute('aria-modal');
        sb.style.visibility=''; sb.style.transform=''; sb.style.pointerEvents='';
      }
    }catch(e){}
  }

  document.addEventListener('hidden.bs.offcanvas', function(){ cleanup(true); }, true);
  document.addEventListener('hidden.bs.modal', function(){ cleanup(true); }, true);
  window.addEventListener('pageshow', function(){ cleanup(false); });
  document.addEventListener('visibilitychange', function(){ if (!document.hidden) cleanup(false); });
  document.addEventListener('touchstart', function(){ cleanup(false); }, { passive: true });
  document.addEventListener('pointerdown', function(){ cleanup(false); }, { passive: true });
  
  // Watch for body attribute/class/style changes and re-run cleanup if needed
  try{
    const obs = new MutationObserver(function(muts){
      muts.forEach(function(m){
        if (m.type === 'attributes' && (m.attributeName === 'class' || m.attributeName === 'style')) {
          cleanup(false);
        }
      });
    });
    obs.observe(document.body, { attributes: true, attributeFilter: ['class','style'] });
  }catch(e){}

  // Interval fallback: attempt a few times after page load to clear stale locks
  (function(){
    var tries = 0; var maxTries = 8;
    var t = setInterval(function(){ tries++; cleanup(false); if (tries >= maxTries) clearInterval(t); }, 300);
  })();
})();
