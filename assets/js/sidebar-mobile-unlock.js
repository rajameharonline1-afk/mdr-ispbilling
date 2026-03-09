(function(){
  function isMobileViewport() {
    return window.matchMedia('(max-width: 767.98px)').matches;
  }

  function forceSidebarHiddenState(sidebar) {
    if (!sidebar) return;
    sidebar.classList.remove('showing', 'show', 'hiding');
    sidebar.setAttribute('aria-hidden', 'true');
    sidebar.removeAttribute('aria-modal');
    sidebar.style.visibility = '';
    sidebar.style.transform = '';
    sidebar.style.pointerEvents = '';
  }

  function forceMobileScrollUnlock(force){
    if (!isMobileViewport()) return;
    const hasModal = !!document.querySelector('.modal.show');
    const hasSidebarOpen = !!document.querySelector('#sidebarOffcanvas.offcanvas.show');
    // Keep modal behavior intact; sidebar open state must not block body scrolling.
    if (!force && hasModal) return;

    document.body.classList.remove('offcanvas-open');
    if (!hasModal) {
      document.body.classList.remove('modal-open');
    }
    document.body.style.paddingRight = '';
    if (!hasModal) {
      document.body.style.overflow = '';
      document.body.style.overflowX = '';
      document.body.style.overflowY = 'auto';
      document.documentElement.style.overflow = '';
      document.documentElement.style.overflowY = 'auto';
      document.body.style.position = '';
      document.body.style.height = '';
      document.body.style.minHeight = '';
    }
    // Remove stale backdrops. Sidebar uses scroll-enabled behavior in this app.
    document.querySelectorAll('.offcanvas-backdrop').forEach(function(el){ el.remove(); });

    // If sidebar is not explicitly open, ensure stale classes are cleared.
    var sidebar = document.getElementById('sidebarOffcanvas');
    if (sidebar && !hasSidebarOpen) {
      forceSidebarHiddenState(sidebar);
    }
  }

  const sidebar = document.getElementById('sidebarOffcanvas');
  if (!sidebar) {
    return;
  }

  document.addEventListener('hidden.bs.offcanvas', function(ev){
    if (ev.target && ev.target.id === 'sidebarOffcanvas') {
      forceSidebarHiddenState(sidebar);
      forceMobileScrollUnlock(true);
      setTimeout(function(){ forceMobileScrollUnlock(true); }, 80);
      setTimeout(function(){ forceMobileScrollUnlock(true); }, 220);
    }
  }, true);

  window.addEventListener('pageshow', function(){ forceMobileScrollUnlock(false); });
  document.addEventListener('visibilitychange', function(){
    if (!document.hidden) {
      forceMobileScrollUnlock(false);
    }
  });
  document.addEventListener('DOMContentLoaded', function(){ forceMobileScrollUnlock(true); });
  window.addEventListener('load', function(){ forceMobileScrollUnlock(true); });
  document.addEventListener('touchstart', function(){ forceMobileScrollUnlock(false); }, { passive: true });
  document.addEventListener('pointerdown', function(){ forceMobileScrollUnlock(false); }, { passive: true });

  // Startup safety retries: clear stale lock styles left by previous navigation.
  (function startupUnlockRetries(){
    var tries = 0;
    var maxTries = 10;
    var t = setInterval(function(){
      tries++;
      if (isMobileViewport() && sidebar && !sidebar.classList.contains('show')) {
        forceSidebarHiddenState(sidebar);
      }
      forceMobileScrollUnlock(true);
      if (tries >= maxTries) clearInterval(t);
    }, 250);
  })();

  const sidebarToggles = Array.from(document.querySelectorAll('[data-bs-target="#sidebarOffcanvas"]'));
  sidebarToggles.forEach(function(toggle){
    toggle.addEventListener('click', function(){
      if (!isMobileViewport()) return;
      // Clear any stale lock state before opening again.
      forceMobileScrollUnlock(true);
    });
  });

  sidebar.addEventListener('click', function(e){
    const dismiss = e.target.closest('[data-bs-dismiss="offcanvas"]');
    if (dismiss && isMobileViewport()) {
      setTimeout(function(){
        forceSidebarHiddenState(sidebar);
        forceMobileScrollUnlock(true);
      }, 30);
      setTimeout(function(){
        forceSidebarHiddenState(sidebar);
        forceMobileScrollUnlock(true);
      }, 180);
    }

    const link = e.target.closest('a');
    if (!link) return;
    if (link.getAttribute('data-bs-toggle') === 'collapse') return;
    const href = (link.getAttribute('href') || '').trim();
    if (href === '' || href === '#' || href.toLowerCase().startsWith('javascript:')) {
      e.preventDefault();
      return;
    }
    if (!window.matchMedia('(max-width: 767.98px)').matches) return;
    if (!window.bootstrap || !bootstrap.Offcanvas) return;
    const instance = bootstrap.Offcanvas.getOrCreateInstance(sidebar, { backdrop: false, scroll: true });
    instance.hide();
    setTimeout(function(){
      forceSidebarHiddenState(sidebar);
      forceMobileScrollUnlock(true);
    }, 380);
  });
})();
