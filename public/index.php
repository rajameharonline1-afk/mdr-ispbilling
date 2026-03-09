<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/require_login.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/dashboard_service.php';

require_perm('view.dashboard');

$pdo = db();
$dashboard = dashboard_build_data($pdo);
$page_title = 'Dashboard';
$_active = 'dashboard';

require __DIR__ . '/../partials/partials_header.php';

$dashboardJson = json_encode($dashboard, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($dashboardJson)) {
    $dashboardJson = '{}';
}

$variantClass = static function (string $variant): string {
    return match ($variant) {
        'green' => 'ads-kpi-green',
        'purple' => 'ads-kpi-purple',
        'dark' => 'ads-kpi-dark',
        'danger' => 'ads-kpi-danger',
        'warning' => 'ads-kpi-warning',
        default => 'ads-kpi-blue',
    };
};
?>

<div class="container-fluid">
  <div class="ads-page-head">
    <h1 class="ads-title"><i class="bi bi-speedometer2 me-1"></i> Dashboard <small>Admin Panel</small></h1>
    <a href="/public/accounts.php" class="ads-action-btn"><i class="bi bi-box-arrow-up-right"></i> Accounting Dashboard</a>
  </div>

  <section class="ads-kpi-grid" id="adsPrimaryCards">
    <?php foreach (($dashboard['cards'] ?? []) as $card): ?>
      <?php
        $key = (string)($card['key'] ?? 'card');
        $title = (string)($card['title'] ?? '');
        $value = (string)($card['value'] ?? '0');
        $note = (string)($card['note'] ?? '');
        $href = (string)($card['href'] ?? '#');
        $icon = (string)($card['icon'] ?? 'bi-circle');
        $variant = (string)($card['variant'] ?? 'blue');
      ?>
      <article class="ads-kpi-card <?= h($variantClass($variant)) ?>" data-key="<?= h($key) ?>" data-title="<?= h($title) ?>" data-note="<?= h($note) ?>" data-value="<?= h($value) ?>" data-href="<?= h($href) ?>">
        <button type="button" class="info-btn" data-bs-toggle="tooltip" data-bs-title="Details" aria-label="Show details">
          <i class="bi bi-info"></i>
        </button>
        <div class="icon"><i class="bi <?= h($icon) ?>"></i></div>
        <div class="body">
          <p class="title"><?= h($title) ?></p>
          <p class="value" data-value-target><?= h($value) ?></p>
          <p class="note"><?= h($note) ?></p>
        </div>
      </article>
    <?php endforeach; ?>
  </section>

  <section class="ads-grid-4">
    <article class="ads-panel">
      <header class="ads-panel-head"><h5>Zone Wise Problem Occurrence</h5></header>
      <div class="ads-panel-body position-relative">
        <canvas id="chartZone" height="230"></canvas>
        <div class="ads-no-data d-none" id="noDataZone">No data</div>
      </div>
    </article>

    <article class="ads-panel">
      <header class="ads-panel-head"><h5>Sub-Zone Wise Problem Occurrence</h5></header>
      <div class="ads-panel-body position-relative">
        <canvas id="chartSubZone" height="230"></canvas>
        <div class="ads-no-data d-none" id="noDataSubZone">No data</div>
      </div>
    </article>

    <div class="ads-support-stack" id="adsSupportCards">
      <?php foreach (($dashboard['support_cards'] ?? []) as $index => $card): ?>
        <?php
          $title = (string)($card['title'] ?? '');
          $value = (string)($card['value'] ?? '0');
          $note = (string)($card['note'] ?? '');
          $icon = (string)($card['icon'] ?? 'bi-circle');
          $variant = (string)($card['variant'] ?? 'danger');
        ?>
        <article class="ads-support-card <?= h($variantClass($variant)) ?>" data-support-index="<?= (int)$index ?>">
          <i class="icon bi <?= h($icon) ?>"></i>
          <div class="content">
            <p class="title"><?= h($title) ?></p>
            <p class="value" data-value-target><?= h($value) ?></p>
            <p class="note"><?= h($note) ?></p>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <article class="ads-panel">
      <header class="ads-panel-head"><h5>Monthly Problem Occurrence</h5></header>
      <div class="ads-panel-body position-relative">
        <canvas id="chartMonthlyProblem" height="230"></canvas>
        <div class="ads-no-data d-none" id="noDataMonthlyProblem">No data</div>
      </div>
    </article>
  </section>

  <section class="ads-grid-2">
    <article class="ads-panel">
      <header class="ads-panel-head"><h5>Most Problem Solver (Quantity)</h5></header>
      <div class="ads-panel-body position-relative">
        <canvas id="chartMostProblemSolver" height="250"></canvas>
        <div class="ads-no-data d-none" id="noDataMostProblemSolver">No data</div>
      </div>
    </article>

    <article class="ads-panel">
      <header class="ads-panel-head"><h5>Monthly New Client</h5></header>
      <div class="ads-panel-body position-relative">
        <canvas id="chartMonthlyNewClient" height="250"></canvas>
        <div class="ads-no-data d-none" id="noDataMonthlyNewClient">No data</div>
      </div>
    </article>
  </section>

  <section class="ads-grid-2">
    <article class="ads-panel">
      <header class="ads-panel-head"><h5>Company Performance (Active Client)</h5></header>
      <div class="ads-panel-body position-relative">
        <canvas id="chartCompanyPerformance" height="260"></canvas>
        <div class="ads-no-data d-none" id="noDataCompanyPerformance">No data</div>
      </div>
    </article>

    <article class="ads-panel">
      <div class="ads-table-wrap">
        <div class="ads-table-head">TOP 20 UNPAID CLIENT</div>
        <div class="ads-table-inner">
          <table class="ads-table" id="unpaidClientTable">
            <thead>
              <tr>
                <th>User Name</th>
                <th>Mobile</th>
                <th>Bill Amount</th>
                <th>Due Amount</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($dashboard['top_unpaid_clients'])): ?>
                <tr>
                  <td colspan="4" class="text-center text-muted">No data</td>
                </tr>
              <?php else: ?>
                <?php foreach ($dashboard['top_unpaid_clients'] as $row): ?>
                  <tr>
                    <td><?= h((string)($row['user_name'] ?? '')) ?></td>
                    <td><?= h((string)($row['mobile'] ?? '')) ?></td>
                    <td><?= h(number_format((float)($row['bill_amount'] ?? 0), 2, '.', '')) ?></td>
                    <td><?= h(number_format((float)($row['due_amount'] ?? 0), 2, '.', '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </article>
  </section>

  <section class="ads-kpi-grid" id="adsFinanceCards">
    <?php foreach (($dashboard['finance_cards'] ?? []) as $index => $card): ?>
      <?php
        $title = (string)($card['title'] ?? '');
        $value = (string)($card['value'] ?? '0');
        $note = (string)($card['note'] ?? '');
        $icon = (string)($card['icon'] ?? 'bi-circle');
        $variant = (string)($card['variant'] ?? 'blue');
      ?>
      <article class="ads-kpi-card <?= h($variantClass($variant)) ?>" data-finance-index="<?= (int)$index ?>" data-title="<?= h($title) ?>" data-note="<?= h($note) ?>" data-value="<?= h($value) ?>">
        <button type="button" class="info-btn" data-bs-toggle="tooltip" data-bs-title="Details" aria-label="Show details">
          <i class="bi bi-info"></i>
        </button>
        <div class="icon"><i class="bi <?= h($icon) ?>"></i></div>
        <div class="body">
          <p class="title"><?= h($title) ?></p>
          <p class="value" data-value-target><?= h($value) ?></p>
          <p class="note"><?= h($note) ?></p>
        </div>
      </article>
    <?php endforeach; ?>
  </section>
</div>

<div class="ads-action-rail">
  <button type="button" data-bs-toggle="tooltip" data-bs-title="Theme"><i class="bi bi-palette-fill"></i></button>
  <button type="button" data-bs-toggle="tooltip" data-bs-title="Settings"><i class="bi bi-gear-fill"></i></button>
</div>

<div class="modal fade ads-modal" id="kpiDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="kpiDetailTitle">Card Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-1 text-muted">Current Value</p>
        <h3 class="fw-bold mb-3" id="kpiDetailValue">0</h3>
        <p class="mb-0" id="kpiDetailNote">-</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  (function() {
    const dashboardData = <?= $dashboardJson ?>;
    const chartPalette = ['#1e96e3', '#2ebdbf', '#f2b72f', '#32c06a', '#8a56d5', '#ff6b4a', '#4e79a7', '#76b7b2'];
    const charts = [];

    function hasData(values) {
      return Array.isArray(values) && values.some(v => Number(v) > 0);
    }

    function renderBarChart(canvasId, labels, values, noDataId, options = {}) {
      const canvas = document.getElementById(canvasId);
      const noData = document.getElementById(noDataId);
      if (!canvas) return;

      if (!hasData(values)) {
        canvas.classList.add('d-none');
        if (noData) noData.classList.remove('d-none');
        return;
      }

      canvas.classList.remove('d-none');
      if (noData) noData.classList.add('d-none');

      const ctx = canvas.getContext('2d');
      const type = options.type || 'bar';
      const isLine = type === 'line';
      const isDoughnut = type === 'doughnut';
      const dataset = isDoughnut
        ? {
            data: values,
            backgroundColor: (labels || []).map((_, i) => chartPalette[i % chartPalette.length]),
            borderColor: '#f3f5f8',
            borderWidth: 1
          }
        : isLine
        ? {
            data: values,
            borderColor: '#1e96e3',
            backgroundColor: 'rgba(30, 150, 227, 0.18)',
            pointBackgroundColor: '#1e96e3',
            pointRadius: 3,
            pointHoverRadius: 4,
            borderWidth: 2,
            tension: 0.32,
            fill: true
          }
        : {
            data: values,
            backgroundColor: (labels || []).map((_, i) => chartPalette[i % chartPalette.length]),
            borderWidth: 0,
            borderRadius: 0,
            barThickness: options.barThickness || 34
          };
      const chart = new Chart(ctx, {
        type: type,
        data: {
          labels: labels,
          datasets: [dataset]
        },
        options: {
          indexAxis: options.indexAxis || 'x',
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              display: isDoughnut,
              position: 'right',
              labels: {
                boxWidth: 10,
                boxHeight: 10,
                usePointStyle: true,
                color: '#23364a',
                font: { size: 10 }
              }
            },
            tooltip: { enabled: true }
          },
          cutout: isDoughnut ? '55%' : '0%',
          scales: isDoughnut ? {} : {
            x: {
              grid: { color: 'rgba(148, 163, 184, 0.25)' },
              ticks: { color: '#0f2f4f' }
            },
            y: {
              beginAtZero: true,
              grid: { color: 'rgba(148, 163, 184, 0.25)' },
              ticks: { color: '#0f2f4f' }
            }
          }
        }
      });
      charts.push(chart);
    }

    function buildCharts(data) {
      charts.splice(0).forEach(c => c.destroy());
      const chartData = data.charts || {};
      renderBarChart('chartZone', chartData.zone_problem?.labels || [], chartData.zone_problem?.values || [], 'noDataZone', { type: 'doughnut' });
      renderBarChart('chartSubZone', chartData.sub_zone_problem?.labels || [], chartData.sub_zone_problem?.values || [], 'noDataSubZone', { type: 'doughnut' });
      renderBarChart('chartMonthlyProblem', chartData.monthly_problem?.labels || [], chartData.monthly_problem?.values || [], 'noDataMonthlyProblem', { type: 'doughnut' });
      renderBarChart('chartMostProblemSolver', chartData.most_problem_solver?.labels || [], chartData.most_problem_solver?.values || [], 'noDataMostProblemSolver', { type: 'bar', indexAxis: 'y', barThickness: 22 });
      renderBarChart('chartMonthlyNewClient', chartData.monthly_new_client?.labels || [], chartData.monthly_new_client?.values || [], 'noDataMonthlyNewClient', { type: 'bar', barThickness: 42 });
      renderBarChart('chartCompanyPerformance', chartData.company_performance?.labels || [], chartData.company_performance?.values || [], 'noDataCompanyPerformance', { type: 'bar', barThickness: 20 });
    }

    function updateKpiValues(data) {
      (data.cards || []).forEach((card) => {
        const key = String(card.key || '');
        const box = Array.from(document.querySelectorAll('.ads-kpi-card[data-key]')).find((el) => el.dataset.key === key);
        if (!box) return;
        const valueEl = box.querySelector('[data-value-target]');
        if (valueEl) valueEl.textContent = String(card.value ?? '0');
        box.dataset.title = String(card.title || '');
        box.dataset.note = String(card.note || '');
        box.dataset.value = String(card.value || '0');
      });

      (data.finance_cards || []).forEach((card, idx) => {
        const box = document.querySelector('.ads-kpi-card[data-finance-index="' + idx + '"]');
        if (!box) return;
        const valueEl = box.querySelector('[data-value-target]');
        if (valueEl) valueEl.textContent = String(card.value ?? '0');
        box.dataset.title = String(card.title || '');
        box.dataset.note = String(card.note || '');
        box.dataset.value = String(card.value || '0');
      });

      (data.support_cards || []).forEach((card, idx) => {
        const box = document.querySelector('.ads-support-card[data-support-index="' + idx + '"]');
        if (!box) return;
        const valueEl = box.querySelector('[data-value-target]');
        if (valueEl) valueEl.textContent = String(card.value ?? '0');
      });

      const ts = data.meta?.generated_at || '';
      if (ts) {
        const tsEl = document.getElementById('dashboardLastUpdated');
        if (tsEl) tsEl.textContent = ts;
      }
    }

    function initDetailModal() {
      const modalEl = document.getElementById('kpiDetailModal');
      if (!modalEl || !window.bootstrap) return;
      const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      const titleEl = document.getElementById('kpiDetailTitle');
      const valueEl = document.getElementById('kpiDetailValue');
      const noteEl = document.getElementById('kpiDetailNote');

      document.querySelectorAll('.ads-kpi-card .info-btn').forEach((btn) => {
        btn.addEventListener('click', (ev) => {
          ev.preventDefault();
          ev.stopPropagation();
          const card = btn.closest('.ads-kpi-card');
          if (!card) return;
          titleEl.textContent = card.dataset.title || 'Card Details';
          valueEl.textContent = card.dataset.value || '0';
          noteEl.textContent = card.dataset.note || '-';
          modal.show();
        });
      });

      document.querySelectorAll('.ads-kpi-card[data-href]').forEach((card) => {
        card.addEventListener('click', (ev) => {
          if (ev.target && ev.target.closest('.info-btn')) return;
          const href = card.dataset.href || '';
          if (!href || href === '#') return;
          window.location.href = href;
        });
      });
    }

    async function refreshDashboard() {
      try {
        const res = await fetch('/public/api/dashboard_data.php', { credentials: 'same-origin' });
        if (!res.ok) return;
        const payload = await res.json();
        if (!payload || !payload.success || !payload.data) return;

        updateKpiValues(payload.data);
        buildCharts(payload.data);
      } catch (e) {
        // silent refresh failure
      }
    }

    function initAutoRefresh() {
      const toggle = document.getElementById('adsAutoRefresh');
      let timer = null;

      const schedule = () => {
        if (timer) {
          clearInterval(timer);
          timer = null;
        }
        if (!toggle || toggle.checked) {
          timer = setInterval(refreshDashboard, 60000);
        }
      };

      if (toggle) {
        toggle.addEventListener('change', schedule);
      }
      schedule();
    }

    document.addEventListener('DOMContentLoaded', function() {
      buildCharts(dashboardData);
      initDetailModal();
      initAutoRefresh();
    });
  })();
</script>

<?php require __DIR__ . '/../partials/partials_footer.php'; ?>
