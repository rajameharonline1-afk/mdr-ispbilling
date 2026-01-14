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
  const filterForm = document.getElementById('oltFiltersForm');
  const clientForm = document.getElementById('clientCodeSearchForm');
  const clientInput = document.getElementById('clientCodeInput');
  const oltSelect = filterForm?.querySelector('select[name="olt_id"]');
  const ponSelect = filterForm?.querySelector('select[name="pon"]');
  const tableArea = document.querySelector('.olt-table-area');
  const summaryWrap = document.getElementById('oltSummary');
  if (!filterForm || !clientForm || !clientInput || !tableArea || !oltSelect) return;

  let timer = null;

  function setTable(html) {
    tableArea.innerHTML = html || '<div class="p-4 text-center text-muted">কোনো ডেটা পাওয়া যায়নি।</div>';
  }

  function setSummary(html) {
    if (summaryWrap) summaryWrap.innerHTML = html || '';
  }

  function syncPonOptions(options, current, clearSelection = false) {
    if (!ponSelect) return;
    const opts = Array.isArray(options) ? options : [];
    const prev = clearSelection ? '0' : (ponSelect.value || '0');
    ponSelect.innerHTML = '<option value="0" disabled>PON সিলেক্ট করুন</option>';
    opts.forEach(val => {
      const v = String(val);
      const opt = document.createElement('option');
      opt.value = v;
      opt.textContent = 'PON ' + v;
      ponSelect.appendChild(opt);
    });
    if (opts.length === 0) {
      ponSelect.value = '0';
      ponSelect.setAttribute('disabled', 'disabled');
    } else {
      ponSelect.removeAttribute('disabled');
      if (!clearSelection && opts.includes(Number(prev))) {
        ponSelect.value = prev;
      } else if (current && opts.includes(Number(current))) {
        ponSelect.value = String(current);
      } else {
        ponSelect.value = '0';
      }
    }
  }

  async function fetchTable() {
    const oltId = parseInt(oltSelect.value || '0', 10) || 0;
    const ponVal = ponSelect ? (parseInt(ponSelect.value || '0', 10) || 0) : 0;
    const code = (clientInput.value || '').trim();

    if (oltId <= 0) {
      syncPonOptions([], 0, true);
      setSummary('');
      setTable('<div class="p-4 text-center text-muted">দয়া করে প্রথমে OLT সিলেক্ট করুন।</div>');
      return;
    }

    setTable('<div class="p-4 text-center text-muted">লোড হচ্ছে…</div>');
    try {
      const params = new URLSearchParams({ ajax: '1', olt_id: String(oltId) });
      if (ponVal > 0) params.append('pon', String(ponVal));
      if (code !== '') params.append('client_code', code);
      const res = await fetch('/public/olt_mac_table.php?' + params.toString(), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        cache: 'no-store',
        credentials: 'same-origin'
      });
      const data = await res.json();
      if (!res.ok || !data?.ok) throw new Error(data?.error || 'লোড ব্যর্থ হয়েছে');
      syncPonOptions(data.pon_options || [], ponVal);
      setTable(data.html || '<div class="p-4 text-center text-muted">কোনো ডেটা পাওয়া যায়নি।</div>');
      setSummary(data.summary || '');
    } catch (err) {
      setTable('<div class="p-4 text-center text-danger">' + (err?.message || 'লোড ব্যর্থ হয়েছে') + '</div>');
    }
  }

  function debounceFetch() {
    if (timer) clearTimeout(timer);
    timer = setTimeout(fetchTable, 350);
  }

  oltSelect.addEventListener('change', () => {
    if (ponSelect) {
      ponSelect.value = '0';
    }
    debounceFetch();
  });
  ponSelect?.addEventListener('change', debounceFetch);
  clientInput.addEventListener('input', debounceFetch);
  clientInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      debounceFetch();
    }
  });

  clientForm.addEventListener('submit', (e) => e.preventDefault());
  filterForm.addEventListener('submit', (e) => e.preventDefault());
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
