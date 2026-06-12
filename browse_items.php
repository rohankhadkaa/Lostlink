<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/auth.php";

require_login(); // must be logged in

$role = strtolower(trim($_SESSION['role'] ?? ''));
?>

<?php require_once __DIR__ . "/partials/header.php"; ?>

<div class="page">
  <div class="shell">

    <div class="page-head">
      <div>
        <h1>Browse Lost &amp; Found Items</h1>
        <p>Search reported items. Only limited information is shown for security and privacy.</p>
      </div>

      <div class="d-flex gap-2 flex-wrap">
        <?php if ($role === 'admin'): ?>
          <a href="admin_dashboard.php" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left me-2"></i>Back to Admin
          </a>
        <?php else: ?>
          <a href="user_dashboard.php" class="btn btn-outline-primary">
            <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
          </a>
        <?php endif; ?>
      </div>
    </div>

    <div class="alert alert-info">
      Limited information is displayed for security purposes.
      Click <strong>Claim Item</strong> on any item to start a claim; an admin will verify ownership through on-site messaging.
    </div>

    <!-- Search Section -->
    <div class="panel mb-4">
      <div class="panel-header">
        <h2 class="panel-title">Search</h2>
        <span class="chip">Filter by type</span>
      </div>

      <div class="panel-body">
        <div class="row g-2 align-items-center">
          <div class="col-md-8">
            <input id="searchInput" class="form-control"
                   placeholder="Search by item name (e.g., iphone → phone)...">
          </div>
          <div class="col-md-2">
            <select id="typeFilter" class="form-select">
              <option value="all">All</option>
              <option value="lost">Lost</option>
              <option value="found">Found</option>
            </select>
          </div>
          <div class="col-md-2">
            <button id="searchBtn" class="btn btn-primary w-100">
              <i class="bi bi-search me-2"></i>Search
            </button>
          </div>
        </div>

        <hr class="hr-soft">

        <div id="statusMsg" class="notice">
          Ready.
        </div>
      </div>
    </div>

    <!-- Results -->
    <div class="panel">
      <div class="panel-header">
        <h2 class="panel-title">Results</h2>
        <span class="status-pill info">Public view</span>
      </div>

      <div class="panel-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead>
              <tr>
                <th>Item</th>
                <th>Type</th>
                <th>Date Reported</th>
                <th>Claim Info</th>
              </tr>
            </thead>
            <tbody id="resultsBody">
              <!-- JS renders rows here -->
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
const input = document.getElementById('searchInput');
const typeFilter = document.getElementById('typeFilter');
const btn = document.getElementById('searchBtn');
const body = document.getElementById('resultsBody');
const statusMsg = document.getElementById('statusMsg');

function escapeHtml(s) {
  return String(s ?? '')
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'","&#039;");
}

function render(rows) {
  body.innerHTML = '';
  if (!rows || rows.length === 0) {
    body.innerHTML = `<tr><td colspan="4" class="text-muted text-center py-4">No results found</td></tr>`;
    return;
  }
  for (const r of rows) {
    const date = (r.date_lost || '').substring(0,10);

    // Any listed item can be claimed, as long as it has an id.
    const claimCell = r.id
      ? `<a class="btn btn-sm btn-primary" href="claim_item.php?item_id=${encodeURIComponent(r.id)}">Claim Item</a>`
      : `&mdash;`;

    body.innerHTML += `
      <tr>
        <td><strong>${escapeHtml(r.item_name)}</strong></td>
        <td>
          <span class="status-pill ${escapeHtml((r.item_type || 'lost').toLowerCase())}">
            ${escapeHtml((r.item_type || 'lost').toUpperCase())}
          </span>
        </td>
        <td>${escapeHtml(date)}</td>
        <td class="small">${claimCell}</td>
      </tr>
    `;
  }
}

async function runSearch() {
  const q = input.value.trim();
  const type = typeFilter.value;
  statusMsg.textContent = 'Searching...';

  try {
    const url = `ai_search.php?q=${encodeURIComponent(q)}&type=${encodeURIComponent(type)}`;
    const res = await fetch(url);
    const data = await res.json();

    if (!Array.isArray(data)) {
      statusMsg.textContent = "Search error: " + (data.error || "unexpected response");
      render([]);
      return;
    }

    statusMsg.textContent = `Showing ${data.length} result(s).`;
    render(data);
  } catch (err) {
    statusMsg.textContent = "Search failed.";
  }
}

btn.addEventListener('click', runSearch);

let t = null;
input.addEventListener('input', () => {
  clearTimeout(t);
  t = setTimeout(runSearch, 250);
});

runSearch();
</script>

<?php require_once __DIR__ . "/partials/footer.php"; ?>