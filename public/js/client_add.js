// (বাংলা) Client Add পেজের JS
(function(){
  const pkgSel = document.getElementById('package_id');
  const bill   = document.getElementById('monthly_bill');
  const routerSel = document.querySelector('select[name="router_id"]');
  const pppoeInput = document.querySelector('input[name="pppoe_id"]');
  const pppoeStatus = document.getElementById('pppoe_status');
  let routerTouched = false;

  routerSel?.addEventListener('change', () => { routerTouched = true; });

  function updateFromPackage(opts = {}){
    const {forceBill = false, forceRouter = false} = opts;
    if (!pkgSel) return;
    const opt = pkgSel.options?.[pkgSel.selectedIndex];
    if (!opt) return;

    const price = parseFloat(opt.dataset?.price || '0');
    if (!isNaN(price) && bill) {
      if (forceBill || !bill.value || parseFloat(bill.value) === 0) {
        bill.value = price.toFixed(2);
      }
    }

    if (routerSel) {
      const rid = parseInt(opt.dataset?.router || '0', 10);
      if (rid && (forceRouter ? !routerSel.value : (!routerTouched || !routerSel.value))) {
        routerSel.value = String(rid);
      }
    }
  }

  pkgSel?.addEventListener('change', () => updateFromPackage({forceBill:true}));
  if (pkgSel) { updateFromPackage({forceRouter:true}); }

  const pppSelect = document.getElementById('ppp_profile');
  async function loadProfiles(routerId) {
    if (!pppSelect) return;
    const curVal = pppSelect.value || '';
    pppSelect.innerHTML = '';
    const baseOpt = document.createElement('option');
    baseOpt.value = '';
    baseOpt.textContent = 'Use package profile';
    pppSelect.appendChild(baseOpt);
    if (!routerId) return;
    try {
      const res = await fetch('/app/ppp_profiles.php?router_id=' + encodeURIComponent(routerId));
      const data = await res.json();
      if (data && data.status === 'success' && Array.isArray(data.profiles)) {
        data.profiles.forEach((name) => {
          const opt = document.createElement('option');
          opt.value = name;
          opt.textContent = name;
          pppSelect.appendChild(opt);
        });
        if (curVal) { pppSelect.value = curVal; }
      }
    } catch (e) { /* ignore profile fetch errors */ }
  }
  if (routerSel) {
    routerSel.addEventListener('change', () => loadProfiles(routerSel.value));
    if (routerSel.value) loadProfiles(routerSel.value);
  }

  let checkTimer = null;
  function setPppoeStatus(msg, cls) {
    if (!pppoeStatus) return;
    pppoeStatus.className = 'form-text small ' + (cls || '');
    pppoeStatus.textContent = msg || '';
  }
  async function checkPppoeSecret() {
    if (!pppoeInput || !routerSel) return;
    const name = (pppoeInput.value || '').trim();
    const rid = routerSel.value;
    if (!rid || !name) { setPppoeStatus('', ''); return; }
    setPppoeStatus('Checking...', 'text-muted');
    try {
      const res = await fetch('/api/pppoe_secret_check.php?router_id=' + encodeURIComponent(rid) + '&pppoe_id=' + encodeURIComponent(name));
      const data = await res.json();
      if (data && data.status === 'success') {
        if (data.exists) { setPppoeStatus('Already Exists...❌', 'text-danger'); }
        else { setPppoeStatus('Available ✔', 'text-success'); }
      } else {
        setPppoeStatus('Check failed', 'text-danger');
      }
    } catch (e) {
      setPppoeStatus('Check failed', 'text-danger');
    }
  }
  function debounceCheck() {
    if (checkTimer) clearTimeout(checkTimer);
    checkTimer = setTimeout(checkPppoeSecret, 400);
  }
  pppoeInput?.addEventListener('input', debounceCheck);
  pppoeInput?.addEventListener('blur', checkPppoeSecret);
  routerSel?.addEventListener('change', debounceCheck);
})();

