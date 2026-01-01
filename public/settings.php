<?php
// /public/settings.php
// Location manager: Area, Sub Zone, Box (single view per query param).

declare(strict_types=1);

require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/acl.php';
require_once __DIR__ . '/../app/csrf_compat.php';
require_once __DIR__ . '/../app/location_options.php';

if (function_exists('require_perm')) {
    require_perm('settings.view');
}

$typeMeta = [
    'area' => [
        'label'       => 'Zone',
        'singular'    => 'Zone',
        'icon'        => 'bi-geo-alt',
        'description' => 'Configure service zones to keep client records organized.',
        'columns'     => [
            ['key' => 'label',   'title' => 'Zone Name',  'type' => 'text'],
            ['key' => 'details', 'title' => 'Details',    'type' => 'details'],
        ],
    ],
    'sub_zone' => [
        'label'       => 'Sub Zone',
        'singular'    => 'Sub Zone',
        'icon'        => 'bi-diagram-3',
        'description' => 'Divide a zone into sub regions for better tracking.',
        'columns'     => [
            ['key' => 'label',       'title' => 'Sub Zone', 'type' => 'text'],
            ['key' => 'parent_area', 'title' => 'Zone',     'type' => 'parent_area'],
            ['key' => 'details',     'title' => 'Details',  'type' => 'details'],
        ],
    ],
    'box' => [
        'label'       => 'Box',
        'singular'    => 'Box',
        'icon'        => 'bi-hdd-stack',
        'description' => 'Register connection boxes against their zone/sub zone.',
        'columns'     => [
            ['key' => 'label',          'title' => 'Box',      'type' => 'text'],
            ['key' => 'parent_area',    'title' => 'Zone',     'type' => 'parent_area'],
            ['key' => 'parent_sub_zone','title' => 'Sub Zone', 'type' => 'parent_sub_zone'],
            ['key' => 'details',        'title' => 'Details',  'type' => 'details'],
        ],
    ],
];

$type = location_option_sanitize_type((string)($_GET['type'] ?? $_GET['loc'] ?? 'area'));
if ($type === '' || !isset($typeMeta[$type])) {
    $type = 'area';
}

$pdo = db();
$allData = [];
foreach (array_keys($typeMeta) as $tk) {
    $allData[$tk] = location_option_list_full($pdo, $tk);
}
$rows = $allData[$type] ?? [];

$csrfToken  = csrf_ensure_token();
$canManage  = acl_can('settings.manage');
$areasList  = array_values(array_map(fn($row) => ['label' => $row['label']], $allData['area'] ?? []));
$subZones   = array_values(array_map(fn($row) => ['label' => $row['label'], 'parent_area' => $row['parent_area']], $allData['sub_zone'] ?? []));

$_active    = 'settings_location_' . $type;
$page_title = 'Location Settings - ' . ($typeMeta[$type]['label'] ?? 'Zone');

$postNotice = '';
$postError = '';
$fieldError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    if (!csrf_verify()) {
        $postError = 'Invalid CSRF token.';
    } else {
        $postType = location_option_sanitize_type((string)($_POST['type'] ?? $type));
        if ($postType === '') $postType = $type;
        $id = (int)($_POST['id'] ?? 0);
        $label = trim((string)($_POST['label'] ?? ''));
        $details = trim((string)($_POST['details'] ?? ''));
        $parentArea = trim((string)($_POST['parent_area'] ?? ''));
        $parentSub = trim((string)($_POST['parent_sub_zone'] ?? ''));
        if ($label === '') {
            $postError = 'Name is required.';
        } else {
            try {
                $dupSql = "SELECT id FROM client_location_options WHERE type = :type AND LOWER(label) = LOWER(:label)";
                $dupParams = [':type' => $postType, ':label' => $label];
                if ($postType === 'sub_zone') {
                    $dupSql .= " AND COALESCE(parent_area,'') = :parent_area";
                    $dupParams[':parent_area'] = $parentArea;
                } elseif ($postType === 'box') {
                    $dupSql .= " AND COALESCE(parent_area,'') = :parent_area AND COALESCE(parent_sub_zone,'') = :parent_sub_zone";
                    $dupParams[':parent_area'] = $parentArea;
                    $dupParams[':parent_sub_zone'] = $parentSub;
                }
                if ($id > 0) {
                    $dupSql .= " AND id <> :id";
                    $dupParams[':id'] = $id;
                }
                $dupSt = $pdo->prepare($dupSql . " LIMIT 1");
                $dupSt->execute($dupParams);
                if ($dupSt->fetchColumn()) {
                    $postError = 'This entry already exists in the database.';
                    $fieldError = $postError;
                }
                if ($postError === '') {
                    if ($id > 0) {
                        $result = location_option_update($pdo, $id, $label, $details, $parentArea ?: null, $parentSub ?: null);
                    } else {
                        $result = location_option_store($pdo, $postType, $label, $details, $parentArea ?: null, $postType === 'box' ? ($parentSub ?: null) : null);
                    }
                    if (empty($result['ok'])) {
                        $postError = $result['error'] ?? 'Save failed.';
                    }
                }
                if ($postError === '') {
                    header('Location: /settings.php?type=' . urlencode($postType) . '&saved=1');
                    exit;
                }
            } catch (Throwable $e) {
                $postError = $e->getMessage();
            }
        }
    }
}

