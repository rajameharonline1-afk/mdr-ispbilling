<?php
// /public/packages.php
// (বাংলা) Packages: list + search + sort + pagination + add/edit + delete + export
require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';

/* ------- Inputs ------- */
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = max(10, (int)($_GET['limit'] ?? 20));
$offset = ($page - 1) * $limit;

$sort = strtolower($_GET['sort'] ?? 'name');            // name | price | id
$dir  = strtolower($_GET['dir']  ?? 'asc');             // asc | desc
$dir  = in_array($dir, ['asc','desc'], true) ? $dir : 'asc';
$map  = ['name'=>'p.name','price'=>'p.price','id'=>'p.id'];
$orderBy = $map[$sort] ?? 'p.name';
$orderSql= $orderBy . ' ' . strtoupper($dir) . ', p.id ASC';

/* ------- Helpers ------- */
function hascol(PDO $pdo, string $tbl, string $col): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM `$tbl` LIKE ?");
  $st->execute([$col]);
  return (bool)$st->fetchColumn();
}

/* ------- DB ------- */
$pdo = db();
$has_speed = hascol($pdo,'packages','speed');
$has_is_deleted = hascol($pdo,'packages','is_deleted');

/* WHERE */
$where = $has_is_deleted ? "COALESCE(p.is_deleted,0)=0" : "1=1";
$params = [];
if ($search !== '') {
  $where .= " AND (p.name LIKE ? OR CAST(p.price AS CHAR) LIKE ?)";
  $like = "%$search%";
  array_push($params, $like, $like);
}

/* Count */
$sqlCount = "SELECT COUNT(*) FROM packages p WHERE $where";
$stc = $pdo->prepare($sqlCount); $stc->execute($params);
$total = (int)$stc->fetchColumn();
$total_pages = max(1, (int)ceil($total / $limit));

/* Data */
$cols = "p.id, p.name, p.price, p.description";
if ($has_speed) $cols .= ", p.speed";
$sql = "SELECT $cols, COALESCE(cnt.cnt,0) AS clients
        FROM packages p
        LEFT JOIN (
          SELECT package_id, COUNT(*) cnt
          FROM clients
          WHERE (is_left IS NULL OR is_left=0)
          GROUP BY package_id
        ) AS cnt ON cnt.package_id = p.id
        WHERE $where
        ORDER BY $orderSql
        LIMIT $limit OFFSET $offset";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

/* Sort link helper */
function sort_link($key, $label, $currentSort, $currentDir){
  $qs = $_GET;
  $next = ($currentSort === $key && $currentDir === 'asc') ? 'desc' : 'asc';
  $qs['sort'] = $key; $qs['dir'] = $next; $qs['page']=1;
  $href = '?' . http_build_query($qs);
  $icon = ' <i class="bi bi-arrow-down-up"></i>';
  if ($currentSort === $key) {
    $icon = ($currentDir === 'asc') ? ' <i class="bi bi-caret-up-fill"></i>' : ' <i class="bi bi-caret-down-fill"></i>';
  }
  return '<a class="text-decoration-none" href="'.$href.'">'.$label.$icon.'</a>';
}

