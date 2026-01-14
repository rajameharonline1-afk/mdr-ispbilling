const BOOT = window.CLIENT_VIEW_BOOT || {};
const API_SINGLE = '/api/control.php'; // বাংলা: action endpoint
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';
const LIVE_STATUS_TIMEOUT_MS = 20000; // SNMP-heavy live status calls can take >10s; allow enough time
const INITIAL_OLT_BINDING = BOOT.initialOltBinding || null;
let currentOltBinding = INITIAL_OLT_BINDING && INITIAL_OLT_BINDING.olt_id ? INITIAL_OLT_BINDING : null;
const CLIENT_ID = BOOT.clientId || 0;
const MONTHLY_BILL = Number(BOOT.monthlyBill || 0);
const EXPIRY_DATE = BOOT.expiryDate || '';
const PPP_PLAIN = BOOT.pp || '';
const rxValueEl = document.getElementById('olt-last-rx-value');
const rxBadgeEl = document.getElementById('olt-last-rx-badge');

function rxBadgeMeta(val){
  if(val === null || val === undefined || val === '') return [null,null];
  const num = Number(val);
  if(!Number.isFinite(num)) return [null,null];
  if(num >= -24 && num <= -1) return ['Good','text-bg-success'];
  if(num >= -26 && num < -24) return ['Warn','text-bg-warning text-dark'];
  return ['Critical','text-bg-danger'];
}

function updateRxDisplay(val, opts={force:false}){
  const hasVal = val !== null && val !== undefined && val !== '' && Number.isFinite(Number(val));
  if(!hasVal && !opts.force){
    // Keep whatever was already displayed if no new value arrived
    return;
  }
  const txt = hasVal ? `${Number(val).toFixed(2)} dBm` : '—';
  if(rxValueEl) rxValueEl.textContent = txt;
  if(rxBadgeEl){
    const [label, cls] = hasVal ? rxBadgeMeta(val) : [null,null];
    rxBadgeEl.className = 'badge ms-2';
    if(label && cls){
      rxBadgeEl.textContent = label;
      rxBadgeEl.className = `badge ms-2 ${cls}`;
      rxBadgeEl.style.display = '';
    } else {
      rxBadgeEl.textContent = '';
      rxBadgeEl.style.display = 'none';
    }
  }
}

function renderOltBinding(binding){
  if(!binding) return;
  currentOltBinding = binding;
  const nameEl   = document.getElementById('olt-name');
  const hostEl   = document.getElementById('olt-host');
  const vendorEl = document.getElementById('olt-vendor');
  const portEl   = document.getElementById('olt-port');
  const onuEl    = document.getElementById('olt-onu');
  const macEl    = document.getElementById('olt-mac');
  const linkEl   = document.getElementById('olt-linked-at');
  const unlinked = document.getElementById('olt-unlinked-hint');
  const viewL    = document.getElementById('olt-view-link');
  const onuL     = document.getElementById('olt-onu-monitor-link');
  const macL     = document.getElementById('olt-mac-cache-link');

  if(nameEl)   nameEl.textContent = binding.name || (binding.olt_id ? `OLT #${binding.olt_id}` : (nameEl.textContent || '—'));
  if(hostEl)   hostEl.textContent = binding.host || hostEl.textContent || '—';
  if(vendorEl) vendorEl.textContent = binding.vendor || vendorEl.textContent || '—';
  if(portEl)   portEl.textContent = binding.port || binding.port_label || portEl.textContent || '—';
  if(onuEl)    onuEl.textContent  = binding.onu ? ((binding.port || binding.port_label) ? `${binding.port || binding.port_label}:${binding.onu}` : binding.onu) : (onuEl.textContent || '—');
  if(macEl)    macEl.textContent  = binding.mac ? binding.mac.toUpperCase() : (macEl.textContent || '—');
  if(linkEl && binding.learned_at) linkEl.textContent = binding.learned_at;
  updateRxDisplay(binding.rx_power_dbm);

  if(binding.olt_id){
    if(unlinked) unlinked.style.display = 'none';
    if(viewL){ viewL.classList.remove('d-none'); viewL.href = '/olt/index.php'; }
    if(onuL){ onuL.classList.remove('d-none'); onuL.href = `/public/onu_monitor.php?olt_id=${binding.olt_id}`; }
    if(macL){ macL.classList.remove('d-none'); macL.href = `/public/olt_mac_table.php?olt_id=${binding.olt_id}`; }
  }
}

renderOltBinding(currentOltBinding);