include __DIR__ . '/../partials/partials_header.php';
?>
<style>
.location-card thead th {
  background:#0f2738;
  color:#fff;
  text-transform:uppercase;
  font-size:.78rem;
}
.location-controls label {
  font-size:.8rem;
  text-transform:uppercase;
  font-weight:600;
  color:#6c757d;
}
.location-status { min-height:42px; }
</style>
<div class="container-fluid py-4">
  <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center mb-3">
    <div>
      <h4 class="mb-1"><i class="bi <?= h($typeMeta[$type]['icon']) ?>"></i> <?= h($typeMeta[$type]['label']) ?></h4>
      <p class="text-muted mb-0"><?= h($typeMeta[$type]['description']) ?></p>
    </div>
    <?php if ($canManage): ?>
      <button class="btn btn-primary" id="btnAddLocation"><i class="bi bi-plus-circle"></i> Add <?= h($typeMeta[$type]['singular']) ?></button>
    <?php endif; ?>
  </div>

  <div id="locStatus" class="alert d-none location-status" role="alert"></div>
  <?php if ($postError): ?>
    <div class="alert alert-danger location-status"><?= h($postError) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm location-card">
    <div class="card-body">
      <div class="d-flex flex-column flex-md-row align-items-md-center gap-3 location-controls mb-3">
        <div class="d-flex align-items-center gap-2">
          <label class="mb-0">Show</label>
          <select class="form-select form-select-sm w-auto" data-loc-perpage>
            <option value="10">10</option>
            <option value="25">25</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
          <span class="text-muted small text-uppercase">Entries</span>
        </div>
        <div class="d-flex align-items-center gap-2 ms-md-auto">
          <label class="mb-0">Search</label>
          <input type="text" class="form-control form-control-sm" placeholder="Type to filter..." data-loc-search>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
          <thead>
            <tr>
              <th style="width:70px;">Serial</th>
              <?php foreach ($typeMeta[$type]['columns'] as $col): ?>
                <th><?= h($col['title']) ?></th>
              <?php endforeach; ?>
              <?php if ($canManage): ?><th class="text-center" style="width:110px;">Action</th><?php endif; ?>
            </tr>
          </thead>
          <tbody data-loc-table data-action-enabled="<?= $canManage ? '1':'0' ?>">
            <tr><td colspan="<?= count($typeMeta[$type]['columns']) + 1 + ($canManage?1:0) ?>" class="text-center text-muted py-4">Loading...</td></tr>
          </tbody>
        </table>
      </div>

      <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mt-3 gap-2">
        <small class="text-muted" data-loc-summary>Showing 0 entries</small>
        <nav>
          <ul class="pagination pagination-sm mb-0" data-loc-pagination></ul>
        </nav>
      </div>
    </div>
  </div>
</div>

