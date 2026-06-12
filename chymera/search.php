<?php
$hideSidebar = true;
include __DIR__ . '/include/header.php';
?>

<link rel="stylesheet" href="/chymera/fontawesome/css/fontawesome.min.css">
<link rel="stylesheet" href="/chymera/fontawesome/css/solid.min.css">
<link rel="stylesheet" href="/chymera/css/chymera-search.css">

<main class="chymera-container">
  <div class="chymera-inner">

    <aside class="search-form-column">
      <form id="chymeraSearchForm" action="/chymera/search-process.php" method="post" enctype="multipart/form-data">
        <div class="search-pane">

          <div class="form-group">
            <label>Genome</label>
            <label><input type="radio" name="genome" value="hg38" checked> hg38 (human)</label>
            <label><input type="radio" name="genome" value="mm10"> mm10 (mouse)</label>
          </div>

          <div class="form-group">
            <label for="editing-type">Editing Type</label>
            <select id="editing-type" name="editing_type" class="form-control">
              <option value="">Select...</option>
              <option value="Cutting">Cutting</option>
              <option value="CRISPRi">CRISPRi</option>
              <option value="CRISPRa">CRISPRa</option>
            </select>
          </div>

          <?php include __DIR__ . '/include/search-cutting.php'; ?>
          <?php include __DIR__ . '/include/search-crispri.php'; ?>
          <?php include __DIR__ . '/include/search-crispra.php'; ?>

          <div class="form-group" id="searchLoadWrap">
            <label>Search Load</label>
            <div class="horizontal-radios">
              <label><input type="radio" name="search_load" value="both" checked> Both</label>
              <label><input type="radio" name="search_load" value="cas9"> Cas9</label>
              <label><input type="radio" name="search_load" value="cas12a"> Cas12a</label>
            </div>
          </div>

          <div class="search-actions">
            <button id="search-btn" class="btn btn-primary" type="submit">Search</button>
            <button id="reset-btn" class="btn btn-secondary" type="reset">Reset Search</button>
          </div>

        </div>
      </form>
    </aside>

    <section class="results-column">
      <h1 class="page-title">CHyMErA Design</h1>
      <div class="notice" id="searchResultsStatus">This is where your search results will be displayed. You haven't entered any criteria so there is nothing to show yet.</div>
      <div id="searchResults" class="search-results"></div>
    </section>

  </div>
</main>

