(function(){
  document.addEventListener('DOMContentLoaded', function(){
    const contentRoot = document.querySelector('.content-area') || document;
    // Clear stale inline scroll locks from previous cached states/scripts.
    document.documentElement.style.overflowY = '';
    document.documentElement.style.height = '';
    document.body.style.overflow = '';
    document.body.style.overflowX = '';
    document.body.style.overflowY = '';
    document.body.style.position = '';
    document.body.style.height = '';
    document.body.style.minHeight = '';
    document.body.style.paddingRight = '';

    function initTooltips(root) {
      if (!(window.bootstrap && bootstrap.Tooltip) || !root || !root.querySelectorAll) return;
      root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el){
        bootstrap.Tooltip.getOrCreateInstance(el);
      });
    }

    function applyDesignSystem(root) {
      if (!root || !root.querySelectorAll) return;
      root.querySelectorAll('.card').forEach(function(el){
        el.classList.add('ads-ds-card');
      });
      root.querySelectorAll('table').forEach(function(el){
        if (!el.classList.contains('ads-table')) {
          el.classList.add('ads-ds-table');
        }
      });
      root.querySelectorAll('.modal').forEach(function(el){
        el.classList.add('ads-ds-modal');
      });
      root.querySelectorAll('.form-switch .form-check-input').forEach(function(el){
        el.classList.add('ads-action-toggle');
      });
      initTooltips(root);
    }

    // Auto-apply shared design-system classes so all pages stay visually consistent.
    applyDesignSystem(contentRoot);
    // Expose a tiny helper so pages with AJAX can request a DS re-apply.
    window.applyAdminDesignSystem = applyDesignSystem;
    document.addEventListener('ads:apply-design-system', function(ev){
      const root = ev && ev.detail && ev.detail.root ? ev.detail.root : document;
      applyDesignSystem(root);
    });

    // Safety-first: default to no live observer to avoid any chance of UI stalls.
    // Enable live observe only with explicit query flag: ?ads_observe=1
    const enableLiveObserve = (window.location.search || '').indexOf('ads_observe=1') >= 0;
    if (enableLiveObserve && window.MutationObserver) {
      // Batch mutations to avoid heavy synchronous work. Limit pending size
      // and prefer adding higher-level containers instead of raw nodes.
      const pending = new Set();
      let scheduled = false;
      const MAX_PENDING = 50;
      const scheduleApply = function(){
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(function(){
          scheduled = false;
          // convert to array to avoid mutation during iteration
          const items = Array.from(pending).slice(0, MAX_PENDING);
          items.forEach(function(el){ try{ applyDesignSystem(el); }catch(e){} });
          pending.clear();
        });
      };

      const observer = new MutationObserver(function(mutations){
        for (let i = 0; i < mutations.length; i++){
          const mutation = mutations[i];
          for (let j = 0; j < mutation.addedNodes.length; j++){
            const node = mutation.addedNodes[j];
            if (!(node instanceof Element)) continue;
            // prefer to process a nearby significant container to reduce work
            let container = node.closest('.content-area, .content, .card, section, article') || node.parentElement || node;
            pending.add(container);
            // Keep pending small to avoid large synchronous workloads
            if (pending.size > MAX_PENDING) break;
          }
          if (pending.size > MAX_PENDING) break;
        }
        if (pending.size) scheduleApply();
      });
      observer.observe(contentRoot, { childList: true, subtree: true });
    }

    const sidebarAccordion = document.getElementById('sidebarAccordion');
    if (sidebarAccordion && window.bootstrap && bootstrap.Collapse) {
      sidebarAccordion.querySelectorAll('.collapse').forEach(function(menuEl){
        menuEl.addEventListener('show.bs.collapse', function(){
          const parentLi = menuEl.closest('li');
          const siblingScope = parentLi && parentLi.parentElement ? parentLi.parentElement : null;
          if (!siblingScope) return;
          siblingScope.querySelectorAll(':scope > li > .collapse.show').forEach(function(openEl){
            if (openEl === menuEl) return;
            bootstrap.Collapse.getOrCreateInstance(openEl, { toggle: false }).hide();
          });
        });
      });
    }

    const sidebarEl = document.getElementById('sidebarOffcanvas');
    const sidebarBodyEl = sidebarEl ? sidebarEl.querySelector('.offcanvas-body') : null;
    const menuSearchEl = sidebarEl ? sidebarEl.querySelector('.menu-search-box') : null;

    function syncSidebarScrollRegion() {
      if (!sidebarBodyEl || !menuSearchEl || !sidebarAccordion) return;
      const bodyH = sidebarBodyEl.clientHeight;
      const searchH = menuSearchEl.offsetHeight;
      const avail = Math.max(120, bodyH - searchH);
      sidebarAccordion.style.height = `${avail}px`;
      sidebarAccordion.style.maxHeight = `${avail}px`;
      sidebarAccordion.style.overflowY = 'auto';
      sidebarAccordion.style.overflowX = 'hidden';
    }

    function unlockBodyIfNoOverlay(force) {
      const hasModal = !!document.querySelector('.modal.show');
      const hasOffcanvas = !!document.querySelector('.offcanvas.show');
      if (!force && (hasModal || hasOffcanvas)) return;
      document.body.classList.remove('offcanvas-open');
      if (!hasModal) {
        document.body.classList.remove('modal-open');
      }
      document.body.style.paddingRight = '';
      if (!hasModal) {
        document.body.style.overflowY = 'auto';
        document.body.style.overflowX = '';
        document.body.style.overflow = '';
        document.documentElement.style.overflow = '';
        document.documentElement.style.overflowY = 'auto';
        document.body.style.position = '';
        document.body.style.height = '';
        document.body.style.minHeight = '';
      }
      if (!hasOffcanvas) {
        document.querySelectorAll('.offcanvas-backdrop').forEach(function(el){ el.remove(); });
      }
    }

    syncSidebarScrollRegion();
    requestAnimationFrame(syncSidebarScrollRegion);
    setTimeout(syncSidebarScrollRegion, 120);

    let shellResizeTimer = null;
    const scheduleShellSync = function() {
      clearTimeout(shellResizeTimer);
      shellResizeTimer = setTimeout(syncSidebarScrollRegion, 80);
    };
    window.addEventListener('resize', scheduleShellSync);
    window.addEventListener('orientationchange', function() {
      setTimeout(syncSidebarScrollRegion, 120);
    });

    if (sidebarEl) {
      sidebarEl.addEventListener('shown.bs.offcanvas', function() {
        syncSidebarScrollRegion();
      });
      sidebarEl.addEventListener('hidden.bs.offcanvas', function() {
        unlockBodyIfNoOverlay(true);
        if (window.matchMedia('(max-width: 767.98px)').matches) {
          setTimeout(function(){
            unlockBodyIfNoOverlay(true);
          }, 60);
          setTimeout(function(){ unlockBodyIfNoOverlay(true); }, 220);
        }
        syncSidebarScrollRegion();
      });
    }

    // Safety net for any stale bootstrap lock state on mobile.
    if (window.matchMedia('(max-width: 767.98px)').matches) {
      unlockBodyIfNoOverlay();
    }

    if (window.bootstrap && bootstrap.Tooltip) {
      document.addEventListener('mouseover', function(ev){
        const trigger = ev.target instanceof Element ? ev.target.closest('[data-bs-toggle="tooltip"]') : null;
        if (!trigger) return;
        bootstrap.Tooltip.getOrCreateInstance(trigger);
      });
    }

    const searchInput = document.getElementById('menuQuickSearch');
    const menuRoot = document.getElementById('sidebarAccordion');
    if (!searchInput || !menuRoot) return;

    const labels = Array.from(menuRoot.querySelectorAll('.menu-label'));
    searchInput.addEventListener('input', function(){
      const q = (searchInput.value || '').trim().toLowerCase();
      labels.forEach(function(label){
        const btn = label.closest('.btn-menu');
        if (!btn) return;
        const text = (label.textContent || '').toLowerCase();
        const parentLi = btn.closest('li');
        if (!parentLi) return;
        if (q === '' || text.includes(q)) {
          parentLi.style.display = '';
        } else if (!btn.closest('.submenu')) {
          parentLi.style.display = 'none';
        }
      });
    });
  });
})();