<?php if ($canManage): ?>
<div class="modal fade" id="locOptionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="locOptionForm" method="POST" action="/settings.php?type=<?= h($type) ?>">
      <div class="modal-header">
        <h5 class="modal-title" id="locModalTitle">Add Entry</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="locTypeField" name="type" value="<?= h($type) ?>">
        <input type="hidden" id="locIdField" name="id">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
        <div class="row g-3">
          <div class="col-12 d-none" data-field="parent_area">
            <label class="form-label">Zone</label>
            <select id="parentAreaSelect" name="parent_area" class="form-select">
              <option value="">Select Zone</option>
            </select>
          </div>
          <div class="col-12 d-none" data-field="parent_sub_zone">
            <label class="form-label">Sub Zone</label>
            <select id="parentSubZoneSelect" name="parent_sub_zone" class="form-select">
              <option value="">Select Sub Zone</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label" id="locNameLabel">Name</label>
            <input type="text" class="form-control" id="locLabelInput" name="label" required maxlength="120">
            <div id="locDupNotice" class="form-text text-danger<?= $fieldError ? '' : ' d-none' ?>"><?= h($fieldError) ?></div>
          </div>
          <div class="col-12">
            <label class="form-label">Details (optional)</label>
            <textarea class="form-control" id="locDetailInput" name="details" rows="3" placeholder="Notes or identifier"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-danger" id="locClearBtn"><i class="bi bi-eraser"></i> Clear</button>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary" id="locSaveBtn"><i class="bi bi-save2"></i> Save</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
