<?php
// OLT MAC Table page
require_once __DIR__ . '/../app/PHP/olt_mac_table_logic.php';

$isAjax = (string)($_GET['ajax'] ?? '') === '1';

function render_olt_table_block(array $groupedMacs, array $clientMacCache, int $filterOlt): void
{
?>
  <div class="container-fluid olt-table-area">
    <div class="table-responsive olt-table-wrap">
      <?php if ($filterOlt <= 0): ?>
        <!-- <div class="p-4 text-center text-muted">দয়া করে প্রথমে OLT সিলেক্ট করুন।</div> -->
      <?php elseif ($groupedMacs): ?>
        <table class="table table-hover table-sm align-middle olt-mac-table table-app">
          <thead class="olt-table-header">
            <tr>
              <th>ONU ID</th>
              <th>Client</th>
              <th>Area</th>
              <th>Sub Zone</th>
              <th>Box</th>
              <th>Description</th>
              <th>MAC</th>
              <th>VLAN</th>
              <th>Status</th>
              <th class="olt-dist-col">Dist.(m)</th>
              <th class="olt-rx-col">L.(dBm)</th>
              <th>LDR</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($groupedMacs as $group): ?>
              <?php foreach ($group['rows'] as $row): ?>
                <tr>
                  <td data-label="ONU ID"><?= h(format_onu_identifier($row)); ?></td>
                  <td data-label="Client">
                    <?php
                    $clientValue = '';
                    $clientLinkId = null;
                    if (!empty($row['client_meta'])) {
                      $cm = $row['client_meta'];
                      $clientCodeDisplay = trim((string)($cm['client_code'] ?? ''));
                      $clientNameDisplay = trim((string)($cm['name'] ?? ''));
                      if ($clientCodeDisplay !== '') {
                        $clientValue = $clientCodeDisplay . ($clientNameDisplay !== '' ? ':' . $clientNameDisplay : '');
                      } elseif ($clientNameDisplay !== '') {
                        $clientValue = $clientNameDisplay;
                      }
                      $clientIdInt = isset($cm['id']) ? (int)$cm['id'] : 0;
                      $clientLinkId = $clientIdInt > 0 ? $clientIdInt : null;
                    } elseif (isset($row['clients'])) {
                      $clientsRaw = trim((string)$row['clients']);
                      if ($clientsRaw !== '' && ctype_digit($clientsRaw)) {
                        $maybeId = (int)$clientsRaw;
                        if ($maybeId > 0) {
                          $clientLinkId = $maybeId;
                        }
                        $codeLookup = trim((string)($row['client_code_lookup'] ?? ''));
                        $nameLookup = trim((string)($row['client_name_lookup'] ?? ''));
                        if ($codeLookup !== '') {
                          $clientValue = $codeLookup . ($nameLookup !== '' ? ':' . $nameLookup : '');
                        } elseif ($nameLookup !== '') {
                          $clientValue = $nameLookup;
                        }
                      } else {
                        $clientValue = $clientsRaw;
                      }
                    } else {
                      $clientIdRaw = isset($row['client_id']) ? (int)$row['client_id'] : 0;
                      $fallbackCode = trim((string)($row['client_code_lookup'] ?? $row['client_code'] ?? ''));
                      $fallbackName = trim((string)($row['client_name_lookup'] ?? ''));
                      if ($fallbackCode !== '') {
                        $clientValue = $fallbackCode . ($fallbackName !== '' ? ':' . $fallbackName : '');
                      } elseif ($fallbackName !== '') {
                        $clientValue = $fallbackName;
                      }
                      if ($clientIdRaw > 0) {
                        $clientLinkId = $clientIdRaw;
                      }
                    }
                    $clientValueTrim = trim($clientValue);
                    $clientMissing = $clientValueTrim === '' || $clientValueTrim === '-' || $clientValueTrim === '—';
                    ?>
                    <?php if ($clientMissing): ?>
                      <span class="text-muted "> — </span>
                    <?php elseif ($clientLinkId !== null): ?>
                      <a href="/public/client_view.php?id=<?= $clientLinkId; ?>" class="fw-semibold text-decoration-none">
                        <?= h($clientValue); ?>
                      </a>
                    <?php else: ?>
                      <span class="fw-semibold"><?= h($clientValue); ?></span>
                    <?php endif; ?>
                  </td>
                  <td data-label="Area"><?= !empty($row['client_meta']) && ($row['client_meta']['area'] ?? '') !== '' ? h($row['client_meta']['area']) : '—' ?></td>
                  <td data-label="Sub Zone"><?= !empty($row['client_meta']) && ($row['client_meta']['sub_zone'] ?? '') !== '' ? h($row['client_meta']['sub_zone']) : '—' ?></td>
                  <td data-label="Box"><?= !empty($row['client_meta']) && ($row['client_meta']['box'] ?? '') !== '' ? h($row['client_meta']['box']) : '—' ?></td>
                  <td data-label="Description"><span class="desc-text" data-row-id="<?= $row['id']; ?>"><?= h($row['description'] !== null && $row['description'] !== '' ? $row['description'] : '—'); ?></span></td>
                  <td data-label="MAC"><code class="fw-semibold"><?= h(strtoupper($row['mac'])); ?></code></td>
                  <?php
                  $clientKey = client_mac_lookup_key($row);
                  $clientMacRows = $clientKey && isset($clientMacCache[$clientKey]) ? $clientMacCache[$clientKey] : [];
                  $vlanBadges = [];
                  if (isset($row['vlan']) && $row['vlan'] !== null && $row['vlan'] !== '') {
                    $vlanBadges[] = $row['vlan'];
                  }
                  if (!$vlanBadges && $clientMacRows) {
                    foreach ($clientMacRows as $cm) {
                      if (isset($cm['vlan']) && $cm['vlan'] !== null && $cm['vlan'] !== '') {
                        $vlanBadges[] = $cm['vlan'];
                      }
                    }
                    $vlanBadges = array_values(array_unique($vlanBadges));
                  }
                  ?>
                  <td data-label="VLAN">
                    <?php if ($vlanBadges): ?>
                      <?php foreach ($vlanBadges as $vb): ?>
                        <span class="badge text-bg-secondary me-1 mb-1"><?= h($vb); ?></span>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?>
                  </td>
                  <td data-label="Status">
                    <?php [$statusLabel, $statusClass] = status_badge_meta(resolve_row_status($row)); ?>
                    <span class="<?= $statusClass; ?>"><?= $statusLabel; ?></span>
                  </td>
                  <?php
                  $distanceRaw = $row['distance_m'] ?? null;
                  $rxRaw = $row['rx_power_dbm'] ?? null;
                  $distanceIsNumeric = $distanceRaw !== null && $distanceRaw !== '' && is_numeric($distanceRaw);
                  $rxIsNumeric = $rxRaw !== null && $rxRaw !== '' && is_numeric($rxRaw);
                  $distanceNum = $distanceIsNumeric ? (float)$distanceRaw : null;
                  $rxNum = $rxIsNumeric ? (float)$rxRaw : null;
                  if ($distanceNum !== null && $distanceNum < 0) {
                    if ($rxNum !== null && $rxNum > 0) {
                      $tmp = $rxRaw;
                      $rxRaw = $distanceRaw;
                      $distanceRaw = $tmp;
                    } else {
                      $rxRaw = $distanceRaw;
                      $distanceRaw = null;
                    }
                  } elseif ($rxNum !== null && $rxNum > 100 && ($distanceNum === null || $distanceNum < 0)) {
                    $tmp = $rxRaw;
                    $rxRaw = $distanceRaw;
                    $distanceRaw = $tmp;
                  }
                  $distanceDisplay = format_metric($distanceRaw, 0);
                  $rxDisplay = format_metric($rxRaw);
                  [$rxLabel, $rxClass] = rx_power_meta($rxRaw);
                  ?>
                  <td class="olt-dist-col" data-label="Dist.(m)"><?= h($distanceDisplay); ?></td>
                  <td class="olt-rx-cell olt-rx-col" data-label="L.(dBm)">
                    <span><?= h($rxDisplay); ?></span>
                    <?php if ($rxLabel): ?>
                      <span class="badge <?= $rxClass; ?>"><?= $rxLabel; ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="olt-dereg-cell" data-label="LDR">
                    <?php
                    $deregReason = $row['last_dereg_reason'] ?? null;
                    $deregTime = $row['last_dereg_time'] ?? null;
                    $deregReasonDisplay = ($deregReason === null || $deregReason === '') ? '—' : $deregReason;
                    ?>
                    <div><?= h($deregReasonDisplay); ?></div>
                    <?php if ($deregTime): ?>
                      <div class="text-muted small"><?= h($deregTime); ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="text-end" data-label="Action">
                    <?php $cfgDisabled = empty($row['id']) || (($row['cache_source'] ?? '') === 'onu_monitor_cache'); ?>
                    <button class="btn btn-outline-primary btn-sm btn-config"
                      type="button"
                      <?= $cfgDisabled ? 'disabled' : ''; ?>
                      data-row-id="<?= (int)($row['id'] ?? 0); ?>"
                      data-desc="<?= h($row['description'] ?? ''); ?>">
                      Configure
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="p-4 text-center text-muted">ONU MAC পাওয়া যায়নি।</div>
      <?php endif; ?>
    </div>
  </div>
<?php
}