include __DIR__ . '/../partials/partials_header.php';
?>
<style>
.table-container{background:#fff;padding:15px;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.1);}
.app-toast{position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);z-index:9999;border:0;border-radius:120px;box-shadow:0 16px 40px rgba(0,0,0,.25);padding:14px 18px;min-width:280px;text-align:center;color:#fff;background:#0d6efd;transition:opacity .25s,transform .25s;}
.app-toast.success{background:#198754}.app-toast.error{background:#dc3545}.app-toast.hide{opacity:0;transform:translate(-50%,-60%)}
/* Keep package modal above backdrop even if parent containers have stacking contexts */
.modal-backdrop{z-index:1045;}
.modal{z-index:1050;}
.table-packages th,.table-packages td{vertical-align:middle;}
.table-packages .col-id{width:70px;white-space:nowrap;}
.table-packages .col-speed{width:150px;white-space:nowrap;}
.table-packages .col-price{width:150px;white-space:nowrap;}
.table-packages .col-desc{min-width:220px;max-width:360px;}
.table-packages .col-desc .desc-text{display:block;max-height:3.6rem;overflow:hidden;}
.table-packages .col-clients{width:120px;white-space:nowrap;}
.table-packages .col-action{width:170px;white-space:nowrap;}
</style>

<div class="container-fluid py-3">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
      <h5 class="mb-0">Packages</h5>
      <div class="text-muted small">Total: <?= number_format($total) ?></div>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
      <form class="d-flex" method="get">
        <input type="hidden" name="limit" value="<?= (int)$limit ?>">
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Search packages..."
               value="<?= htmlspecialchars($search) ?>">
        <button class="btn btn-primary btn-sm ms-2"><i class="bi bi-search"></i></button>
      </form>
      <a class="btn btn-outline-secondary btn-sm" href="/public/packages_export.php?<?= http_build_query(['search'=>$search]) ?>">
        <i class="bi bi-filetype-csv"></i> Export CSV
      </a>
      <button class="btn btn-success btn-sm" id="btn-add">
        <i class="bi bi-plus-circle"></i> New Package
      </button>
    </div>
  </div>

  <div class="table-responsive mt-3 table-container">
    <table class="table table-sm table-hover align-middle table-packages">
      <thead class="table-primary">
        <tr>
          <th class="col-id"><?= sort_link('id','ID', $sort,$dir) ?></th>
          <th><?= sort_link('name','Package Name', $sort,$dir) ?></th>
          <th class="col-speed">Speed</th>
          <th class="text-end col-price"><?= sort_link('price','Monthly Price', $sort,$dir) ?></th>
          <th class="col-desc">Description</th>
          <th class="text-end col-clients">Clients</th>
          <th class="text-end col-action">Action</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="text-center text-muted">No data</td></tr>
      <?php else: foreach($rows as $r): ?>
        <tr data-id="<?= (int)$r['id'] ?>">
          <td class="col-id"><?= (int)$r['id'] ?></td>
          <td><?= htmlspecialchars($r['name']) ?></td>
          <td class="col-speed"><?= htmlspecialchars((string)($r['speed'] ?? '')) ?></td>
          <td class="text-end col-price"><?= number_format((float)$r['price'], 2) ?></td>
          <td class="col-desc text-wrap small"><span class="desc-text"><?= htmlspecialchars((string)($r['description'] ?? '')) ?></span></td>
          <td class="text-end col-clients">
            <?php if ((int)$r['clients']>0): ?>
              <a class="btn btn-outline-secondary btn-sm" href="/public/clients.php?package_id=<?= (int)$r['id'] ?>">
                <?= (int)$r['clients'] ?>
              </a>
            <?php else: ?>
              <span class="text-muted">0</span>
            <?php endif; ?>
          </td>
          <td class="text-end col-action">
            <button class="btn btn-outline-primary btn-sm me-1 btn-edit"
                    data-id="<?= (int)$r['id'] ?>"
                    data-name="<?= htmlspecialchars($r['name']) ?>"
                    data-speed="<?= $has_speed ? htmlspecialchars((string)($r['speed'] ?? '')) : '' ?>"
                    data-price="<?= htmlspecialchars($r['price']) ?>"
                    data-desc="<?= htmlspecialchars($r['description'] ?? '') ?>">
              <i class="bi bi-pencil-square"></i>
            </button>
            <button class="btn btn-outline-danger btn-sm btn-delete" data-id="<?= (int)$r['id'] ?>">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($total_pages > 1): ?>
    <nav class="mt-3">
      <ul class="pagination pagination-sm justify-content-center">
        <li class="page-item <?= $page<=1?'disabled':'' ?>">
          <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>">Prev</a>
        </li>
        <?php
          $start = max(1,$page-2); $end=min($total_pages,$page+2);
          if (($end-$start+1)<5){ if($start==1){$end=min($total_pages,$start+4);} elseif($end==$total_pages){$start=max(1,$end-4);} }
          for($i=$start;$i<=$end;$i++):
        ?>
          <li class="page-item <?= $i==$page?'active':'' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$i])) ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= $page>=$total_pages?'disabled':'' ?>">
          <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>">Next</a>
        </li>
      </ul>
    </nav>
  <?php endif; ?>
</div>

<!-- Modal: Add/Edit -->
<div class="modal fade" id="pkgModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="pkgForm" autocomplete="off">
      <div class="modal-header">
        <h6 class="modal-title">Package</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="pkg-id">

        <div class="mb-2">
          <label class="form-label">Package Name</label>
          <input type="text" class="form-control" name="name" id="pkg-name" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Speed</label>
          <input type="text" class="form-control" name="speed" id="pkg-speed" placeholder="e.g. 10Mbps/5Mbps" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Monthly Price</label>
          <input type="number" step="0.01" min="0" class="form-control" name="price" id="pkg-price" required>
        </div>
        <div>
          <label class="form-label">Description (optional)</label>
          <textarea class="form-control" name="description" id="pkg-description" rows="2" placeholder="Details, speed notes, etc."></textarea>
        </div>
        <div class="form-text mt-1">
          <i class="bi bi-info-circle"></i>
          <span class="text-muted">Changing package name may require updating existing client assignments.</span>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
const API_UPSERT = '/api/package_upsert.php';
const API_DELETE = '/api/package_delete.php';

/* Toast */
function showToast(message, type='success', timeout=2500){
  const box = document.createElement('div');
  box.className = 'app-toast ' + (type==='success'?'success':'error');
  box.textContent = message || '';
  document.body.appendChild(box);
  setTimeout(()=> box.classList.add('hide'), timeout - 250);
  setTimeout(()=> box.remove(), timeout);
}
function simpleConfirm(msg){ return new Promise(r=> r(confirm(msg))); }

/* ===== Add/Edit Modal ===== */
document.addEventListener('DOMContentLoaded', ()=>{
  const modalEl = document.getElementById('pkgModal');
  if (!modalEl || !window.bootstrap || !bootstrap.Modal) return;
  if (modalEl.parentElement !== document.body) document.body.appendChild(modalEl);
  const modal = new bootstrap.Modal(modalEl);
  const form  = document.getElementById('pkgForm');
  const speedInput = document.getElementById('pkg-speed');
  const descInput = document.getElementById('pkg-description');

  document.getElementById('btn-add')?.addEventListener('click', (e)=>{
    e.preventDefault();
    form.reset();
    form.querySelector('#pkg-id').value = '';
    if (speedInput) speedInput.value = '';
    if (descInput) descInput.value = '';
    modal.show();
  });

  document.querySelectorAll('.btn-edit').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      form.reset();
      form.querySelector('#pkg-id').value   = btn.dataset.id || '';
      form.querySelector('#pkg-name').value = btn.dataset.name || '';
      if (speedInput) speedInput.value      = btn.dataset.speed || '';
      form.querySelector('#pkg-price').value= btn.dataset.price || '';
      if (descInput) descInput.value = btn.dataset.desc || '';
      modal.show();
    });
  });

  form.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const fd = new FormData(form);
    const data = Object.fromEntries(fd.entries());
    const name = (data.name||'').trim();
    const speed= (data.speed||'').trim();
    const price= parseFloat(data.price||'0');
    const desc = (data.description||'').trim();
    if (!name) return showToast('Package name is required','error');
    if (!speed) return showToast('Speed is required','error');
    if (isNaN(price) || price<0) return showToast('Enter a valid price (>= 0)','error');

    try{
      const res = await fetch(API_UPSERT, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ id: data.id||null, name, speed, price, description: desc || null })
      });
      const j = await res.json();
      if (j.ok){ showToast(j.message||'Saved'); setTimeout(()=> location.reload(), 500); }
      else { showToast(j.error||'Failed','error'); }
    }catch(_){ showToast('Request failed','error'); }
  });

  /* ===== Delete ===== */
  document.querySelectorAll('.btn-delete').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const id = parseInt(btn.dataset.id,10);
      if (!id) return;
      const ok = await simpleConfirm('Are you sure? If clients are assigned, delete will be blocked.');
      if (!ok) return;
      try{
        const res = await fetch(API_DELETE, {
          method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ id })
        });
        const j = await res.json();
        if (j.ok){ showToast(j.message||'Deleted'); setTimeout(()=> location.reload(), 400); }
        else { showToast(j.error||'Failed','error'); }
      }catch(_){ showToast('Request failed','error'); }
    });
  });

});
</script>

<?php include __DIR__ . '/../partials/partials_footer.php'; ?>