<style>
.btn-primary{
  background:linear-gradient(180deg,#147089 0%,#0e5567 100%);
  color:#fff; border:none; padding:10px 18px; border-radius:8px; font-weight:700;
  box-shadow:0 2px 0 rgba(0,0,0,0.06);
}
.btn-secondary{
  background:#fff; color:#147089; border:1px solid #cfeaf0;
  padding:10px 18px; border-radius:8px; font-weight:700;
}
.search-results{ margin-top:18px; }
.results-column{ min-width:0; }
.result-card{
  max-width:100%; box-sizing:border-box; border:1px solid #dfe7ea;
  border-radius:8px; background:#fff; padding:16px 18px; margin-bottom:16px;
}
.result-card h2{ margin:0 0 10px; font-size:18px; }
.result-grid{ display:grid; grid-template-columns:180px 1fr; gap:8px 16px; }
.result-grid dt{ font-weight:700; color:#184f61; }
.result-grid dd{ margin:0; }
.result-empty{ color:#666; font-style:italic; }
.result-table-wrap{
  max-width:100%; max-height:420px; overflow-x:auto; overflow-y:auto;
  border:1px solid #dfe7ea; border-radius:8px; margin-top:12px;
  -webkit-overflow-scrolling:touch;
}
.result-table{ width:max-content; min-width:100%; border-collapse:collapse; }
.result-table th,.result-table td{
  border:1px solid #dfe7ea; padding:8px 10px; text-align:left;
  vertical-align:top; background:#fff; white-space:nowrap; font-size:14px;
}
.result-table th{ background:#f7fbfc; position:sticky; top:0; z-index:3; }
.result-table th:first-child,.result-table td:first-child{
  position:sticky; left:0; z-index:2; background:#fff; box-shadow:2px 0 0 #dfe7ea;
}
.result-table th:first-child{ background:#f7fbfc; z-index:4; }
.loading-box{ color:#184f61; }
.error-box{ color:#8b1e1e; }
.result-tabs{
  display:flex; flex-wrap:wrap; gap:8px; margin:12px 0 16px;
  border-bottom:1px solid #dfe7ea; padding:12px 0 10px;
  position:sticky; top:12px; z-index:10; background:#fff;
}
.result-tab{
  appearance:none; border:1px solid #cfeaf0; background:#f7fbfc;
  color:#184f61; border-radius:999px; padding:8px 14px; font-weight:700; cursor:pointer;
}
.result-tab.active{
  background:linear-gradient(180deg,#147089 0%,#0e5567 100%);
  color:#fff; border-color:#0e5567;
}
.result-tab-panel{ display:none; min-width:0; }
.result-tab-panel.active{ display:block; }
.result-pagination{
  display:flex; flex-wrap:wrap; gap:10px 12px; align-items:center; margin:0 0 10px;
}
.result-pagination .pagination-btn{
  appearance:none; border:1px solid #cfeaf0; background:#f7fbfc; color:#184f61;
  border-radius:8px; padding:7px 12px; font-weight:700; cursor:pointer;
}
.result-pagination .pagination-btn:disabled{ opacity:.45; cursor:not-allowed; }
.result-pagination .pagination-info{ font-weight:700; color:#184f61; }
.result-pagination label{ display:inline-flex; align-items:center; gap:8px; font-weight:700; color:#184f61; }
.result-pagination input[type="number"]{ width:84px; padding:7px 8px; border:1px solid #cfeaf0; border-radius:8px; }
.per-page-wrap{ margin-bottom:12px; font-weight:700; color:#184f61; }
.per-page-wrap select{ display:inline-block; width:auto; margin-left:8px; }
.results-header-row{ display:flex; align-items:center; justify-content:space-between; gap:12px; margin:0 0 4px; }
.btn-export{
  appearance:none; background:linear-gradient(180deg,#1a7d3a 0%,#145c2b 100%);
  color:#fff; border:none; border-radius:8px; padding:7px 14px;
  font-size:14px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:7px;
}
.btn-export:hover{ background:linear-gradient(180deg,#1f9444 0%,#176832 100%); }
.results-toolbar{
  display:flex; flex-wrap:wrap; gap:12px; align-items:flex-start;
  justify-content:space-between; margin:12px 0 6px; padding:12px;
  border:1px solid #dfe7ea; border-radius:10px; background:#fafdfe;
}
.results-toolbar .toolbar-group{ display:flex; flex-wrap:wrap; gap:8px 10px; align-items:center; }
.results-toolbar label{ font-weight:700; color:#184f61; }
.results-toolbar input[type="search"]{ min-width:260px; padding:8px 10px; border:1px solid #cfeaf0; border-radius:8px; }
.results-toolbar .toolbar-meta{ color:#184f61; font-weight:700; }
.results-toolbar .column-menu{ min-width:240px; }
.results-toolbar details{ border:1px solid #cfeaf0; border-radius:8px; background:#fff; padding:8px 10px; }
.results-toolbar summary{ cursor:pointer; font-weight:700; color:#184f61; }
.column-visibility-list{ display:grid; gap:6px; padding-top:10px; max-height:220px; overflow:auto; }
.column-visibility-list label{ display:flex; align-items:center; gap:8px; font-weight:600; color:#184f61; }
.column-fixed-note{ font-size:12px; color:#567; margin-top:8px; }
.sort-btn{
  appearance:none; border:none; background:none; padding:0; margin:0;
  font:inherit; font-weight:700; color:inherit; cursor:pointer;
  display:inline-flex; align-items:center; gap:6px; white-space:nowrap;
}
.sort-btn .sort-arrow{ font-size:12px; }
.col-hidden{ display:none !important; }
.tab-error{ color:#8b1e1e; font-style:italic; padding:12px 0; }
</style>

<script>
(function () {
  // ── State ──────────────────────────────────────────────────────────────────
  // Per-group cache: { fields, items, total, page } | null (not yet loaded) | { error }
  const groupCache    = {};
  const groupPage     = {}; // last-visited page per group
  let enabledGroups   = [];
  let currentGroup    = '';
  let sharedMeta      = null;   // criteria / mode / files from last response
  let searchGen       = 0;      // incremented on every fresh search to discard stale fetches

  let rowsPerPage   = 50;
  let tableSearch   = '';
  let sortColumn    = 'Cut Site';
  let sortDirection = 'asc';
  let hiddenColumns = new Set();
  let columnMenuOpen = false;

  const form              = document.getElementById('chymeraSearchForm');
  const editingTypeEl     = document.getElementById('editing-type');
  const cuttingPane       = document.getElementById('cutting-pane');
  const crispriPane       = document.getElementById('crispri-pane');
  const crisprpaPane      = document.getElementById('crisprpa-pane');
  const results           = document.getElementById('searchResults');
  const status            = document.getElementById('searchResultsStatus');
  const appSelect         = document.getElementById('application');
  const targetBlock       = document.getElementById('target-definition');
  const deletionExons     = document.getElementById('deletion-exons-block');
  const deletionFragments = document.getElementById('deletion-fragments-block');

  // ── UI helpers ─────────────────────────────────────────────────────────────
  const show = el => { if (el) el.style.display = ''; };
  const hide = el => { if (el) el.style.display = 'none'; };
  function hideAllPanes() { hide(cuttingPane); hide(crispriPane); hide(crisprpaPane); }

  function resetCuttingBlocks() {
    [targetBlock, deletionExons, deletionFragments,
     document.getElementById('exon-inputs'),
     document.getElementById('lower-upper-block')
    ].forEach(el => { if (el) el.style.display = 'none'; });
  }

  function setDefaultsForEditType(type) {
    const pairs = type === 'crispri'
      ? [['#crispri-upstream','#crispri-downstream',-100,500],
         ['#crispri-upstream-ens','#crispri-downstream-ens',-100,500],
         ['#crispri-upstream-tss','#crispri-downstream-tss',-100,500]]
      : [['#crisprpa-upstream','#crisprpa-downstream',-300,0],
         ['#crisprpa-upstream-ens','#crisprpa-downstream-ens',-300,0],
         ['#crisprpa-upstream-tss','#crisprpa-downstream-tss',-300,0]];
    pairs.forEach(([us, ds, uv, dv]) => {
      const u = document.querySelector(us), d = document.querySelector(ds);
      if (u) u.value = uv;
      if (d) d.value = dv;
    });
  }

  function onEditingTypeChange() {
    const v = String(editingTypeEl?.value || '').toLowerCase();
    hideAllPanes();
    if (v === 'cutting')      { show(cuttingPane);  if (targetBlock) targetBlock.style.display = 'none'; }
    else if (v === 'crispri') { show(crispriPane);  setDefaultsForEditType('crispri'); }
    else if (v === 'crispra') { show(crisprpaPane); setDefaultsForEditType('crisprpa'); }
  }

  if (editingTypeEl) editingTypeEl.addEventListener('change', onEditingTypeChange);

  if (appSelect) {
    appSelect.addEventListener('change', function () {
      const v = this.value;
      if (targetBlock)       targetBlock.style.display       = v === 'knockout'           ? '' : 'none';
      if (deletionExons)     deletionExons.style.display     = v === 'deletion-exons'     ? '' : 'none';
      if (deletionFragments) deletionFragments.style.display = v === 'deletion-fragments' ? '' : 'none';
    });
  }

  document.querySelectorAll('input[name="exon-choice"]').forEach(r => {
    r.addEventListener('change', () => {
      const el = document.getElementById('exon-inputs');
      if (el) el.style.display = '';
    });
  });

  document.querySelectorAll('input[name="fragment-search"]').forEach(r => {
    r.addEventListener('change', function () {
      const el = document.getElementById('lower-upper-block');
      if (el) el.style.display = this.value === 'outside' ? '' : 'none';
    });
  });

  ['crispri', 'crisprpa'].forEach(prefix => {
    const sel = document.getElementById(prefix + '-search-type');
    if (!sel) return;
    sel.addEventListener('change', function () {
      [prefix+'-state-gene', prefix+'-state-ensembl', prefix+'-state-tss'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
      });
      if (this.value) {
        const el = document.getElementById(prefix + '-state-' + this.value);
        if (el) el.style.display = '';
      }
    });
  });

  // ── Value helpers ──────────────────────────────────────────────────────────
  function escapeHtml(v) {
    return String(v)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  function formatValue(v) {
    if (Array.isArray(v))                          return v.length ? v.map(x => escapeHtml(x)).join(', ') : '—';
    if (v === null || v === undefined || v === '') return '—';
    if (typeof v === 'object')                     return '<pre style="margin:0;white-space:pre-wrap;">' + escapeHtml(JSON.stringify(v, null, 2)) + '</pre>';
    if (v === 'TRUE')  return '<i class="fa-solid fa-circle-check"  style="color:#22863a;" title="TRUE"></i>';
    if (v === 'FALSE') return '<i class="fa-solid fa-circle-xmark"  style="color:#cb2431;" title="FALSE"></i>';
    return escapeHtml(v);
  }

  // ── Cache / state reset ────────────────────────────────────────────────────
  function resetAllState() {
    searchGen++;
    Object.keys(groupCache).forEach(k => delete groupCache[k]);
    Object.keys(groupPage).forEach(k => delete groupPage[k]);
    enabledGroups = [];
    currentGroup  = '';
    sharedMeta    = null;
  }

  /** Invalidate cached data (but keep page positions) — used when search/sort changes. */
  function invalidateCache() {
    searchGen++;
    Object.keys(groupCache).forEach(k => delete groupCache[k]);
  }

  // ── FormData builder ───────────────────────────────────────────────────────
  function buildFd(group, page) {
    const fd = new FormData(form);
    fd.set('group',         group);
    fd.set('page',          String(page));
    fd.set('rows_per_page', String(rowsPerPage));
    fd.set('table_search',  tableSearch);
    fd.set('sort_col',      sortColumn);
    fd.set('sort_dir',      sortDirection);
    return fd;
  }

  // ── Fetch a single group/page ──────────────────────────────────────────────
  async function fetchGroup(group, page, gen) {
    try {
      const resp = await fetch(form.action, { method: 'POST', body: buildFd(group, page) });
      const text = await resp.text();
      let parsed;
      try { parsed = JSON.parse(text); } catch { throw new Error('Invalid JSON from server.'); }
      if (!resp.ok) throw new Error(parsed?.message || 'Server error ' + resp.status);
      if (gen !== searchGen) return null; // stale
      return parsed.data;
    } catch (err) {
      if (gen !== searchGen) return null;
      return { __error: err.message || 'Fetch failed.' };
    }
  }

  // ── Initial search: fire metadata fetch then load first-tab data ───────────
  async function runSearch() {
    resetAllState();
    const gen = searchGen;

    status.textContent = 'Searching…';
    results.innerHTML  = '<div class="result-card loading-box">Loading…</div>';
    showSpinner();

    // First fetch with no group to get enabled_groups + metadata
    const meta = await fetchGroup('', 1, gen);
    if (!meta || gen !== searchGen) { hideSpinner(); return; }

    if (meta.__error) {
      hideSpinner();
      renderError(meta.__error);
      return;
    }

    sharedMeta    = meta;
    enabledGroups = meta.enabled_groups || [];
    currentGroup  = enabledGroups[0] || '';

    // If this is a query type that doesn't produce results (CRISPRi/a stubs etc.)
    // just render the summary and stop.
    if (!enabledGroups.length) {
      hideSpinner();
      renderAll();
      return;
    }

    // Immediately fetch the first tab's data
    await loadTab(currentGroup, groupPage[currentGroup] || 1, gen);
    hideSpinner();
  }

  // ── Load (or reload) a specific tab ───────────────────────────────────────
  async function loadTab(group, page, gen) {
    gen = gen ?? searchGen;
    groupCache[group] = null; // mark as loading
    renderAll();
    showSpinner();

    const data = await fetchGroup(group, page, gen);
    hideSpinner();
    if (!data || gen !== searchGen) return;

    if (data.__error) {
      groupCache[group] = { error: data.__error };
    } else {
      groupPage[group]  = data.page ?? page;
      groupCache[group] = {
        fields: data.fields || [],
        items:  data.items  || [],
        total:  data.total  ?? 0,
        page:   data.page   ?? page,
      };
      // Also update sharedMeta with latest criteria/mode (in case it changed)
      if (!sharedMeta) sharedMeta = data;
    }
    renderAll();
  }

  // ── Total pages helper ─────────────────────────────────────────────────────
  function totalPagesFor(group) {
    const c = groupCache[group];
    if (!c || c.error) return 1;
    if (rowsPerPage === 0) return 1;
    return Math.max(1, Math.ceil(c.total / rowsPerPage));
  }

  // ── Render ─────────────────────────────────────────────────────────────────
  function renderError(msg) {
    status.textContent = msg;
    results.innerHTML  = '<div class="result-card error-box"><strong>' + escapeHtml(msg) + '</strong></div>';
  }

  function renderAll() {
    if (!sharedMeta) return;

    const data     = sharedMeta;
    const criteria = data.criteria || {};
    const files    = Array.isArray(data.files) ? data.files : [];
    const mode     = String(data.mode || '').toLowerCase();
    const app      = String(criteria.application || '').toLowerCase();

    const allLoaded = enabledGroups.every(g => groupCache[g] !== null && groupCache[g] !== undefined);
    status.textContent = allLoaded ? 'Search completed.' : 'Searching…';

    let html = '';

    // ── Search Summary ───────────────────────────────────────────────────────
    html += '<div class="result-card"><h2>Search Summary</h2><dl class="result-grid">';
    html += '<dt>Editing Type</dt><dd>' + (escapeHtml(data.editing_type ?? '') || '—') + '</dd>';
    html += '<dt>Genome</dt><dd>'       + (escapeHtml(data.genome      ?? '') || '—') + '</dd>';
    html += '<dt>Search Load</dt><dd>'  + (escapeHtml(data.search_load ?? '') || '—') + '</dd>';

    if (mode === 'cutting') {
      html += '<dt>Application</dt><dd>' + (escapeHtml(criteria.application ?? '') || '—') + '</dd>';
      if (app === 'knockout') {
        if (criteria.gene_symbol?.length)     html += '<dt>Gene Symbol</dt><dd>'     + escapeHtml(criteria.gene_symbol.join(', '))     + '</dd>';
        if (criteria.ensembl_gene_id?.length) html += '<dt>Ensembl Gene ID</dt><dd>' + escapeHtml(criteria.ensembl_gene_id.join(', ')) + '</dd>';
      }
      if (app === 'deletion-exons') {
        if (criteria.exon_choice)        html += '<dt>Exon Choice</dt><dd>'  + escapeHtml(criteria.exon_choice)            + '</dd>';
        if (criteria.exon_input?.length) html += '<dt>Exon Input</dt><dd>'   + escapeHtml(criteria.exon_input.join(', '))  + '</dd>';
        if (criteria.max_distance != null) html += '<dt>Max Distance</dt><dd>' + escapeHtml(String(criteria.max_distance)) + '</dd>';
      }
      if (app === 'deletion-fragments') {
        if (criteria.fragment_coords)    html += '<dt>Fragment Coordinates</dt><dd>' + escapeHtml(criteria.fragment_coords)       + '</dd>';
        if (criteria.fragment_search)    html += '<dt>Fragment Search</dt><dd>'      + escapeHtml(criteria.fragment_search)       + '</dd>';
        if (criteria.fragment_search === 'outside' && criteria.lower_upper != null)
          html += '<dt>Lower/Upper Limit</dt><dd>' + escapeHtml(String(criteria.lower_upper)) + '</dd>';
      }
    }
    if (mode === 'crispri' || mode === 'crispra') {
      if (criteria.search_type) html += '<dt>Search Type</dt><dd>' + escapeHtml(criteria.search_type) + '</dd>';
      if (criteria.search_type === 'gene'    && criteria.gene_symbol?.length)     html += '<dt>Gene Symbol</dt><dd>'     + escapeHtml(criteria.gene_symbol.join(', '))     + '</dd>';
      if (criteria.search_type === 'ensembl' && criteria.ensembl_gene_id?.length) html += '<dt>Ensembl Gene ID</dt><dd>' + escapeHtml(criteria.ensembl_gene_id.join(', ')) + '</dd>';
      if (criteria.search_type === 'tss') {
        if (criteria.tss_coordinate) html += '<dt>TSS Coordinate</dt><dd>' + escapeHtml(criteria.tss_coordinate) + '</dd>';
        if (criteria.gene_strand)    html += '<dt>Gene Strand</dt><dd>'    + escapeHtml(criteria.gene_strand)    + '</dd>';
      }
      if (criteria.upstream   != null) html += '<dt>Upstream</dt><dd>'   + escapeHtml(String(criteria.upstream))   + '</dd>';
      if (criteria.downstream != null) html += '<dt>Downstream</dt><dd>' + escapeHtml(String(criteria.downstream)) + '</dd>';
    }
    html += '</dl></div>';

    // ── Uploaded Files ───────────────────────────────────────────────────────
    if (files.length) {
      html += '<div class="result-card"><h2>Uploaded Files</h2>';
      html += '<table class="result-table"><thead><tr><th>Field</th><th>Name</th><th>Type</th><th>Size</th></tr></thead><tbody>';
      files.forEach(f => {
        html += '<tr><td>' + escapeHtml(f.field ?? '') + '</td><td>' + escapeHtml(f.name ?? '') + '</td>'
              + '<td>' + escapeHtml(f.type ?? '') + '</td><td>' + escapeHtml(String(f.size ?? '')) + '</td></tr>';
      });
      html += '</tbody></table></div>';
    }

    html += renderGroupedResults();
    results.innerHTML = html;
    attachResultEvents();
  }

  // ── Results card ───────────────────────────────────────────────────────────
  function renderGroupedResults() {
    const arrow = sortDirection === 'asc' ? '▲' : '▼';
    let html = '<div class="result-card">';
    html += '<h2 class="results-header-row">Results'
          + ' <button type="button" id="exportBtn" class="btn-export"><i class="fa-solid fa-file-excel"></i> Export to XLSX</button>'
          + '</h2>';

    // Toolbar
    html += '<div class="results-toolbar">';
    html += '<div class="toolbar-group">';
    html += '<label for="tableSearchInput">Search all rows</label>';
    html += '<input type="search" id="tableSearchInput" value="' + escapeHtml(tableSearch) + '" placeholder="Filter every column">';
    html += '<button type="button" class="pagination-btn" id="tableSearchGo">Search</button>';
    html += '<button type="button" class="pagination-btn" id="tableSearchClear">Clear</button>';
    html += '</div>';
    html += '<div class="toolbar-group toolbar-meta">Sorted by ' + escapeHtml(sortColumn) + ' ' + arrow + '</div>';
    html += '<details class="column-menu" id="columnMenu"><summary>Columns</summary>'
          + '<div id="columnVisibilityList" class="column-visibility-list"></div>'
          + '<div id="columnFixedNote" class="column-fixed-note"></div></details>';
    html += '</div>';

    if (!enabledGroups.length) {
      html += '<p class="result-empty">No guide results found.</p></div>';
      return html;
    }

    // Per-page selector
    html += '<div class="per-page-wrap"><label>Results per page: <select id="perPageSelect" class="form-control">';
    [25, 50, 100, 500, 'All'].forEach(opt => {
      const val = opt === 'All' ? '0' : String(opt);
      const sel = (opt === 'All' ? rowsPerPage === 0 : rowsPerPage === Number(opt)) ? ' selected' : '';
      html += '<option value="' + val + '"' + sel + '>' + opt + '</option>';
    });
    html += '</select></label></div>';

    // Tabs
    html += '<div class="result-tabs" role="tablist">';
    enabledGroups.forEach(key => {
      const active = key === currentGroup;
      const cache  = groupCache[key];
      const loaded = cache !== null && cache !== undefined;
      const total  = loaded && !cache.error ? cache.total : null;
      html += '<button type="button" class="result-tab' + (active ? ' active' : '') + '"'
            + ' data-group="' + escapeHtml(key) + '" role="tab" aria-selected="' + active + '">'
            + escapeHtml(key)
            + (total !== null ? ' (' + total + ')' : '')
            + '</button>';
    });
    html += '</div>';

    // Tab panels
    enabledGroups.forEach(key => {
      const active  = key === currentGroup;
      const cache   = groupCache[key];
      const loading = cache === null; // null = fetch in progress
      const unvisited = cache === undefined;
      const hasError  = cache && cache.error;
      const fields    = (!loading && !unvisited && !hasError) ? (cache.fields || []) : [];
      const items     = (!loading && !unvisited && !hasError) ? (cache.items  || []) : [];
      const pg        = groupPage[key] || 1;
      const tp        = totalPagesFor(key);

      html += '<div class="result-tab-panel' + (active ? ' active' : '') + '"'
            + ' role="tabpanel" data-group="' + escapeHtml(key) + '">';

      if (unvisited) {
        html += '<p class="result-empty">Click this tab to load results.</p>';
        html += '</div>';
        return;
      }

      if (loading) {
        html += '<p class="result-empty">Loading…</p>';
        html += '</div>';
        return;
      }

      if (hasError) {
        html += '<p class="tab-error"><strong>Error:</strong> ' + escapeHtml(cache.error) + '</p>';
        html += '<button type="button" class="pagination-btn js-tab-retry" data-group="' + escapeHtml(key) + '">Retry</button>';
        html += '</div>';
        return;
      }

      // Pagination controls
      html += '<div class="result-pagination">';
      html += '<button type="button" class="pagination-btn js-page" data-group="' + escapeHtml(key) + '" data-page="' + (pg - 1) + '"' + (pg <= 1 ? ' disabled' : '') + '>Prev</button>';
      html += '<span class="pagination-info">Page ' + pg + ' of ' + tp + ' (' + (cache.total ?? 0) + ' total)</span>';
      html += '<label>Jump to <input type="number" min="1" max="' + tp + '" value="' + pg + '" class="pagination-input js-jump-input" data-group="' + escapeHtml(key) + '"></label>';
      html += '<button type="button" class="pagination-btn js-jump-go" data-group="' + escapeHtml(key) + '">Go</button>';
      html += '<button type="button" class="pagination-btn js-page" data-group="' + escapeHtml(key) + '" data-page="' + (pg + 1) + '"' + (pg >= tp ? ' disabled' : '') + '>Next</button>';
      html += '</div>';

      // Table
      if (fields.length && items.length) {
        const displayFields = fields.slice(1); // skip Cas Type
        html += '<div class="result-table-wrap"><table class="result-table"><thead><tr>';
        displayFields.forEach((col, ci) => {
          const isSorted = col.name === sortColumn;
          const sortArrow = isSorted ? ' <span class="sort-arrow">' + (sortDirection === 'asc' ? '▲' : '▼') + '</span>' : '';
          const hidden    = hiddenColumns.has(col.name) && ci > 0;
          html += '<th' + (hidden ? ' class="col-hidden"' : '') + ' data-column="' + escapeHtml(col.name) + '">'
                + '<button type="button" class="sort-btn js-sort" data-column="' + escapeHtml(col.name) + '">' + escapeHtml(col.name) + sortArrow + '</button></th>';
        });
        html += '</tr></thead><tbody>';
        items.forEach(row => {
          const displayRow = row.slice(1);
          const rowData    = JSON.stringify({ columns: fields.map(f => f.name), values: row });
          html += '<tr>';
          displayRow.forEach((cell, ci) => {
            const colName = displayFields[ci]?.name || '';
            const hidden  = hiddenColumns.has(colName) && ci > 0;
            if (ci === 0) {
              html += '<td' + (hidden ? ' class="col-hidden"' : '') + ' data-column="' + escapeHtml(colName) + '">'
                    + '<a href="#" class="js-detail-link" data-row="' + escapeHtml(rowData) + '">' + escapeHtml(cell ?? '') + '</a></td>';
            } else {
              html += '<td' + (hidden ? ' class="col-hidden"' : '') + ' data-column="' + escapeHtml(colName) + '">' + formatValue(cell) + '</td>';
            }
          });
          html += '</tr>';
        });
        html += '</tbody></table></div>';
      } else {
        html += '<p class="result-empty">No results found.</p>';
      }

      html += '</div>';
    });

    html += '</div>';
    return html;
  }

  // ── Column visibility menu ─────────────────────────────────────────────────
  function rebuildColumnMenu() {
    const list = document.getElementById('columnVisibilityList');
    const note = document.getElementById('columnFixedNote');
    const menu = document.getElementById('columnMenu');
    if (!list) return;
    if (menu && columnMenuOpen) menu.open = true;

    // Get fields from whichever group has data
    let fields = [];
    for (const g of enabledGroups) {
      const c = groupCache[g];
      if (c && !c.error && c.fields?.length) { fields = c.fields; break; }
    }
    const visible = fields.slice(1); // skip Cas Type

    let html = '';
    visible.forEach(col => {
      const checked = hiddenColumns.has(col.name) ? '' : ' checked';
      html += '<label><input type="checkbox" class="column-toggle-input" data-column="'
            + escapeHtml(col.name) + '"' + checked + '> ' + escapeHtml(col.name) + '</label>';
    });
    list.innerHTML = html || '<div class="result-empty">No columns to configure.</div>';
    if (note) note.textContent = visible.length
      ? 'The Guide Sequence column stays visible because it opens the row detail.' : '';

    list.querySelectorAll('.column-toggle-input').forEach(input => {
      input.addEventListener('change', function () {
        const col = input.dataset.column || '';
        if (!col) return;
        if (input.checked) hiddenColumns.delete(col); else hiddenColumns.add(col);
        const m = document.getElementById('columnMenu');
        columnMenuOpen = m ? m.open : false;
        renderAll();
      });
    });

    if (menu) menu.addEventListener('toggle', () => { columnMenuOpen = menu.open; });
  }

  // ── Event binding ──────────────────────────────────────────────────────────
  function attachResultEvents() {
    // Per-page selector
    const perPageSel = document.getElementById('perPageSelect');
    if (perPageSel) {
      perPageSel.addEventListener('change', function () {
        rowsPerPage = parseInt(this.value, 10);
        invalidateCache();
        // Reset page positions since page sizes changed
        Object.keys(groupPage).forEach(k => groupPage[k] = 1);
        renderAll();
        loadTab(currentGroup, 1, searchGen);
      });
    }

    // Table search
    const searchInput = document.getElementById('tableSearchInput');
    const searchBtn   = document.getElementById('tableSearchGo');
    const clearBtn    = document.getElementById('tableSearchClear');

    function runTableSearch() {
      if (!searchInput) return;
      const newSearch = searchInput.value;
      if (newSearch === tableSearch) return;
      tableSearch = newSearch;
      invalidateCache();
      Object.keys(groupPage).forEach(k => groupPage[k] = 1);
      loadTab(currentGroup, 1, searchGen);
    }

    if (searchInput) {
      searchInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); runTableSearch(); }
      });
    }
    if (searchBtn)  searchBtn.addEventListener('click', runTableSearch);
    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        if (tableSearch === '') return;
        tableSearch = '';
        invalidateCache();
        Object.keys(groupPage).forEach(k => groupPage[k] = 1);
        loadTab(currentGroup, 1, searchGen);
      });
    }

    // Tabs
    results.querySelectorAll('.result-tab').forEach(tab => {
      tab.addEventListener('click', function () {
        const g = tab.dataset.group || '';
        if (!g || g === currentGroup) return;
        currentGroup = g;
        // If already cached, just re-render; otherwise fetch
        if (groupCache[g] !== undefined && groupCache[g] !== null) {
          renderAll();
        } else {
          loadTab(g, groupPage[g] || 1, searchGen);
        }
      });
    });

    // Pagination — prev/next
    results.querySelectorAll('.js-page').forEach(btn => {
      btn.addEventListener('click', function () {
        const g  = btn.dataset.group || currentGroup;
        const pg = parseInt(btn.dataset.page, 10);
        const tp = totalPagesFor(g);
        if (isNaN(pg) || pg < 1 || pg > tp) return;
        groupPage[g] = pg;
        delete groupCache[g]; // invalidate this group's cache for new page
        loadTab(g, pg, searchGen);
      });
    });

    // Pagination — jump to page
    results.querySelectorAll('.js-jump-go').forEach(btn => {
      btn.addEventListener('click', function () {
        const g     = btn.dataset.group || currentGroup;
        const panel = btn.closest('.result-tab-panel');
        const input = panel?.querySelector('.js-jump-input');
        if (!input) return;
        const pg = parseInt(input.value, 10);
        const tp = totalPagesFor(g);
        if (isNaN(pg) || pg < 1 || pg > tp) return;
        groupPage[g] = pg;
        delete groupCache[g];
        loadTab(g, pg, searchGen);
      });
    });

    results.querySelectorAll('.js-jump-input').forEach(input => {
      input.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const g  = input.dataset.group || currentGroup;
        const pg = parseInt(input.value, 10);
        const tp = totalPagesFor(g);
        if (isNaN(pg) || pg < 1 || pg > tp) return;
        groupPage[g] = pg;
        delete groupCache[g];
        loadTab(g, pg, searchGen);
      });
    });

    // Sort
    results.querySelectorAll('.js-sort').forEach(btn => {
      btn.addEventListener('click', function () {
        const col = btn.dataset.column || 'Cut Site';
        if (sortColumn === col) sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
        else { sortColumn = col; sortDirection = 'asc'; }
        invalidateCache();
        Object.keys(groupPage).forEach(k => groupPage[k] = 1);
        loadTab(currentGroup, 1, searchGen);
      });
    });

    // Retry failed tab
    results.querySelectorAll('.js-tab-retry').forEach(btn => {
      btn.addEventListener('click', function () {
        const g = btn.dataset.group || currentGroup;
        delete groupCache[g];
        loadTab(g, groupPage[g] || 1, searchGen);
      });
    });

    // Detail modal links
    results.querySelectorAll('.js-detail-link').forEach(link => {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        let parsed;
        try { parsed = JSON.parse(link.dataset.row); } catch { return; }
        openDetailModal(parsed.columns, parsed.values);
      });
    });

    // Export
    const exportBtn = document.getElementById('exportBtn');
    if (exportBtn) {
      exportBtn.addEventListener('click', function () {
        const fd = new FormData(form);
        fd.set('table_search', tableSearch);
        fd.set('sort_col', sortColumn);
        fd.set('sort_dir', sortDirection);
        const dlForm = document.createElement('form');
        dlForm.method = 'POST';
        dlForm.action = '/chymera/search-export.php';
        dlForm.style.display = 'none';
        for (const [key, val] of fd.entries()) {
          const inp = document.createElement('input');
          inp.type = 'hidden'; inp.name = key; inp.value = val;
          dlForm.appendChild(inp);
        }
        const token    = 'export_ready_' + Date.now();
        const tokenInp = document.createElement('input');
        tokenInp.type = 'hidden'; tokenInp.name = 'download_token'; tokenInp.value = token;
        dlForm.appendChild(tokenInp);
        document.body.appendChild(dlForm);
        showSpinner();
        const poll = setInterval(() => {
          if (document.cookie.split(';').some(c => c.trim() === 'download_token=' + token)) {
            clearInterval(poll);
            hideSpinner();
            document.cookie = 'download_token=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
          }
        }, 300);
        setTimeout(() => { clearInterval(poll); hideSpinner(); }, 60000);
        dlForm.submit();
        document.body.removeChild(dlForm);
      });
    }

    rebuildColumnMenu();
  }

  // ── Spinner ────────────────────────────────────────────────────────────────
  (function buildSpinner() {
    const el = document.createElement('div');
    el.id = 'searchSpinner';
    el.innerHTML = `<div id="searchSpinnerBox"><img src="/chymera/images/beakerspin.svg" alt="" width="72" height="72"><p>Loading…</p></div>`;
    document.body.appendChild(el);
    const style = document.createElement('style');
    style.textContent = `
      #searchSpinner{ display:none; position:fixed; inset:0; background:rgba(255,255,255,.7); z-index:900; align-items:center; justify-content:center; }
      #searchSpinner.active{ display:flex; }
      #searchSpinnerBox{ display:flex; flex-direction:column; align-items:center; gap:14px; background:#fff; border-radius:12px; padding:28px 36px; box-shadow:0 4px 24px rgba(0,0,0,.15); }
      #searchSpinnerBox p{ margin:0; font-size:16px; font-weight:700; color:#184f61; }
    `;
    document.head.appendChild(style);
  })();

  function showSpinner() { document.getElementById('searchSpinner').classList.add('active'); }
  function hideSpinner() { document.getElementById('searchSpinner').classList.remove('active'); }

  // ── Detail modal ───────────────────────────────────────────────────────────
  (function buildModal() {
    const backdrop = document.createElement('div');
    backdrop.id = 'detailBackdrop';
    backdrop.innerHTML = `
      <div id="detailModal" role="dialog" aria-modal="true" aria-labelledby="detailModalTitle">
        <div id="detailModalHeader">
          <h2 id="detailModalTitle">Guide Detail</h2>
          <button id="detailModalPrint" aria-label="Print"><i class="fa-solid fa-print"></i></button>
          <button id="detailModalClose" aria-label="Close">&times;</button>
        </div>
        <dl id="detailModalBody" class="result-grid"></dl>
      </div>`;
    document.body.appendChild(backdrop);
    const style = document.createElement('style');
    style.textContent = `
      #detailBackdrop{ display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1000; align-items:center; justify-content:center; }
      #detailBackdrop.open{ display:flex; }
      #detailModal{ background:#fff; border-radius:10px; width:min(680px,92vw); max-height:85vh; display:flex; flex-direction:column; box-shadow:0 8px 32px rgba(0,0,0,.22); }
      #detailModalHeader{ display:flex; align-items:center; justify-content:space-between; padding:16px 20px 12px; border-bottom:1px solid #dfe7ea; position:sticky; top:0; background:#fff; border-radius:10px 10px 0 0; }
      #detailModalHeader h2{ margin:0; font-size:18px; color:#184f61; }
      #detailModalClose{ appearance:none; border:none; background:none; font-size:24px; line-height:1; cursor:pointer; color:#666; padding:0 4px; }
      #detailModalPrint{ appearance:none; border:1px solid #cfeaf0; background:#f7fbfc; color:#184f61; border-radius:8px; padding:6px 10px; font-size:14px; cursor:pointer; margin-right:8px; }
      #detailModalPrint:hover{ background:#e0f2f7; }
      @media print{
        body > *:not(#detailBackdrop){ display:none !important; }
        #detailBackdrop{ display:block !important; position:static !important; background:none !important; }
        #detailModal{ box-shadow:none !important; max-height:none !important; width:100% !important; }
        #detailModalPrint, #detailModalClose{ display:none !important; }
      }
      #detailModalBody{ padding:18px 20px; overflow-y:auto; display:grid; grid-template-columns:200px 1fr; gap:10px 16px; }
      #detailModalBody dt{ font-weight:700; color:#184f61; align-self:start; }
      #detailModalBody dd{ margin:0; word-break:break-word; }
    `;
    document.head.appendChild(style);
    backdrop.addEventListener('click', e => { if (e.target === backdrop) closeDetailModal(); });
    document.getElementById('detailModalPrint').addEventListener('click', () => window.print());
    document.getElementById('detailModalClose').addEventListener('click', closeDetailModal);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDetailModal(); });
  })();

  function openDetailModal(columns, values) {
    const body = document.getElementById('detailModalBody');
    body.innerHTML = columns.map((col, i) =>
      '<dt>' + escapeHtml(col) + '</dt><dd>' + formatValue(values[i] ?? '') + '</dd>'
    ).join('');
    document.getElementById('detailBackdrop').classList.add('open');
  }

  function closeDetailModal() {
    document.getElementById('detailBackdrop').classList.remove('open');
  }

  // ── Form handlers ──────────────────────────────────────────────────────────
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    tableSearch   = '';
    sortColumn    = 'Cut Site';
    sortDirection = 'asc';
    hiddenColumns = new Set();
    runSearch();
  });

  form.addEventListener('reset', function () {
    setTimeout(function () {
      resetCuttingBlocks();
      hideAllPanes();
      tableSearch   = '';
      sortColumn    = 'Cut Site';
      sortDirection = 'asc';
      hiddenColumns = new Set();
      resetAllState();
      status.textContent = "This is where your search results will be displayed. You haven't entered any criteria so there is nothing to show yet.";
      results.innerHTML  = '';
    }, 0);
  });

  document.addEventListener('DOMContentLoaded', function () {
    resetCuttingBlocks();
    onEditingTypeChange();
  });
})();
</script>

<?php include __DIR__ . '/include/footer.php'; ?>