// (বাংলা) লোকেশন অপশন মডাল
(function(){
  const boot = window.CLIENT_ADD_BOOT || {};
  const typeLabels = {area:'Area', sub_zone:'Sub Zone', box:'Box'};
  const csrf = boot.locCsrf || '';
  let currentType = null;
  const modalEl = document.getElementById('locOptionModal');
  let modalInstance = null;
  const titleEl = document.getElementById('locModalTitle');
  const form = document.getElementById('locOptionForm');
  const typeField = document.getElementById('locTypeField');
  const labelInput = document.getElementById('locLabelInput');
  const detailInput = document.getElementById('locDetailInput');
  const noticeEl = document.getElementById('locSaveNotice');
  const clearBtn = document.getElementById('locClearBtn');
  const saveBtn = document.getElementById('locSaveBtn');
  const parentAreaWrap = document.querySelector('[data-field="parent_area"]');
  const parentSubWrap = document.querySelector('[data-field="parent_sub_zone"]');
  const parentAreaSelect = document.getElementById('locParentArea');
  const parentSubSelect = document.getElementById('locParentSubZone');
  const areaSelect = document.querySelector('select[data-loc-type="area"]');
  const subZoneSelect = document.querySelector('select[data-loc-type="sub_zone"]');
  let areaCache = null;
  let subZoneCache = null;

  async function refreshSelect(type, selectedValue){
    const sel = document.querySelector(`select[data-loc-type="${type}"]`);
    if (!sel) return;
    try {
      const res = await fetch(`/ajax/location_options.php?type=${encodeURIComponent(type)}`, {cache:'no-store'});
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error || 'Failed to load list');
      const keep = selectedValue ?? sel.value;
      const opts = Array.isArray(data.items) ? data.items : (Array.isArray(data.options) ? data.options : []);
      sel.innerHTML = '<option value="">Select</option>';
      opts.forEach(val => {
        const opt = document.createElement('option');
        const label = typeof val === 'string' ? val : (val.label || '');
        opt.value = label; opt.textContent = label; sel.appendChild(opt);
      });
      if (keep) {
        if (!opts.includes(keep)) {
          const extra = document.createElement('option');
          extra.value = keep; extra.textContent = keep; sel.appendChild(extra);
        }
        sel.value = keep;
      }
    } catch (err) {
      console.error(err);
      alert(err.message || 'Could not refresh options.');
    }
  }

  async function fetchFull(type){
    const url = `/ajax/location_options.php?type=${encodeURIComponent(type)}&full=1`;
    const res = await fetch(url, {cache:'no-store'});
    const json = await res.json();
    if (!res.ok || !json.ok) throw new Error(json.error || 'Failed to load list');
    return Array.isArray(json.items) ? json.items : [];
  }

  async function ensureAreaCache(){ if (!areaCache) areaCache = await fetchFull('area'); return areaCache; }
  async function ensureSubZoneCache(){ if (!subZoneCache) subZoneCache = await fetchFull('sub_zone'); return subZoneCache; }

  async function populateParentArea(selectedValue){
    if (!parentAreaSelect) return;
    const data = await ensureAreaCache();
    parentAreaSelect.innerHTML = '<option value="">Select Zone</option>';
    data.forEach(row => {
      const opt = document.createElement('option');
      opt.value = row.label || '';
      opt.textContent = row.label || '';
      if (opt.value === selectedValue) opt.selected = true;
      parentAreaSelect.appendChild(opt);
    });
  }

  async function populateParentSub(areaValue, selectedValue){
    if (!parentSubSelect) return;
    const data = await ensureSubZoneCache();
    parentSubSelect.innerHTML = '<option value="">Select Sub Zone</option>';
    const filtered = areaValue ? data.filter(row => row.parent_area === areaValue) : data;
    filtered.forEach(row => {
      const opt = document.createElement('option');
      opt.value = row.label || '';
      opt.textContent = row.label || '';
      if (opt.value === selectedValue) opt.selected = true;
      parentSubSelect.appendChild(opt);
    });
    parentSubSelect.disabled = filtered.length === 0;
  }

  parentAreaSelect?.addEventListener('change', () => {
    if (currentType === 'box') {
      populateParentSub(parentAreaSelect.value || '', '');
    }
  });

  function ensureModal(){
    if (modalInstance) return modalInstance;
    const bs = window.bootstrap || null;
    if (!modalEl || !bs || !bs.Modal) return null;
    if (modalEl.parentElement !== document.body) {
      document.body.appendChild(modalEl);
    }
    modalInstance = new bs.Modal(modalEl);
    return modalInstance;
  }

  async function openModal(type){
    currentType = type;
    const modal = ensureModal();
    if (!modal) {
      alert('Cannot open form because Bootstrap modal is unavailable.');
      return;
    }
    typeField.value = type;
    if (titleEl) titleEl.textContent = 'Add ' + (typeLabels[type] || 'Option');
    form?.reset();
    clearNotice();
    const showArea = (type === 'sub_zone' || type === 'box');
    const showSub = (type === 'box');
    parentAreaWrap?.classList.toggle('d-none', !showArea);
    parentSubWrap?.classList.toggle('d-none', !showSub);
    if (showArea) {
      const defaultArea = areaSelect?.value || '';
      await populateParentArea(defaultArea);
      if (showSub) {
        const defaultSub = subZoneSelect?.value || '';
        await populateParentSub(parentAreaSelect.value || defaultArea, defaultSub);
      }
    }
    modal.show();
  }

  clearBtn?.addEventListener('click', () => {
    form?.reset();
    clearNotice();
    parentAreaSelect && (parentAreaSelect.value = '');
    parentSubSelect && (parentSubSelect.value = '');
    parentSubSelect && (parentSubSelect.disabled = false);
    labelInput?.focus();
  });

  function setNotice(message, type = 'info'){
    if (!noticeEl) return;
    noticeEl.textContent = message || '';
    noticeEl.className = 'form-text small';
    noticeEl.classList.remove('d-none');
    if (type === 'success') {
      noticeEl.classList.add('text-success');
    } else if (type === 'error') {
      noticeEl.classList.add('text-danger');
    } else {
      noticeEl.classList.add('text-muted');
    }
    if (!message) noticeEl.classList.add('d-none');
  }
  function clearNotice(){
    if (!noticeEl) return;
    noticeEl.textContent = '';
    noticeEl.className = 'form-text small d-none';
  }

  async function submitValue(type, label, details){
    try {
      const payload = {type, label, details, csrf_token: csrf};
      if (type !== 'area' && parentAreaSelect && !parentAreaWrap?.classList.contains('d-none')) {
        payload.parent_area = parentAreaSelect.value || '';
      }
      if (type === 'box' && parentSubSelect && !parentSubWrap?.classList.contains('d-none')) {
        payload.parent_sub_zone = parentSubSelect.value || '';
      }
      const res = await fetch('/ajax/location_options.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (!res.ok || !data.ok) throw new Error(data.error || 'Failed to save');
      areaCache = null; subZoneCache = null;
      await refreshSelect(type, data.value || label);
      if (type === 'area') {
        await populateParentArea(parentAreaSelect?.value || '');
      } else if (type === 'sub_zone' || type === 'box') {
        await populateParentSub(parentAreaSelect?.value || '', parentSubSelect?.value || '');
      }
      setNotice(`Saved: ${(data.value || label).trim()}.`, 'success');
      alert('Saved successfully.');
      const modal = ensureModal();
      modal?.hide();
      form?.reset();
    } catch (err) {
      const msg = err?.message || 'Could not save option.';
      setNotice(msg, 'error');
      alert(msg);
    }
  }

  saveBtn?.addEventListener('click', () => {
    const type = currentType;
    const label = (labelInput?.value ?? '').trim();
    const details = (detailInput?.value ?? '').trim();
    if (!type || !label) {
      setNotice('Please enter a value.', 'error');
      alert('Please enter a value.');
      return;
    }
    if (type === 'sub_zone' && parentAreaSelect && !parentAreaSelect.value) {
      setNotice('Please select a zone first.', 'error');
      alert('Please select a zone first.');
      parentAreaSelect.focus();
      return;
    }
    if (type === 'box') {
      if (parentAreaSelect && !parentAreaSelect.value) {
        setNotice('Please select a zone first.', 'error');
        alert('Please select a zone first.');
        parentAreaSelect.focus();
        return;
      }
      if (parentSubSelect && !parentSubSelect.value) {
        setNotice('Please select a sub zone.', 'error');
        alert('Please select a sub zone.');
        parentSubSelect.focus();
        return;
      }
    }
    submitValue(type, label, details);
  });

  document.querySelectorAll('[data-loc-add]').forEach(btn => {
    btn.addEventListener('click', () => {
      const t = btn.dataset.locAdd;
      if (!t) return;
      openModal(t).catch(err => alert(err.message || 'Failed to open form.'));
    });
  });
})();
