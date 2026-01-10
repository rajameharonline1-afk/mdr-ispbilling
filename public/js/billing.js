// (বাংলা) Billing পেজের JS আলাদা ফাইলে রাখা হলো

/* (বাংলা) পেমেন্ট হয়ে ফিরে এলে রিসিট অটো-ওপেন; ছোট Toast */
(() => {
  const q = new URLSearchParams(location.search);
  if (q.get('ok') === '1' && q.get('pid')) {
    const pid = q.get('pid');
    const w   = q.get('w') || '58';
    const url = `/public/receipt_payment.php?payment_id=${encodeURIComponent(pid)}&w=${encodeURIComponent(w)}&autoprint=1`;
    window.open(url, '_blank', 'noopener');
    const t = document.createElement('div');
    t.style.position='fixed'; t.style.left='50%'; t.style.top='20px'; t.style.transform='translateX(-50%)';
    t.style.background='#198754'; t.style.color='#fff'; t.style.padding='10px 14px';
    t.style.borderRadius='10px'; t.style.boxShadow='0 10px 30px rgba(0,0,0,.2)'; t.style.zIndex='9999';
    t.textContent='Payment saved';
    document.body.appendChild(t);
    setTimeout(()=>{ t.style.opacity='0'; t.style.transform='translate(-50%,-10px)'; }, 1800);
    setTimeout(()=> t.remove(), 2300);
  }
})();

/* (বাংলা) Discount Manager Modal লজিক */
(() => {
  const CSRF = (window.BILLING_BOOT && window.BILLING_BOOT.csrf) || '';
  const modalEl = document.getElementById('discountModal');
  const discInfo = document.getElementById('disc-info');
  const invAmtEl = document.getElementById('inv-disc-amt');
  const btnClearInv = document.getElementById('btn-clear-inv');
  const tbody = document.getElementById('pay-disc-tbody');
  let currentInvoiceId = 0;

  async function apiCall(payload){
    const res = await fetch('/public/billing_discount_api.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
      body: new URLSearchParams(payload)
    });
    return res.json();
  }

  async function loadDiscounts(invId){
    discInfo.textContent = 'Loading…';
    invAmtEl.textContent = ' 0.00';
    btnClearInv.disabled = true;
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Loading…</td></tr>';

    const data = await apiCall({ action:'list', invoice_id: invId, csrf: CSRF });
    if (!data.ok) {
      discInfo.textContent = 'Failed: ' + (data.error || 'Unknown error');
      return;
    }
    discInfo.textContent = 'Invoice ID: ' + invId;

    const inv = Number(data.invoice_discount || 0);
    invAmtEl.textContent = ' ' + inv.toFixed(2);
    btnClearInv.disabled = !(inv > 0.0001);

    const pays = Array.isArray(data.payments) ? data.payments : [];
    if (!pays.length) {
      tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No payment discounts.</td></tr>';
    } else {
      tbody.innerHTML = '';
      pays.forEach(row => {
        const tr = document.createElement('tr');
        const id = Number(row.id);
        const disc = Number(row.discount || 0);
        const dt = row.pdate || '';
        const method = row.method || '';
        tr.innerHTML = `
          <td>${id}</td>
          <td>${dt ? dt : '-'}</td>
          <td>${method ? method : '-'}</td>
          <td class="text-end">৳ ${disc.toFixed(2)}</td>
          <td class="text-end">
            <button class="btn btn-outline-danger btn-sm btn-del-pay" data-pid="${id}">
              <i class="bi bi-x-circle"></i> Clear
            </button>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }
  }

  // Open modal
  document.addEventListener('click', function(ev){
    const btn = ev.target.closest('.manage-discount');
    if (!btn) return;
    ev.preventDefault();
    currentInvoiceId = Number(btn.dataset.invoice) || 0;
    if (!currentInvoiceId) return;

    const modal = new bootstrap.Modal(modalEl);
    modal.show();
    loadDiscounts(currentInvoiceId);
  });

  // Clear invoice-level discount
  btnClearInv?.addEventListener('click', async function(){
    if (this.disabled || !currentInvoiceId) return;
    if (!confirm('Clear invoice-level discount?')) return;
    const data = await apiCall({ action:'clear_invoice', invoice_id: currentInvoiceId, csrf: CSRF });
    if (!data.ok) { alert(data.error || 'Failed'); return; }
    await loadDiscounts(currentInvoiceId);
    location.reload();
  });

  // Clear payment-level discount (event delegation)
  tbody?.addEventListener('click', async function(ev){
    const b = ev.target.closest('.btn-del-pay');
    if (!b) return;
    const pid = Number(b.dataset.pid) || 0;
    if (!pid) return;
    if (!confirm('Clear this payment discount?')) return;
    const data = await apiCall({ action:'delete_payment', payment_id: pid, csrf: CSRF });
    if (!data.ok) { alert(data.error || 'Failed'); return; }
    await loadDiscounts(currentInvoiceId);
    location.reload();
  });
})();