function render_olt_summary_block(?array $selectedOlt, array $ponSummary, array $ponTotals, int $filterOlt): void
{
  if (!$selectedOlt || $filterOlt <= 0) {
    echo '';
    return;
  }
?>
  <div class="d-flex align-items-center justify-content-between olt-summary-block">
    <div class=" accordion-body">
      <span class="fw-semibold">OLT NAME : <?= h($selectedOlt['name'] ?: 'Unnamed OLT'); ?> |IP: <?= h($selectedOlt['host'] ?? ''); ?></span>
    </div>
    <?php if (!empty($ponSummary)): ?>
      <div class=" d-flex align-items-center gap-2 flex-wrap">
        <span class="text-muted small">PON Ports: <?= (int)$ponTotals['total_pons']; ?> • Total ONU: <?= (int)$ponTotals['total_onu']; ?></span>
        <?php foreach ($ponSummary as $ps): ?>
          <span class="badge text-bg-light border"><?= h($ps['label'] ?? 'PON :'); ?> • ONU : <?= (int)($ps['count'] ?? 0); ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php
}

if ($isAjax) {
  $tableHtml = '';
  if ($filterOlt <= 0) {
    // $tableHtml = '<div class="p-4 text-center text-muted">দয়া করে প্রথমে OLT সিলেক্ট করুন।</div>';
  } else {
    ob_start();
    render_olt_table_block($groupedMacs, $clientMacCache, $filterOlt);
    $tableHtml = ob_get_clean();
  }
  ob_start();
  render_olt_summary_block($selectedOlt ?? null, $ponSummary ?? [], $ponTotals ?? [], $filterOlt);
  $summaryHtml = ob_get_clean();
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok' => true,
    'html' => $tableHtml,
    'summary' => $summaryHtml,
    'pon_options' => $filterOlt > 0 ? array_values($ponOptions ?? []) : [],
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

require_once __DIR__ . '/../partials/partials_header.php';
?>
<link rel="stylesheet" href="/css/olt_mac_table.css?v=<?= @filemtime(__DIR__ . '/../assets/css/olt_mac_table.css'); ?>">
<div class="olt-mac-wrap container-fluid">
  <div class="container-admin olt-mac-table-page">

    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <span class="fw-semibold">ONU Table</span>
        <form id="clientCodeSearchForm" class="d-flex align-items-center gap-2 fw-semibold" method="get" action="">
          <input type="hidden" name="olt_id" value="<?= (int)$filterOlt; ?>">
          <input type="hidden" name="pon" value="<?= (int)$filterPon; ?>">
          <input id="clientCodeInput" type="text" name="client_code" class="form-control form-control-sm" placeholder="Client code" value="<?= h($filterClientCode); ?>" style="min-width: 180px;" autocomplete="off">
        </form>
      </div>
      <span class="fw-semibold"><i class="bi bi-hdd-fill"></i> <?= h($lastLearnedHuman); ?></span>
    </div>
    <div class="container-fluid olt-sticky-header">
      <div class="olt-filters-wrap mb-3">
        <form id="oltFiltersForm" class="card shadow-sm" method="get" action="">
          <div class="d-flex justify-content-between align-items-center gap-3 p-3 flex-wrap">
            <input type="hidden" name="client_code" id="clientCodeHidden" value="<?= h($filterClientCode); ?>">
            <div class="fw-semibold">
              <select name="olt_id" class="form-select" required>
                <option value="0" disabled <?= $filterOlt === 0 ? 'selected' : ''; ?>>OLT সিলেক্ট করুন</option>
                <?php foreach ($olts as $olt): ?>
                  <?php $oltId = (int)$olt['id']; ?>
                  <option value="<?= $oltId; ?>" <?= (int)$filterOlt === $oltId ? 'selected' : ''; ?>>
                    <?= h($olt['name'] ?: $olt['host']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="fw-semibold">
              <select name="pon" class="form-select" <?= $filterOlt === 0 ? 'disabled' : ''; ?>>
                <option value="0" disabled <?= $filterPon === 0 ? 'selected' : ''; ?>>PON সিলেক্ট করুন</option>
                <?php if (!empty($ponOptions) && $filterOlt > 0): ?>
                  <?php foreach ($ponOptions as $slot): ?>
                    <?php $slotVal = (int)$slot; ?>
                    <option value="<?= $slotVal; ?>" <?= (int)$filterPon === $slotVal ? 'selected' : ''; ?>>
                      PON <?= h($slotVal); ?>
                    </option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
            </div>

          </div>


          <div id="oltSummary">
            <?php render_olt_summary_block($selectedOlt ?? null, $ponSummary ?? [], $ponTotals ?? [], $filterOlt); ?>
          </div>
        </form>
      </div>
    </div>

    <?php render_olt_table_block($groupedMacs, $clientMacCache, $filterOlt); ?>
  </div>
</div>

<?php require_once __DIR__ . '/../partials/partials_footer.php'; ?>

<!-- Config modal -->
<div class="modal fade" id="onuConfigModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="onuConfigForm">
      <div class="modal-header">
        <h5 class="modal-title">ONU Configuration</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?= csrf_input_html(); ?>
        <input type="hidden" name="action" value="update_desc">
        <input type="hidden" name="ajax" value="1">
        <input type="hidden" name="row_id" id="cfgRowId" value="">
        <label class="form-label text-muted small text-uppercase">Description</label>
        <textarea name="description" id="cfgDesc" rows="3" class="form-control" placeholder="e.g. Building-3, 3rd Floor"></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<?php if ($toast_message !== '' && $toast_type !== ''): ?>
  <script>
    if (window.showToast) {
      showToast('<?= h($toast_message) ?>', '<?= h($toast_type) ?>', 3000);
    }
  </script>
<?php endif; ?>
<?php $jsVer = @filemtime(__DIR__ . '/js/olt_mac_table.js') ?: time(); ?>
<script src="/public/js/olt_mac_table.js?v=<?= $jsVer ?>"></script>
