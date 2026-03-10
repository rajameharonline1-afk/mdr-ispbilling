(function(){
  const KEY='sb-collapsed-v2';
  const mq = window.matchMedia('(min-width: 768px)');
  const apply = (collapsed) => {
    document.body.classList.toggle('sb-collapsed', collapsed);
  };

  const readSaved = () => {
    try {
      return localStorage.getItem(KEY) === '1';
    } catch (e) {
      return false;
    }
  };

  const saveState = (collapsed) => {
    try {
      localStorage.setItem(KEY, collapsed ? '1' : '0');
    } catch (e) {
      // ignore storage write failures
    }
  };

  document.addEventListener('DOMContentLoaded', function(){
    apply(readSaved());

    mq.addEventListener('change', function(){
      // Keep persisted collapse preference when switching between viewports.
      apply(readSaved());
    });

    const toggles = Array.from(document.querySelectorAll('#btnSidebarToggle, #btnSidebarToggleDesktop'));
    if (!toggles.length) return;

    toggles.forEach(function(btn){
      btn.addEventListener('click', function(ev){
        if (mq.matches) {
          ev.preventDefault();
          const next = !document.body.classList.contains('sb-collapsed');
          apply(next);
          saveState(next);
          return;
        }

        const oc = document.getElementById('sidebarOffcanvas');
        if (oc && window.bootstrap && bootstrap.Offcanvas) {
          bootstrap.Offcanvas.getOrCreateInstance(oc, { backdrop: false, scroll: true }).show();
        }
      });
    });
  });
})();