/* ===== Toast ===== */
function showToast(msg, type='success', timeout=2800){
  const box = document.createElement('div');
  box.className = 'app-toast ' + (type==='success' ? 'success' : 'error');
  box.setAttribute('role','status');
  box.textContent = msg || 'Done';
  document.body.appendChild(box);
  setTimeout(()=> box.classList.add('hide'), timeout-200);
  setTimeout(()=> box.remove(), timeout);
}
/* Restore toast after reload */
document.addEventListener('DOMContentLoaded', ()=>{
  const t = sessionStorage.getItem('toast');
  if (t){ try{ const o=JSON.parse(t); showToast(o.message, o.type||'success', 2800); }catch{} sessionStorage.removeItem('toast'); }
});

/* ===== Confirm dialog ===== */
function customConfirm({title='Confirm', message='Are you sure?', okText='OK', cancelText='Cancel'}){
  return new Promise((resolve)=>{
    const bd = document.createElement('div');
    bd.className = 'app-confirm-backdrop';
    bd.innerHTML = `
      <div class="app-confirm-box" role="dialog" aria-modal="true" aria-label="${title}">
        <div class="app-confirm-title">${title}</div>
        <div class="app-confirm-text">${message}</div>
        <div class="app-confirm-actions">
          <button class="app-btn secondary" data-act="cancel">${cancelText}</button>
          <button class="app-btn primary" data-act="ok">${okText}</button>
        </div>
      </div>`;
    document.body.appendChild(bd);
    const close=(v)=>{ document.removeEventListener('keydown', onKey); bd.remove(); resolve(v); };
    const onKey=(e)=>{ if(e.key==='Escape') close(false); if(e.key==='Enter') close(true); };
    bd.addEventListener('click', e=>{ if(e.target.dataset.act==='ok') close(true); if(e.target.dataset.act==='cancel'||e.target===bd) close(false); });
    document.addEventListener('keydown', onKey);
    setTimeout(()=> bd.querySelector('[data-act="ok"]')?.focus(), 10);
  });
}

/* ===== Enable/Disable/Kick — POST + CSRF ===== */
async function changeStatus(btn, id, action){
  const ok = await customConfirm({
    title: (action==='disable')?'Disable client?':(action==='kick'?'Disconnect client?':'Enable client?'),
    message: `Are you sure you want to ${action} this client?`,
    okText: (action==='disable')?'Disable':'Yes', cancelText: 'Cancel'
  });
  if(!ok) return;

  const oldHTML = btn.innerHTML; btn.disabled = true; btn.innerHTML = '...';

  fetch(API_SINGLE, {
    method: 'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ action, id: String(id), csrf_token: CSRF })
  })
    .then(r=>r.json())
    .then(data=>{
      if (data.status === 'success'){
        const msg = data.message || 'Done';
        sessionStorage.setItem('toast', JSON.stringify({message: msg, type:'success'}));
        location.reload();
      } else {
        showToast(data.message || 'Operation failed', 'error', 3000);
        btn.disabled=false; btn.innerHTML=oldHTML;
      }
    })
    .catch(()=>{
      showToast('Request failed', 'error', 3000);
      btn.disabled=false; btn.innerHTML=oldHTML;
    });
}

/* ===== Auto-control trigger — POST + CSRF ===== */
async function autoRecheck(btn, id){
  const ok = await customConfirm({
    title: 'Auto re-evaluate?',
    message: 'Run auto control now based on current ledger balance.',
    okText: 'Run now', cancelText: 'Cancel'
  });
  if(!ok) return;

  const old = btn.innerHTML; btn.disabled = true; btn.innerHTML = '...';

  try{
    const res = await fetch('/api/auto_control_client.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({client_id: String(id), csrf_token: CSRF})
    });
    const j = await res.json();
    if (j.ok){
      sessionStorage.setItem('toast', JSON.stringify({message: j.msg || ('Action: '+(j.action||'done')), type:'success'}));
      location.reload();
    } else {
      showToast(j.msg || 'Auto control failed', 'error', 3000);
      btn.disabled=false; btn.innerHTML=old;
    }
  } catch(e){
    showToast('Request failed', 'error', 3000);
    btn.disabled=false; btn.innerHTML=old;
  }
}

/* ===== Copy ===== */
async function __copyTextRobust(t){
  t = (t || '').trim();
  if (!t || t === '-' || t === '—') throw new Error('empty');
  if (navigator.clipboard && window.isSecureContext !== false) {
    await navigator.clipboard.writeText(t);
    return;
  }
  const ta = document.createElement('textarea');
  ta.value = t; ta.setAttribute('readonly',''); ta.style.position='fixed'; ta.style.opacity='0';
  document.body.appendChild(ta); ta.select(); ta.setSelectionRange(0, t.length);
  const ok = document.execCommand('copy');
  document.body.removeChild(ta);
  if (!ok) throw new Error('fallback-failed');
}
document.addEventListener('click', async function(e){
  const btn = e.target.closest('.btn-copy');
  if(!btn) return;
  let text = (btn.getAttribute('data-copy') || '').trim();
  if (!text) {
    const sel = btn.getAttribute('data-copy-el');
    if (sel) {
      const el = document.querySelector(sel);
      if (el) text = (el.textContent || '').trim();
    }
  }
  try { await __copyTextRobust(text); showToast('copied','success',1600); }
  catch(err){ showToast('copy failed','error',1800); }
});