(function(){
  const hiddenType = (document.getElementById('locTypeField')?.value || '').trim();
  const TYPE = (hiddenType || <?= json_encode($type) ?> || 'area').trim() || 'area';
  const meta = <?= json_encode($typeMeta[$type], JSON_UNESCAPED_UNICODE) ?>;
  const CAN_MANAGE = <?= $canManage ? 'true' : 'false' ?>;
  const csrf = <?= json_encode($csrfToken, JSON_UNESCAPED_UNICODE) ?>;
  let data = <?= json_encode(array_values($rows), JSON_UNESCAPED_UNICODE) ?>;
  let areas = <?= json_encode($areasList, JSON_UNESCAPED_UNICODE) ?>;
  let subZones = <?= json_encode($subZones, JSON_UNESCAPED_UNICODE) ?>;

  const state = {page: 1, perPage: 10, search: ''};
  const tbody = document.querySelector('[data-loc-table]');
  const summaryEl = document.querySelector('[data-loc-summary]');
  const pagEl = document.querySelector('[data-loc-pagination]');
  const perPageSel = document.querySelector('[data-loc-perpage]');
  const searchInput = document.querySelector('[data-loc-search]');
  const statusBox = document.getElementById('locStatus');
  const dupNotice = document.getElementById('locDupNotice');
  const saveBtn = document.getElementById('locSaveBtn');

  const showStatus = (kind, msg) => {
    const isDelete = kind === 'delete';
    const toastType = (kind === 'error' || isDelete) ? 'danger' : 'success';
    const toastTitle = isDelete ? 'Deleted' : null;
    if (window.globalToast) {
      window.globalToast(msg, toastType, 3200, toastTitle);
      return;
    }
    if (kind !== 'error' && !isDelete) {
      clearTimeout(showStatus._timer);
      showStatus._timer = setTimeout(() => showStatus(kind, msg), 120);
      return;
    }
    if (!statusBox) return;
    statusBox.className = 'alert location-status alert-danger';
    statusBox.textContent = msg;
    statusBox.classList.remove('d-none');
    clearTimeout(showStatus._hideTimer);
    showStatus._hideTimer = setTimeout(() => statusBox.classList.add('d-none'), 3500);
  };

  <?php if (isset($_GET['saved']) && $_GET['saved'] === '1'): ?>
    showStatus('ok', 'Saved successfully.');
    try {
      const url = new URL(window.location.href);
      url.searchParams.delete('saved');
      window.history.replaceState({}, document.title, url.toString());
    } catch (e) {}
  <?php endif; ?>

  const esc = (str) => String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');

  function filtered(){
    const term = state.search.trim().toLowerCase();
    if (!term) return data.slice();
    return data.filter(row => {
      return ['label','details','parent_area','parent_sub_zone'].some(key => (row[key] || '').toLowerCase().includes(term));
    });
  }

  function formatCell(col, row){
    const value = row[col.key] ?? '';
    if (col.type === 'details') {
      return value ? esc(value) : '<span class="text-muted">-</span>';
    }
    if (col.key === 'parent_area' || col.key === 'parent_sub_zone') {
      return value ? esc(value) : '<span class="text-muted">Unassigned</span>';
    }
    return esc(value);
  }

  function render(){
    if (!tbody) return;
    const rows = filtered();
    const perPage = state.perPage;
    const totalPages = Math.max(1, Math.ceil(Math.max(rows.length, 1) / perPage));
    if (state.page > totalPages) state.page = totalPages;
    const startIdx = (state.page - 1) * perPage;
    const pageRows = rows.slice(startIdx, startIdx + perPage);
    if (!pageRows.length){
      tbody.innerHTML = `<tr><td colspan="${meta.columns.length + (CAN_MANAGE?2:1)}" class="text-center text-muted py-4">No entries found.</td></tr>`;
    } else {
      tbody.innerHTML = pageRows.map((row, idx) => {
        const serial = startIdx + idx + 1;
        const cols = meta.columns.map(col => `<td>${formatCell(col, row)}</td>`).join('');
        const action = CAN_MANAGE ? `<td class="text-center">
          <button class="btn btn-link text-success p-0 me-2" data-loc-action="edit" data-id="${row.id}"><i class="bi bi-pencil-square"></i></button>
          <button class="btn btn-link text-danger p-0" data-loc-action="delete" data-id="${row.id}"><i class="bi bi-trash"></i></button>
        </td>` : '';
        return `<tr data-id="${row.id}"><td>${serial}</td>${cols}${action}</tr>`;
      }).join('');
    }
    if (summaryEl){
      if (!rows.length) summaryEl.textContent = 'Showing 0 entries';
      else summaryEl.textContent = `Showing ${startIdx+1} to ${startIdx + pageRows.length} of ${rows.length} entries`;
    }
    if (pagEl){
      const parts = [];
      const add = (label, target, disabled=false, active=false) => {
        parts.push(`<li class="page-item${disabled?' disabled':''}${active?' active':''}">
          <a class="page-link" href="#" ${disabled?'':`data-page="${target}"`}>${label}</a>
        </li>`);
      };
      add('First', 1, state.page===1);
      add('Prev', Math.max(1,state.page-1), state.page===1);
      for (let p=Math.max(1,state.page-2); p<=Math.min(totalPages,state.page+2); p++){
        add(String(p), p, false, p===state.page);
      }
      add('Next', Math.min(totalPages,state.page+1), state.page===totalPages);
      add('Last', totalPages, state.page===totalPages);
      pagEl.innerHTML = parts.join('');
    }
  }

  perPageSel?.addEventListener('change', () => {
    const v = parseInt(perPageSel.value, 10);
    state.perPage = !isNaN(v) && v > 0 ? v : 10;
    state.page = 1;
    render();
  });
  searchInput?.addEventListener('input', () => {
    state.search = searchInput.value || '';
    state.page = 1;
    render();
  });
  pagEl?.addEventListener('click', evt => {
    const link = evt.target.closest('[data-page]');
    if (!link) return;
    evt.preventDefault();
    const target = parseInt(link.dataset.page, 10);
    if (!isNaN(target)) {
      state.page = target;
      render();
    }
  });

  render();
  refreshDataset().catch(() => {});

  if (!CAN_MANAGE) return;

  const modalEl = document.getElementById('locOptionModal');
  const form = document.getElementById('locOptionForm');
  const idField = document.getElementById('locIdField');
  const labelInput = document.getElementById('locLabelInput');
  const detailInput = document.getElementById('locDetailInput');
  const nameLabel = document.getElementById('locNameLabel');
  const parentAreaWrap = document.querySelector('[data-field="parent_area"]');
  const parentSubWrap = document.querySelector('[data-field="parent_sub_zone"]');
  const parentAreaSelect = document.getElementById('parentAreaSelect');
  const parentSubSelect = document.getElementById('parentSubZoneSelect');
  const clearBtn = document.getElementById('locClearBtn');
  const btnAdd = document.getElementById('btnAddLocation');
  let modalInstance = null;
  let mode = 'add';

  function normalizeLabel(val){
    return String(val || '').trim().toLowerCase();
  }

  function currentDupExists(){
    const label = normalizeLabel(labelInput?.value || '');
    if (!label) return false;
    const parentArea = normalizeLabel(parentAreaSelect?.value || '');
    const parentSub = normalizeLabel(parentSubSelect?.value || '');
    const currentId = parseInt(idField?.value || '0', 10) || 0;
    return data.some(row => {
      if (currentId && String(row.id) === String(currentId)) return false;
      if (normalizeLabel(row.label) !== label) return false;
      if (TYPE === 'sub_zone') {
        return normalizeLabel(row.parent_area) === parentArea;
      }
      if (TYPE === 'box') {
        return normalizeLabel(row.parent_area) === parentArea && normalizeLabel(row.parent_sub_zone) === parentSub;
      }
      return true;
    });
  }

  function updateDupNotice(){
    if (!dupNotice) return;
    const hasDup = currentDupExists();
    dupNotice.textContent = hasDup ? 'This entry already exists in the database.' : '';
    dupNotice.classList.toggle('d-none', !hasDup);
    if (saveBtn) saveBtn.disabled = hasDup;
  }

  const ensureModal = () => {
    if (!modalEl) return null;
    if (modalInstance) return modalInstance;
    if (window.bootstrap && window.bootstrap.Modal) {
      modalInstance = new window.bootstrap.Modal(modalEl);
    }
    return modalInstance;
  };

  function populateAreas(selected=''){
    if (!parentAreaSelect) return;
    parentAreaSelect.innerHTML = '<option value="">Select Zone</option>';
    areas.forEach(area => {
      const opt = document.createElement('option');
      opt.value = area.label;
      opt.textContent = area.label;
      if (area.label === selected) opt.selected = true;
      parentAreaSelect.appendChild(opt);
    });
  }

  function populateSubZones(zoneValue, selected=''){
    if (!parentSubSelect) return;
    parentSubSelect.innerHTML = '<option value="">Select Sub Zone</option>';
    const filtered = subZones.filter(sz => !zoneValue || sz.parent_area === zoneValue);
    filtered.forEach(sz => {
      const opt = document.createElement('option');
      opt.value = sz.label;
      opt.textContent = sz.label;
      if (sz.label === selected) opt.selected = true;
      parentSubSelect.appendChild(opt);
    });
    parentSubSelect.disabled = filtered.length === 0;
  }

  parentAreaSelect?.addEventListener('change', () => {
    if (TYPE === 'box') {
      populateSubZones(parentAreaSelect.value, '');
    }
    updateDupNotice();
  });
  parentSubSelect?.addEventListener('change', updateDupNotice);
  labelInput?.addEventListener('input', updateDupNotice);

  clearBtn?.addEventListener('click', () => {
    form?.reset();
    if (parentAreaSelect) parentAreaSelect.value='';
    if (parentSubSelect) parentSubSelect.value='';
    parentSubSelect && (parentSubSelect.disabled = false);
    labelInput?.focus();
    updateDupNotice();
  });

  function openModal(record){
    mode = record ? 'edit' : 'add';
    idField.value = record ? record.id : '';
    labelInput.value = record ? record.label || '' : '';
    detailInput.value = record ? record.details || '' : '';
    const typeField = document.getElementById('locTypeField');
    if (typeField) typeField.value = TYPE;
    nameLabel.textContent = meta.columns[0]?.title || 'Name';
    const showArea = (TYPE === 'sub_zone' || TYPE === 'box');
    const showSub = (TYPE === 'box');
    parentAreaWrap?.classList.toggle('d-none', !showArea);
    parentSubWrap?.classList.toggle('d-none', !showSub);
    if (showArea) {
      populateAreas(record?.parent_area || '');
      if (showSub) {
        populateSubZones(record?.parent_area || '', record?.parent_sub_zone || '');
      }
    }
    const modal = ensureModal();
    if (modal) modal.show();
    labelInput?.focus();
    const title = document.getElementById('locModalTitle');
    if (title) title.textContent = (mode === 'add' ? 'Add ' : 'Edit ') + meta.singular;
    updateDupNotice();
  }

  async function fetchFull(type){
    const res = await fetch(`/ajax/location_options.php?type=${encodeURIComponent(type)}&full=1`, {cache:'no-store'});
    const json = await res.json();
    if (!res.ok || !json.ok) throw new Error(json.error || 'Failed to load list');
    return Array.isArray(json.items) ? json.items : [];
  }

  async function refreshDataset(){
    try {
      data = await fetchFull(TYPE);
      if (TYPE !== 'area') {
        const zoneRows = await fetchFull('area');
        areas = zoneRows.map(row => ({label: row.label}));
      }
      if (TYPE === 'box') {
        const subRows = await fetchFull('sub_zone');
        subZones = subRows.map(row => ({label: row.label, parent_area: row.parent_area}));
      }
      state.page = 1;
      render();
    } catch (err) {
      showStatus('error', err.message || 'Failed to refresh data.');
    }
  }

  async function confirmDelete(label){
    return new Promise(resolve => {
      const modalEl = document.createElement('div');
      modalEl.className = 'modal fade';
      modalEl.tabIndex = -1;
      modalEl.innerHTML = `
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header bg-danger text-white">
              <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Confirm Delete</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <p class="mb-0">Are you sure you want to delete <strong>${label}</strong>? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-danger" data-confirm>Delete</button>
            </div>
          </div>
        </div>`;
      document.body.appendChild(modalEl);
      const modal = new bootstrap.Modal(modalEl);
      modalEl.querySelector('[data-confirm]').addEventListener('click', () => { modal.hide(); resolve(true); });
      modalEl.addEventListener('hidden.bs.modal', () => { resolve(false); modalEl.remove(); }, {once:true});
      modal.show();
    });
  }

  async function deleteOption(id){
    const res = await fetch('/ajax/location_options.php', {
      method: 'DELETE',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({id, csrf_token: csrf})
    });
    const json = await res.json();
    if (!res.ok || !json.ok) throw new Error(json.error || 'Delete failed.');
  }

  async function deleteSubTree(parentLabel, type, matchField){
    const rows = await fetchFull(type);
    const filtered = rows.filter(row => row[matchField] === parentLabel);
    for (const row of filtered) {
      await deleteOption(row.id);
    }
  }

  async function hasChildren(record){
    if (TYPE === 'area') {
      const [subRows, boxRows] = await Promise.all([fetchFull('sub_zone'), fetchFull('box')]);
      const hasSub = subRows.some(row => row.parent_area === record.label);
      const hasBox = boxRows.some(row => row.parent_area === record.label);
      return hasSub || hasBox;
    }
    if (TYPE === 'sub_zone') {
      const boxRows = await fetchFull('box');
      return boxRows.some(row => row.parent_sub_zone === record.label);
    }
    return false;
  }

  async function handleDelete(id){
    const record = data.find(r => String(r.id) === String(id));
    if (!record) {
      showStatus('error','Entry already removed.');
      return;
    }
    try {
      if (await hasChildren(record)) {
        if (TYPE === 'area') {
          showStatus('error','Delete sub zones and boxes under this area first.');
        } else if (TYPE === 'sub_zone') {
          showStatus('error','Delete boxes under this sub zone first.');
        } else {
          showStatus('error','Delete child items first.');
        }
        return;
      }
    } catch (err) {
      showStatus('error', err.message || 'Failed to check dependencies.');
      return;
    }
    const confirmed = await confirmDelete(record.label);
    if (!confirmed) return;
    try {
      await deleteOption(id);
      showStatus('delete', 'Delete successfully.');
      await refreshDataset();
    } catch (err) {
      showStatus('error', err.message || 'Delete failed.');
    }
  }

  btnAdd?.addEventListener('click', () => openModal(null));

  tbody?.addEventListener('click', evt => {
    const actionBtn = evt.target.closest('[data-loc-action]');
    if (!actionBtn) return;
    const action = actionBtn.dataset.locAction;
    const id = actionBtn.dataset.id;
    if (action === 'edit') {
      const record = data.find(r => String(r.id) === String(id));
      if (!record) { showStatus('error','Entry not found.'); return; }
      openModal(record);
    }
    if (action === 'delete') {
      handleDelete(id);
    }
  });

  form?.addEventListener('submit', evt => {
    const label = (labelInput.value || '').trim();
    const parentArea = parentAreaSelect && !parentAreaWrap.classList.contains('d-none') ? parentAreaSelect.value.trim() : '';
    const parentSub = parentSubSelect && !parentSubWrap.classList.contains('d-none') ? parentSubSelect.value.trim() : '';
    if (!label) {
      evt.preventDefault();
      alert('Please enter a name.');
      labelInput.focus();
      return;
    }
    if (TYPE === 'sub_zone' && parentArea === '') {
      evt.preventDefault();
      alert('Select a zone first.');
      parentAreaSelect.focus();
      return;
    }
    if (TYPE === 'box' && (parentArea === '' || parentSub === '')) {
      evt.preventDefault();
      alert('Select both zone and sub zone.');
      return;
    }
    if (currentDupExists()) {
      evt.preventDefault();
      updateDupNotice();
      labelInput.focus();
      return;
    }
    saveBtn?.setAttribute('disabled', 'disabled');
    if (saveBtn) saveBtn.textContent = 'Saving...';
    if (mode === 'add') {
      idField.value = '';
    }
  });
})();
</script>

<?php include __DIR__ . '/../partials/partials_footer.php'; ?>
