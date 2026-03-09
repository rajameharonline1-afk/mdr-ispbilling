(function(){
  const KEY='sb-collapsed-v2';
  const mq = window.matchMedia('(min-width: 768px)');
  const apply = (collapsed) => {
    document.body.classList.toggle('sb-collapsed', collapsed);
  };

  document.addEventListener('DOMContentLoaded', function(){
    if (mq.matches) {
      apply(false);
      try{ localStorage.removeItem(KEY); }catch(e){}
    } else {
      const saved = localStorage.getItem(KEY);
      apply(saved === '1');
    }

    const btn = document.getElementById('btnSidebarToggle');
    if (!btn) return;

    btn.addEventListener('click', function(){
      if (mq.matches) return;
      const oc = document.getElementById('sidebarOffcanvas');
      if (oc){ bootstrap.Offcanvas.getOrCreateInstance(oc, { backdrop: false, scroll: true }).show(); }
    });
  });
})();