/* ===== Password eye toggle ===== */
document.getElementById('ppp-eye')?.addEventListener('click', ()=>{
  const m = document.getElementById('ppp-mask');
  if (!m) return;
  const maskVal = PPP_PLAIN ? '•'.repeat(Math.max(6, PPP_PLAIN.length)) : '-';
  if (m.dataset.revealed === '1') {
    m.textContent = maskVal;
    m.dataset.revealed = '0';
  } else {
    m.textContent = PPP_PLAIN || '-';
    m.dataset.revealed = '1';
  }
});

/* ===== Live status via API (10s; backoff) ===== */
let liveTimer = null, inflight = false, backoff = 10000;
function loadLiveStatus(){
  if(inflight) return;
  inflight = true;
  const ctl = new AbortController();
  const t = setTimeout(()=>ctl.abort(), LIVE_STATUS_TIMEOUT_MS);

  fetch(`/api/client_live_status.php?id=${CLIENT_ID}`, {cache:'no-store', signal: ctl.signal})
    .then(res=>res.json()).then(d=>{
      const dv = document.getElementById('device-vendor');
      if (dv && d.device_vendor && d.device_vendor.trim() !== '') {
        dv.textContent = d.device_vendor;
      }

      const rmacEl = document.getElementById('router-mac');
      const amacEl = document.getElementById('active-mac');
      const rBtn   = document.getElementById('btn-copy-router');
      const aBtn   = document.getElementById('btn-copy-active');

      const rmac = (d.router_mac && d.router_mac.trim()!=='') ? d.router_mac : (d.arp_mac || d.caller_id || '—');
      const amac = (d.active_mac && d.active_mac.trim()!=='') ? d.active_mac : (d.caller_id || d.arp_mac || '—');

      const keepText = (el) => el && el.textContent && el.textContent.trim() && el.textContent.trim() !== '—';
      if (rmacEl){
        if (rmac && rmac !== '—') rmacEl.textContent = rmac;
        else if (!keepText(rmacEl)) rmacEl.textContent = '—';
      }
      if (amacEl){
        if (amac && amac !== '—') amacEl.textContent = amac;
        else if (!keepText(amacEl)) amacEl.textContent = '—';
      }

      if (rBtn){
        if (rmac && rmac!=='—'){ rBtn.style.display=''; rBtn.setAttribute('data-copy', rmac); rBtn.removeAttribute('data-copy-el'); }
        else { rBtn.style.display='none'; rBtn.setAttribute('data-copy',''); }
      }
      if (aBtn){
        if (amac && amac!=='—'){ aBtn.style.display=''; aBtn.setAttribute('data-copy', amac); aBtn.removeAttribute('data-copy-el'); }
        else { aBtn.style.display='none'; aBtn.setAttribute('data-copy',''); }
      }

      if (dv && (dv.textContent==='—' || dv.textContent==='' || dv.textContent==='Unknown Vendor') && rmac && rmac!=='—'){
        fetch('/api/mac_vendor.php?mac='+encodeURIComponent(rmac), {cache:'no-store'})
          .then(r=>r.json()).then(j=>{ if (j && j.vendor) dv.textContent = j.vendor; }).catch(()=>{});
      }

      const ip = document.getElementById('live-ip');
      const up = document.getElementById('uptime');
      const st = document.getElementById('live-status');
      const ls = document.getElementById('last-seen');
      const dl = document.getElementById('total-dl');
      const ul = document.getElementById('total-ul');
      const rx = document.getElementById('rx-rate');
      const tx = document.getElementById('tx-rate');
      const namePill = document.getElementById('name-online');
      const binding = d.olt_binding;

      const keep = (el) => el && el.textContent && el.textContent.trim() && el.textContent.trim() !== '—';
      const setOrKeep = (el, val, fmt=(v)=>v) => {
        if(!el) return;
        if(val !== null && val !== undefined && String(val).trim() !== ''){
          el.textContent = fmt(val);
        } else if(!keep(el)) {
          el.textContent = '—';
        }
      };

      setOrKeep(ip, d.ip);
      setOrKeep(up, d.uptime);
      setOrKeep(ls, d.last_seen);
      setOrKeep(dl, d.total_download_gb, (v)=>v+' GB');
      setOrKeep(ul, d.total_upload_gb, (v)=>v+' GB');
      if(rx) rx.textContent = d.rx_rate || '0 Kbps';
      if(tx) tx.textContent = d.tx_rate || '0 Kbps';
      updateRxDisplay(d.rx_power_dbm);
      if(binding && binding.olt_id){
        renderOltBinding(binding);
      }

      if(st){
        st.textContent = d.online ? 'Online':'Offline';
        st.className   = 'badge ' + (d.online ? 'bg-success' : 'bg-danger');
      }
      if(namePill){
        namePill.innerHTML = `<i class="bi bi-wifi"></i> ${d.online ? 'Online' : 'Offline'}`;
        namePill.className = 'badge ' + (d.online ? 'bg-success' : 'bg-secondary');
        namePill.style.backgroundColor = d.online ? '#198754' : '#6c757d';
      }

      backoff = 10000;
    })
    .catch(()=>{ backoff = Math.min(backoff * 1.5, 30000); })
    .finally(()=>{ clearTimeout(t); inflight=false; });
}
function startLive(){ if (!liveTimer) liveTimer = setInterval(loadLiveStatus, backoff); }
function stopLive(){ if (liveTimer) { clearInterval(liveTimer); liveTimer = null; } }
document.addEventListener('visibilitychange', ()=> {
  if (document.hidden) stopLive(); else { loadLiveStatus(); startLive(); }
});
setInterval(()=>{ if (liveTimer){ clearInterval(liveTimer); liveTimer = setInterval(loadLiveStatus, backoff); } }, 3000);

