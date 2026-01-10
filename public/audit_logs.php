<?php
// /public/audit_logs.php
// বাংলা: Audit Logs — dynamic schema aware (cached), old/new → details merge, filters + sorting + export
require_once __DIR__ . '/../app/PHP/audit_logs_logic.php';
include __DIR__ . '/../partials/partials_header.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="/public/css/audit_logs.css">

<div class="container-fluid py-3">
  <?php
    // (বাংলা) ক্লায়েন্ট ভিউতে ফেরার জন্য ব্যাক বাটন; client_id পেলে কুয়েরিতে যোগ করি
    $backUrl = '/public/client_view.php';
    if (!empty($_GET['client_id']) && ctype_digit((string)$_GET['client_id'])) {
      $backUrl .= '?id='.(int)$_GET['client_id'];
    }
  ?>
  <div class="d-flex justify-content-end mb-2">
    <a class="btn btn-outline-secondary btn-sm" href="<?= h($backUrl) ?>">
      <i class="bi bi-arrow-left"></i> Back
    </a>
  </div>

  <!-- Filters -->
  <form class="card shadow-sm mb-3" method="get">
    <div class="card-body">
      <div class="row g-2 align-items-end">

        <!-- Keep sort/dir -->
        <input type="hidden" name="sort" value="<?= h($sort_key) ?>">
        <input type="hidden" name="dir"  value="<?= h($dir_raw) ?>">

        <div class="col-12 col-md-3">
          <label class="form-label mb-1">Search</label>
          <input type="text" name="q" value="<?= h($q) ?>" class="form-control form-control-sm" placeholder="Client / PPPoE / Event / JSON / Actor">
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label mb-1">Action</label>
          <select name="action" class="form-select form-select-sm" <?= $colAction ? '' : 'disabled' ?>>
            <option value="">All</option>
            <?php foreach($actions as $a): ?>
              <option value="<?= h($a) ?>" <?= $action===$a?'selected':'' ?>><?= h($a) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if(!$colAction): ?><div class="form-text small text-danger">Action column not found</div><?php endif; ?>
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label mb-1">Router</label>
          <select name="router" class="form-select form-select-sm" <?= $HAS_CLIENTS && $HAS_ROUTERS ? '' : 'disabled' ?>>
            <option value="">All</option>
            <?php foreach($rtrs as $r): ?>
              <option value="<?= (int)$r['id'] ?>" <?= ($router!=='' && (int)$router===(int)$r['id'])?'selected':'' ?>>
                <?= h($r['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label mb-1">Package</label>
          <select name="package" class="form-select form-select-sm" <?= $HAS_CLIENTS && $HAS_PACKAGES ? '' : 'disabled' ?>>
            <option value="">All</option>
            <?php foreach($pkgs as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= ($package!=='' && (int)$package===(int)$p['id'])?'selected':'' ?>>
                <?= h($p['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label mb-1">Area</label>
          <input name="area" value="<?= h($area) ?>" list="areas" class="form-control form-control-sm" placeholder="Area" <?= $HAS_CLIENTS ? '' : 'disabled' ?>>
          <datalist id="areas">
            <?php foreach($areas as $ar): ?><option value="<?= h($ar) ?>"></option><?php endforeach; ?>
          </datalist>
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label mb-1">Date From</label>
          <input type="date" name="df" value="<?= h($df) ?>" class="form-control form-control-sm" <?= $colCreated ? '' : 'disabled' ?>>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label mb-1">Date To</label>
          <input type="date" name="dt" value="<?= h($dt) ?>" class="form-control form-control-sm" <?= $colCreated ? '' : 'disabled' ?>>
        </div>

        <div class="col-6 col-md-2">
          <label class="form-label mb-1">Per Page</label>
          <select name="limit" class="form-select form-select-sm">
            <?php foreach([10,25,50,100] as $L): ?>
              <option value="<?= $L ?>" <?= $limit==$L?'selected':'' ?>><?= $L ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-6 col-md-2 d-grid">
          <label class="form-label mb-1 invisible d-none d-md-block">_</label>
          <button class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Filter</button>
        </div>

        <!-- Export -->
        <div class="col-6 col-md-2 d-grid">
          <label class="form-label mb-1 invisible d-none d-md-block">_</label>
          <button class="btn btn-success btn-sm" name="export" value="csv" formtarget="_blank">
            <i class="bi bi-filetype-csv"></i> Export CSV
          </button>
        </div>
        <div class="col-6 col-md-2 d-grid">
          <label class="form-label mb-1 invisible d-none d-md-block">_</label>
          <button class="btn btn-primary btn-sm" name="export" value="xls" formtarget="_blank">
            <i class="bi bi-file-earmark-excel"></i> Export Excel
          </button>
        </div>

      </div>
    </div>
  </form>

  <!-- Table -->
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle">
        <thead class="table-dark">
          <tr>
            <th><?= sort_link('id','#', $sort_key, $dir_raw) ?></th>
            <th><?= sort_link('created','When', $sort_key, $dir_raw) ?></th>
            <th><?= sort_link('action','Action', $sort_key, $dir_raw) ?></th>
            <th><?= sort_link('entity','Entity', $sort_key, $dir_raw) ?></th>
            <?php if($HAS_CLIENTS): ?>
              <th><?= sort_link('client','Client', $sort_key, $dir_raw) ?></th>
            <?php else: ?>
              <th>Client</th>
            <?php endif; ?>
            <?php if($HAS_ROUTERS): ?>
              <th><?= sort_link('router','Router', $sort_key, $dir_raw) ?></th>
            <?php else: ?>
              <th>Router</th>
            <?php endif; ?>
            <th>Details</th>
            <th>Actor</th>
            <th>IP</th>
          </tr>
        </thead>
        <tbody>
        <?php if($rows): foreach($rows as $r):
          // বাংলা: বড় JSON হলে ট্রাঙ্কেট; তারপর prettify
          $pretty = $r['details'];
          if (is_string($pretty) && strlen($pretty) > 65536) {
            $pretty = substr($pretty, 0, 65536) . "\n/* truncated */";
          }
          $norm = normalize_audit_details($r['details'] ?? '');
          if (is_array($norm)) {
            $pretty = json_encode($norm, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
          } else {
            $j = json_decode($r['details'] ?? '', true);
            if (json_last_error() === JSON_ERROR_NONE) {
              $pretty = json_encode($j, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }
          }
          $badge = 'secondary';
          if (($r['action'] ?? '') !== '') {
            $act = strtolower((string)$r['action']);
            if (str_contains($act,'add') || str_contains($act,'create')) $badge='success';
            elseif (str_contains($act,'update') || str_contains($act,'edit')) $badge='primary';
            elseif (str_contains($act,'delete') || str_contains($act,'remove')) $badge='danger';
            elseif (str_contains($act,'toggle') || str_contains($act,'status')) $badge='warning';
          }
        ?>
          <tr>
            <td class="text-muted mono"><?= (int)$r['id'] ?></td>
            <td class="mono"><?= h($r['created_at'] ?: '-') ?></td>
            <td><span class="badge bg-<?= h($badge) ?>"><?= h($r['action'] ?: '-') ?></span></td>
            <td><?= ($r['entity_type']!==null ? h($r['entity_type']) : '—') ?> <?= $r['entity_id']?('#'.(int)$r['entity_id']):'' ?></td>
            <td>
              <?php if ($HAS_CLIENTS && !empty($r['client_name'])): ?>
                <a class="text-decoration-none" href="client_view.php?id=<?= (int)$r['entity_id'] ?>">
                  <?= h($r['client_name']) ?>
                </a>
                <div class="text-muted small"><?= h($r['pppoe_id'] ?: '') ?></div>
                <?php if (($r['package_name'] ?? null) || ($r['area'] ?? null)): ?>
                  <div class="text-muted small">
                    <?= h($r['package_name'] ?: '') ?><?= (($r['package_name'] ?? '') && ($r['area'] ?? ''))?' · ':'' ?><?= h($r['area'] ?: '') ?>
                  </div>
                <?php endif; ?>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td><?= h($r['router_name'] ?: '—') ?></td>
            <td style="min-width:240px;">
              <?php if(strlen((string)($r['details'] ?? ''))): ?>
                <details>
                  <summary class="text-primary small">view</summary>
                  <pre class="details mb-0"><?= h($pretty) ?></pre>
                </details>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="small">
              <?php
                $actor = trim(($r['user_name'] ?? ''));
                $uidVal = $r['user_id'] ?? null;
                $uid = is_numeric($uidVal) ? (int)$uidVal : null;
                if ($uid === 0) {
                  echo 'System automatic';
                } elseif ($actor !== '' && $uid) {
                  echo h($actor) . " (#" . h((string)$uid) . ")";
                } elseif ($uid) {
                  echo "#" . h((string)$uid);
                } else {
                  echo '-';
                }
              ?>
            </td>
            <td>
              <code><?= h($r['ip'] ?? '') ?></code>
              <div class="text-muted text-trunc-ua" title="<?= h($r['ua'] ?? '') ?>"><?= h($r['ua'] ?? '') ?></div>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="9" class="text-center text-muted py-4">No logs found</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pagination -->
  <?php if($total_pages>1):
    $qsPrev = $_GET; $qsPrev['page'] = max(1,$page-1);
    $qsNext = $_GET; $qsNext['page'] = min($total_pages,$page+1);
    $start = max(1,$page-2); $end = min($total_pages,$page+2);
    if(($end-$start)<4){ $end=min($total_pages,$start+4); $start=max(1,$end-4); }
  ?>
    <nav class="mt-3">
      <ul class="pagination pagination-sm justify-content-center">
        <li class="page-item <?= $page<=1?'disabled':'' ?>">
          <a class="page-link" href="?<?= http_build_query($qsPrev) ?>">Previous</a>
        </li>
        <?php for($i=$start;$i<=$end;$i++): $qsi=$_GET; $qsi['page']=$i; ?>
          <li class="page-item <?= $i==$page?'active':'' ?>">
            <a class="page-link" href="?<?= http_build_query($qsi) ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item <?= $page>=$total_pages?'disabled':'' ?>">
          <a class="page-link" href="?<?= http_build_query($qsNext) ?>">Next</a>
        </li>
      </ul>
    </nav>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/../partials/partials_footer.php'; ?>
