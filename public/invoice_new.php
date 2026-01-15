<?php
// /public/invoice_new.php
// নতুন ইনভয়েস ফর্ম + লাইভ ক্যালকুলেশন (UI), সার্ভার-সাইড ক্যালকুলেশন হবে create API-তে

require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// optional preselect client
$client_id = (int)($_GET['client_id'] ?? 0);
$clients = db()->query("SELECT id,name FROM clients WHERE is_deleted=0 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>New Invoice</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<style>
.new-invoice-shell{
  max-width: 960px;
  background:
    radial-gradient(780px 520px at 12% 12%, rgba(59, 130, 246, 0.12), transparent 60%),
    radial-gradient(760px 500px at 90% 14%, rgba(16, 185, 129, 0.12), transparent 60%),
    #f6f7fb;
  border: 1px solid #e5e7eb;
  border-radius: 18px;
  box-shadow: 0 18px 50px rgba(15, 23, 42, 0.12);
  padding: 1.5rem;
}
.new-invoice-shell h4{ font-weight:700; }
.card-invoice{
  border:1px solid #e5e7eb;
  border-radius: 14px;
  box-shadow: 0 12px 28px rgba(15,23,42,0.08);
}
.card-invoice .card-body{
  padding: 1.25rem;
}
.form-label{ font-weight: 600; color: #111827; }
.form-control, .form-select, textarea.form-control{
  border-radius: 10px;
  border-color: #e5e7eb;
  background: #f9fafb;
  box-shadow: inset 0 1px 2px rgba(15,23,42,0.04);
}
.form-control:focus, .form-select:focus, textarea.form-control:focus{
  border-color: #0d6efd;
  box-shadow: 0 0 0 .15rem rgba(13,110,253,.15);
  background: #fff;
}
.card-preview .kv {display:flex;justify-content:space-between}
.card-preview .kv div:first-child{color:#6c757d}
</style>
</head>
<body>
<?php include __DIR__.'/../partials/partials_header.php'; ?>

<div class="main-content p-3 p-md-4 d-flex justify-content-center">
  <div class="container-fluid new-invoice-shell">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h4 class="mb-1">New Invoice</h4>
      <a class="btn btn-outline-secondary btn-sm" href="invoices.php">Back</a>
    </div>

    <form class="card border-0 card-invoice" method="post" action="../api/invoice_create.php" id="invForm">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12 col-md-6">
            <label class="form-label">Client</label>
            <select name="client_id" id="client_id" class="form-select" required>
              <option value="">Select client...</option>
              <?php foreach($clients as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= $client_id===(int)$c['id']?'selected':'' ?>>
                  <?= h($c['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text" id="cl-meta"><!-- will fill via ajax --></div>
          </div>

          <div class="col-6 col-md-3">
            <label class="form-label">Period Start</label>
            <input type="date" name="period_start" id="period_start" class="form-control" required>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">Period End</label>
            <input type="date" name="period_end" id="period_end" class="form-control" required>
          </div>

          <div class="col-6 col-md-3">
            <label class="form-label">Amount</label>
            <input type="number" step="0.01" name="amount" id="amount" class="form-control" required>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">Discount</label>
            <input type="number" step="0.01" name="discount" id="discount" class="form-control" value="0">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">VAT %</label>
            <input type="number" step="0.01" name="vat_percent" id="vat_percent" class="form-control" value="0">
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label">Due Date</label>
            <input type="date" name="due_date" id="due_date" class="form-control">
          </div>

          <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes"></textarea>
          </div>
        </div>
      </div>

      <div class="card-footer d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <div class="card card-preview border-0 shadow-sm mb-0">
          <div class="card-body py-2">
            <div class="kv"><div>Base</div><div id="pv_base">0.00</div></div>
            <div class="kv"><div>Discount</div><div id="pv_discount">0.00</div></div>
            <div class="kv"><div>VAT</div><div id="pv_vat">0.00</div></div>
            <div class="kv fw-semibold"><div>Total</div><div id="pv_total">0.00</div></div>
          </div>
        </div>
        <div>
          <button class="btn btn-primary" type="submit"><i class="bi bi-save2"></i> Create Invoice</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
// --- ছোট JS: client meta prefill + live totals ---
const $ = (s)=>document.querySelector(s);
const fmt = (n)=> (Number(n||0).toFixed(2));

async function loadClientMeta(id){
  if(!id){ $('#cl-meta').textContent=''; return; }
  try{
    const r = await fetch('../api/client_meta.php?id='+encodeURIComponent(id));
    const j = await r.json();
    if(!j.ok) return;
    const d=j.data;
    $('#cl-meta').textContent = `PPPoE: ${d.pppoe_id||'-'} • Expiry: ${d.expiry_date||'-'} • Monthly: ${fmt(d.monthly_bill)}`;

    // prefill amount if empty
    if(!$('#amount').value){ $('#amount').value = d.monthly_bill || ''; }
    // auto period from expiry+1 to +1M-1
    if(d.expiry_date && !$('#period_start').value && !$('#period_end').value){
      const ps = new Date(d.expiry_date); ps.setDate(ps.getDate()+1);
      const pe = new Date(ps); pe.setMonth(pe.getMonth()+1); pe.setDate(pe.getDate()-1);
      const s1 = ps.toISOString().slice(0,10);
      const s2 = pe.toISOString().slice(0,10);
      $('#period_start').value = s1;
      $('#period_end').value   = s2;
      if(!$('#due_date').value) $('#due_date').value = s1;
      recalc();
    }
  }catch(e){}
}

function recalc(){
  const amount = parseFloat($('#amount').value||0);
  const discount = Math.max(0, parseFloat($('#discount').value||0));
  const base = Math.max(0, amount - discount);
  const vatp = Math.max(0, parseFloat($('#vat_percent').value||0));
  const vat = +(base * (vatp/100)).toFixed(2);
  const total = +(base + vat).toFixed(2);

  $('#pv_base').textContent     = fmt(amount);
  $('#pv_discount').textContent = fmt(discount);
  $('#pv_vat').textContent      = fmt(vat);
  $('#pv_total').textContent    = fmt(total);
}

['#amount','#discount','#vat_percent'].forEach(sel=>{
  document.addEventListener('input', e=>{
    if(e.target.matches(sel)) recalc();
  });
});

$('#client_id').addEventListener('change', e=> loadClientMeta(e.target.value));
document.addEventListener('DOMContentLoaded', ()=>{
  const pre = $('#client_id').value; if(pre) loadClientMeta(pre);
  recalc();
});
</script>
</body>
</html>
