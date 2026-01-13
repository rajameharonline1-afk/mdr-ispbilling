// (বাংলা) OLT MAC Table পেজের JS আলাদা ফাইলে রাখা হলো

(() => {
  const buttons = document.querySelectorAll('.telnet-refresh-btn');
  if (!buttons.length) return;
  const statusEl = document.getElementById('telnetRefreshStatus');
  function resetAfter(btn, originalHtml) {
    btn.disabled = false;
    btn.innerHTML = originalHtml;
    setTimeout(() => { if (statusEl) statusEl.textContent = ''; }, 7000);
  }
  async function handleClick(e) {
    const btn = e.currentTarget;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Refreshing…';
    if (statusEl) statusEl.textContent = 'রিফ্রেশ চলছে…';
    try {
      const resp = await fetch(btn.dataset.url, { credentials: 'same-origin' });
      const data = await resp.json();
      if (data.ok) {
        const seen = data.seen ?? 0;
        if (statusEl) statusEl.textContent = 'রিফ্রেশ সম্পন্ন। নতুন ডেটা: ' + seen + ' MAC। পৃষ্ঠা আপডেট হচ্ছে…';
        setTimeout(() => window.location.reload(), 1200);
      } else {
        if (statusEl) statusEl.textContent = data.error || 'রিফ্রেশ ব্যর্থ হয়েছে।';
        resetAfter(btn, originalHtml);
      }
    } catch (err) {
      if (statusEl) statusEl.textContent = 'রিফ্রেশ ব্যর্থ: ' + err.message;
      resetAfter(btn, originalHtml);
    }
  }
  buttons.forEach(btn => btn.addEventListener('click', handleClick));
})();

(() => {
  const form = document.getElementById('clientCodeSearchForm');
  const input = document.getElementById('clientCodeInput');
  if (!form || !input) return;
  let timer = null;
  let lastSubmitted = input.value.trim();
  const submit = () => {
    const val = input.value.trim();
    if (val === lastSubmitted) return;
    lastSubmitted = val;
    if (typeof form.requestSubmit === 'function') {
      form.requestSubmit();
    } else {
      form.submit();
    }
  };
  input.addEventListener('input', () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(submit, 400);
  });
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      if (timer) clearTimeout(timer);
      submit();
    }
  });
})();

(() => {
  const ensureHolder = () => {
    let holder = document.getElementById('app-toast-holder');
    if (!holder) {
      holder = document.createElement('div');
      holder.id = 'app-toast-holder';
      document.body.appendChild(holder);
    }
    return holder;
  };
  const showSavingToast = () => {
    const existing = document.getElementById('saving-toast');
    if (existing) return existing;
    const holder = ensureHolder();
    const el = document.createElement('div');
    el.id = 'saving-toast';
    el.className = 'app-toast app-toast--info show';
    const row = document.createElement('div');
    row.className = 'app-toast__row';
    const icon = document.createElement('div');
    icon.className = 'app-toast__icon';
    icon.textContent = '⏳';
    const body = document.createElement('div');
    body.className = 'app-toast__body';
    const t = document.createElement('div');
    t.className = 'app-toast__title';
    t.textContent = 'Saving';
    const msg = document.createElement('div');
    msg.className = 'app-toast__msg';
    msg.textContent = 'Please wait...';
    body.appendChild(t);
    body.appendChild(msg);
    row.appendChild(icon);
    row.appendChild(body);
    el.appendChild(row);
    holder.appendChild(el);
    return el;
  };
  const hideSavingToast = () => {
    const el = document.getElementById('saving-toast');
    if (!el) return;
    el.classList.add('hide');
    el.addEventListener('transitionend', () => { el.remove(); }, { once: true });
  };

  const modalEl = document.getElementById('onuConfigModal');
  const modalForm = document.getElementById('onuConfigForm');
  const rowIdEl = document.getElementById('cfgRowId');
  const descEl = document.getElementById('cfgDesc');
  let modalInstance = null;
  const ensureModal = () => {
    if (!modalEl || !window.bootstrap) return null;
    if (!modalInstance) modalInstance = new bootstrap.Modal(modalEl);
    return modalInstance;
  };
  document.querySelectorAll('.btn-config').forEach(btn => {
    btn.addEventListener('click', () => {
      if (!rowIdEl || !descEl) return;
      rowIdEl.value = btn.getAttribute('data-row-id') || '';
      descEl.value = btn.getAttribute('data-desc') || '';
      const m = ensureModal();
      if (m) m.show();
    });
  });
  if (modalForm) {
    modalForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      showSavingToast();
      const data = new FormData(modalForm);
      try {
        const res = await fetch(window.location.href, { method: 'POST', body: data, credentials: 'same-origin' });
        const json = await res.json();
        hideSavingToast();
        if (json && json.ok) {
          if (window.showToast) showToast(json.message || 'Saved', 'success', 3000);
          const rid = rowIdEl ? rowIdEl.value : '';
          if (rid) {
            const cell = document.querySelector(`.desc-text[data-row-id=\"${rid}\"]`);
            if (cell) cell.textContent = descEl ? (descEl.value || '—') : cell.textContent;
          }
          if (modalInstance) modalInstance.hide();
        } else {
          if (window.showToast) showToast((json && json.message) || 'Save failed', 'error', 3000);
        }
      } catch (_e) {
        hideSavingToast();
        if (window.showToast) showToast('Save failed', 'error', 3000);
      }
    });
  }
})();
