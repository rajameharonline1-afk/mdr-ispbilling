<?php
// partials/partials_footer.php
?>
  </main><!-- /main -->
</div><!-- /app-wrap -->

<!-- Bootstrap Bundle (JS + Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<style>
#app-toast-holder{
  position:fixed;
  top:1rem;
  right:1rem;
  z-index:2050;
  display:flex;
  flex-direction:column;
  gap:.5rem;
  pointer-events:none;
}
.app-toast{
  --toast-accent: #0d6efd;
  --toast-bg: rgba(255,255,255,.96);
  --toast-fg: #111827;
  --toast-muted: rgba(17,24,39,.75);
  --toast-shadow: 0 18px 45px rgba(0,0,0,.22);
  --toast-w: min(420px, calc(100vw - 2rem));
  --toast-duration: 3500ms;

  position: relative;
  left: auto;
  top: auto;
  width: var(--toast-w);
  border-radius: 14px;
  background: var(--toast-bg);
  color: var(--toast-fg);
  border: 1px solid rgba(0,0,0,.08);
  box-shadow: var(--toast-shadow);
  overflow: hidden;
  pointer-events:auto;

  transform: translate3d(115%, 0, 0);
  opacity: 0;
  transition: transform .42s cubic-bezier(.2,.9,.2,1), opacity .42s ease;
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
}
.app-toast.show{ transform: translate3d(0,0,0); opacity:1; }
.app-toast.hide{ transform: translate3d(115%,0,0); opacity:0; }

.app-toast__row{
  display:flex;
  gap:.75rem;
  padding:.85rem .9rem .8rem .9rem;
}
.app-toast__icon{
  width: 38px;
  height: 38px;
  border-radius: 11px;
  display:flex;
  align-items:center;
  justify-content:center;
  flex: 0 0 auto;
  background: color-mix(in srgb, var(--toast-accent) 12%, transparent);
  color: var(--toast-accent);
  border: 1px solid color-mix(in srgb, var(--toast-accent) 28%, transparent);
}
.app-toast__body{ flex: 1 1 auto; min-width: 0; }
.app-toast__title{
  font-weight: 700;
  font-size: .95rem;
  line-height: 1.1;
  margin: 0 0 .25rem 0;
}
.app-toast__msg{
  font-size: .93rem;
  line-height: 1.25rem;
  color: var(--toast-muted);
  margin:0;
  white-space: pre-wrap;
  word-break: break-word;
}
.app-toast__close{
  appearance:none;
  border:0;
  background: transparent;
  color: rgba(17,24,39,.65);
  width: 34px;
  height: 34px;
  border-radius: 10px;
  display:flex;
  align-items:center;
  justify-content:center;
  margin: .55rem .55rem 0 0;
  flex: 0 0 auto;
  cursor: pointer;
}
.app-toast__close:hover{ background: rgba(0,0,0,.06); color: rgba(17,24,39,.9); }
.app-toast__bar{
  height: 3px;
  width: 100%;
  background: color-mix(in srgb, var(--toast-accent) 28%, transparent);
  transform-origin: left;
  animation: toastbar var(--toast-duration) linear forwards;
}
.app-toast.pause .app-toast__bar{ animation-play-state: paused; }
@keyframes toastbar{ from{ transform: scaleX(1); } to { transform: scaleX(0); } }

