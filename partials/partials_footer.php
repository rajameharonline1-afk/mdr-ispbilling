<?php
// partials/partials_footer.php
?>
  </main><!-- /main -->
</div><!-- /layout -->

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
<script src="/assets/js/toasts.js" defer></script>

<script src="/assets/js/sidebar-toggle.js" defer></script>

<script src="/assets/js/sidebar-mobile-unlock.js" defer></script>
<script src="/assets/js/design-system-apply.js" defer></script>
<?php
  $dbg_mode = isset($_GET['dbg']) && (string)$_GET['dbg'] === '1';
?>
<?php if ($dbg_mode): ?>
  <script src="/assets/js/overlay-debug.js" defer></script>
<?php endif; ?>
</body>
</html>


<?php include __DIR__ . '/../app/footer.php'; ?>