loadLiveStatus(); startLive();

/* ===== Renew submit (invoice+renew) ===== */
(function(){
  const monthsEl = document.getElementById('rn_months');
  const amountEl = document.getElementById('rn_amount');
  const invDateEl= document.getElementById('rn_invoice_date');
  const formEl   = document.getElementById('renewForm');

  const monthlyBill = MONTHLY_BILL;
  const expCur = EXPIRY_DATE;

  function addMonths(dateStr, m){
    if(!dateStr) return '';
    const d = new Date(dateStr+'T00:00:00');
    if(isNaN(d)) return '';
    const dd = new Date(d.getTime()); dd.setMonth(dd.getMonth() + m);
    return `${dd.getFullYear()}-${String(dd.getMonth()+1).padStart(2,'0')}-${String(dd.getDate()).padStart(2,'0')}`;
  }
  function todayYMD(){
    const d=new Date();
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
  }
  function maxDate(a,b){ if(!a) return b; if(!b) return a; return (a>b)?a:b; }

  document.getElementById('renewModal')?.addEventListener('shown.bs.modal', ()=>{
    const m = parseInt(monthsEl.value||'1',10);
    if (!amountEl.dataset.touched) amountEl.value = (monthlyBill * (isNaN(m)?1:m)).toFixed(2);
    const base = maxDate(todayYMD(), (expCur||'')); // base = today বা current expiry এর বড় যেটা
    document.getElementById('rn_exp_new').textContent = base ? addMonths(base, isNaN(m)?1:m) : '—';
    document.getElementById('rn_exp_current').textContent = (expCur||'—');
  });

  monthsEl?.addEventListener('change', ()=>{
    const m = parseInt(monthsEl.value||'1',10);
    if (!amountEl.dataset.touched) amountEl.value = (monthlyBill * (isNaN(m)?1:m)).toFixed(2);
    const base = maxDate(todayYMD(), (expCur||'')); 
    document.getElementById('rn_exp_new').textContent = base ? addMonths(base, isNaN(m)?1:m) : '—';
  });
  amountEl?.addEventListener('input', ()=>{ amountEl.dataset.touched = '1'; });

  formEl?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const months = parseInt(monthsEl.value||'1',10);
    const amount = Number(amountEl.value||'0');
    const method = document.getElementById('rn_method').value || 'Cash';
    const note   = document.getElementById('rn_note').value || '';
    const invdt  = invDateEl.value || todayYMD();
    if(isNaN(months) || months<=0){ showToast('Invalid months','error'); return; }
    if(isNaN(amount) || amount<=0){ showToast('Invalid amount','error'); return; }

    try{
      const res = await fetch('/api/renew.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ client_id: CLIENT_ID, months, amount, method, note, invoice_date: invdt, csrf_token: CSRF })
      });
      const data = await res.json();
      if (data.status === 'success'){
        showToast(data.message || 'Renewed & Invoiced','success',2200);
        const url = data.invoice_id
          ? `/public/invoice_view.php?id=${encodeURIComponent(data.invoice_id)}`
          : `/public/invoices.php?client_id=${CLIENT_ID}`;
        setTimeout(()=> window.location.href = url, 700);
      } else {
        showToast(data.message || 'Renew failed','error',3000);
      }
    }catch(err){ showToast('Request failed','error',3000); }
  });
})();