.app-toast--success{ --toast-accent:#16a34a; }
.app-toast--info{ --toast-accent:#0ea5e9; }
.app-toast--warning{ --toast-accent:#f59e0b; }
.app-toast--danger{ --toast-accent:#ef4444; }

@media (prefers-reduced-motion: reduce){
  .app-toast{ transition:none; }
  .app-toast__bar{ animation:none; }
}
</style>
<script>
(function(){
  const ICONS = {
    success: '<svg viewBox="0 0 20 20" width="18" height="18" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.707-9.707a1 1 0 0 0-1.414-1.414L9 10.172 7.707 8.879a1 1 0 1 0-1.414 1.414l2 2a1 1 0 0 0 1.414 0l4-4Z" clip-rule="evenodd"/></svg>',
    info: '<svg viewBox="0 0 20 20" width="18" height="18" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM9 9a1 1 0 1 1 2 0v5a1 1 0 1 1-2 0V9Zm1-4a1.25 1.25 0 1 1 0 2.5A1.25 1.25 0 0 1 10 5Z" clip-rule="evenodd"/></svg>',
    warning: '<svg viewBox="0 0 20 20" width="18" height="18" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.721-1.36 3.486 0l6.518 11.59c.75 1.334-.214 2.99-1.743 2.99H3.482c-1.53 0-2.493-1.656-1.743-2.99l6.518-11.59ZM11 14a1 1 0 1 1-2 0 1 1 0 0 1 2 0Zm-1-8a1 1 0 0 0-1 1v4a1 1 0 1 0 2 0V7a1 1 0 0 0-1-1Z" clip-rule="evenodd"/></svg>',
    danger: '<svg viewBox="0 0 20 20" width="18" height="18" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8.707 7.293a1 1 0 0 0-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 1 0 1.414 1.414L10 11.414l1.293 1.293a1 1 0 0 0 1.414-1.414L11.414 10l1.293-1.293a1 1 0 0 0-1.414-1.414L10 8.586 8.707 7.293Z" clip-rule="evenodd"/></svg>',
  };

  function ensureHolder(){
    let holder=document.getElementById('app-toast-holder');
    if(!holder){
      holder=document.createElement('div');
      holder.id='app-toast-holder';
      holder.setAttribute('aria-live','polite');
      holder.setAttribute('aria-atomic','true');
      document.body.appendChild(holder);
    }
    return holder;
  }

  function normalizeType(type){
    type = String(type || 'info').toLowerCase();
    if (type === 'error') type = 'danger';
    if (!['success','info','warning','danger','primary'].includes(type)) type = 'info';
    if (type === 'primary') type = 'info';
    return type;
  }
  function defaultTitle(type){
    const t = normalizeType(type);
    if (t === 'success') return 'Success';
    if (t === 'warning') return 'Warning';
    if (t === 'danger')  return 'Error';
    return 'Info';
  }

  function globalToast(message, type='info', timeout=3500, title=null){
    const holder=ensureHolder();
    type = normalizeType(type);
    const el=document.createElement('div');
    el.className=`app-toast app-toast--${type}`;
    el.style.setProperty('--toast-duration', `${Math.max(1200, Number(timeout)||3500)}ms`);

    const row=document.createElement('div');
    row.className='app-toast__row';

    const icon=document.createElement('div');
    icon.className='app-toast__icon';
    icon.innerHTML = ICONS[type] || ICONS.info;

    const body=document.createElement('div');
    body.className='app-toast__body';
    const t=document.createElement('div');
    t.className='app-toast__title';
    t.textContent = title ? String(title) : defaultTitle(type);
    const msg=document.createElement('div');
    msg.className='app-toast__msg';
    msg.textContent = String(message ?? '');
    body.appendChild(t);
    body.appendChild(msg);

    const close=document.createElement('button');
    close.type='button';
    close.className='app-toast__close';
    close.setAttribute('aria-label','Close');
    close.innerHTML = '&times;';

    row.appendChild(icon);
    row.appendChild(body);
    row.appendChild(close);
    el.appendChild(row);

    const bar=document.createElement('div');
    bar.className='app-toast__bar';
    el.appendChild(bar);

    holder.appendChild(el);

    let hideTimer = null;
    let hidden = false;
    const hide = () => {
      if (hidden) return;
      hidden = true;
      el.classList.add('hide');
    };
    const startTimer = () => {
      clearTimeout(hideTimer);
      hideTimer = setTimeout(hide, Math.max(1200, Number(timeout)||3500));
    };

    requestAnimationFrame(()=>{ el.classList.add('show'); startTimer(); });

    el.addEventListener('mouseenter',()=>{ el.classList.add('pause'); clearTimeout(hideTimer); });
    el.addEventListener('mouseleave',()=>{ el.classList.remove('pause'); startTimer(); });
    close.addEventListener('click', hide);
    el.addEventListener('transitionend',()=>{ if(el.classList.contains('hide')) el.remove(); });
  }
  window.globalToast = globalToast;
  window.showToast = globalToast;

  // Replace blocking alerts with non-blocking toast (keeps native alert accessible)
  if (!window.__nativeAlert) window.__nativeAlert = window.alert?.bind(window);
  window.alert = function(msg){
    try{
      globalToast(String(msg ?? ''), 'warning', 5000, 'Notice');
    }catch(e){
      try{ window.__nativeAlert && window.__nativeAlert(msg); }catch(_){}
    }
  };
})();
</script>

<script>
/* Sidebar collapse on desktop, offcanvas on mobile */
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
      if (oc){ new bootstrap.Offcanvas(oc).show(); }
    });
  });
})();
</script>

<script>
(function(){
  const sidebar = document.getElementById('sidebarOffcanvas');
  if (!sidebar) return;
  sidebar.addEventListener('click', function(e){
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
    const instance = bootstrap.Offcanvas.getInstance(sidebar) || new bootstrap.Offcanvas(sidebar);
    instance.hide();
  });
})();
</script>


</body>
</html>


<?php include __DIR__ . '/../app/footer.php'; ?>
