<?php
require_once __DIR__ . '/includes/auth.php';
$layout = !empty($forceDash) ? 'dash' : 'public';
if ($layout === 'dash') require_role('seeker');
$pageTitle = $layout === 'dash' ? 'Find Jobs' : 'Browse Jobs';

/* =====================================================================
   FILTER INPUT
   ===================================================================== */
$qv  = trim((string)($_GET['q'] ?? ''));
$loc = trim((string)($_GET['location'] ?? ''));
$cat = (int)($_GET['category'] ?? 0);

// Job types: compare in a normalised form so "Full Time", "Full-time", "full_time"
// and "FULLTIME" are all treated as the same type (the stored value and the
// label shown in the filter do not have to be spelled identically).
function jb_norm_type($s): string { return strtolower(preg_replace('/[^a-z0-9]/i', '', (string)$s)); }

$typeOptions = (defined('JOB_TYPES') && is_array(JOB_TYPES) && JOB_TYPES)
    ? array_values(JOB_TYPES)
    : ['Full Time', 'Part Time', 'Contract', 'Internship'];
$typeByKey = [];
foreach ($typeOptions as $t) $typeByKey[jb_norm_type($t)] = $t;

$types = [];                       // selected labels (from the allowed list only)
foreach ((array)($_GET['type'] ?? []) as $rt) {
    $k = is_scalar($rt) ? jb_norm_type($rt) : '';
    if ($k !== '' && isset($typeByKey[$k]) && !in_array($typeByKey[$k], $types, true)) $types[] = $typeByKey[$k];
}

// Escape LIKE wildcards typed by the user ("!" is the escape character)
function jb_like(string $s): string { return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $s) . '%'; }

// Search these columns; "skills" / "requirements" are used only if they exist in the jobs table
$jobCols = [];
try { $jobCols = array_column(rows('SHOW COLUMNS FROM jobs'), 'Field'); } catch (Throwable $e) { $jobCols = []; }
$searchCols = ['j.title', 'c.name', 'j.description'];
foreach (['skills', 'requirements'] as $extra) if (in_array($extra, $jobCols, true)) $searchCols[] = 'j.' . $extra;

/* =====================================================================
   QUERY (prepared statements only)
   ===================================================================== */
$w = ["j.status='open'", '(j.deadline IS NULL OR j.deadline >= CURDATE())']; $p = [];

if ($qv !== '') {
    // Every word must match at least one searchable column ("php developer" works)
    $terms = array_slice(preg_split('/\s+/', $qv, -1, PREG_SPLIT_NO_EMPTY), 0, 6);
    foreach ($terms as $term) {
        $parts = [];
        foreach ($searchCols as $col) { $parts[] = "$col LIKE ? ESCAPE '!'"; $p[] = jb_like($term); }
        $w[] = '(' . implode(' OR ', $parts) . ')';
    }
}
if ($loc !== '') { $w[] = "j.location LIKE ? ESCAPE '!'"; $p[] = jb_like($loc); }
if ($cat)        { $w[] = 'j.category_id=?'; $p[] = $cat; }
if ($types) {
    $w[] = "LOWER(REPLACE(REPLACE(REPLACE(j.job_type,' ',''),'-',''),'_','')) IN (" . implode(',', array_fill(0, count($types), '?')) . ')';
    foreach ($types as $t) $p[] = jb_norm_type($t);
}
$where = ' WHERE ' . implode(' AND ', $w);

$per = max(3, (int)setting('jobs_per_page', 6)); $page = page_no();
$total = (int)val('SELECT COUNT(*) FROM jobs j JOIN companies c ON c.id=j.company_id' . $where, $p);
$jobs = rows(JOB_SELECT . $where . ' ORDER BY j.is_featured DESC, j.created_at DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per), $p);
$saved = saved_ids();

$self = basename($_SERVER['SCRIPT_NAME']);

ob_start();
echo '<div class="jb-count"><b>' . $total . '</b> ' . ($total === 1 ? 'job' : 'jobs') . ' found</div>';
// Each card is wrapped so the click-to-select effect works with any job_card() markup
foreach ($jobs as $j) echo '<div class="jb-item">' . job_card($j, $saved) . '</div>';
if (!$jobs) echo '<div class="jb-empty"><i class="bi bi-search"></i><h5>No jobs found</h5><p>Try different keywords or remove some filters.</p><a href="' . e($self) . '" class="jb-clear">Clear all filters</a></div>';
echo pager($total, $per, $page);
$listHtml = ob_get_clean();
if (isset($_GET['ajax'])) { echo $listHtml; exit; }

$cats = rows('SELECT id, name FROM job_categories ORDER BY name');
$locs = rows("SELECT DISTINCT location FROM jobs WHERE status='open' ORDER BY location");

// ---- View helpers (display only) ----
function jb_url(array $q, string $self): string {
    unset($q['page'], $q['ajax']);
    return $self . ($q ? '?' . http_build_query($q) : '');
}
$base = $_GET; unset($base['page'], $base['ajax']);

$catName = '';
foreach ($cats as $c) if ((int)$c['id'] === $cat) $catName = $c['name'];

// Applied-filter chips, each with a link that removes just that filter
$chips = [];
if ($qv !== '')  { $q = $base; unset($q['q']);        $chips[] = ['"' . $qv . '"', jb_url($q, $self)]; }
if ($loc !== '') { $q = $base; unset($q['location']); $chips[] = [$loc, jb_url($q, $self)]; }
if ($cat && $catName) { $q = $base; unset($q['category']); $chips[] = [$catName, jb_url($q, $self)]; }
foreach ($types as $t) {
    $q = $base; $q['type'] = array_values(array_diff($types, [$t]));
    if (!$q['type']) unset($q['type']);
    $chips[] = [$t, jb_url($q, $self)];
}

include ROOT_PATH . '/includes/header.php';
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    /* Same palette as the login page */
    .jb {
        --ink: #0f1f3d;
        --ink-soft: #566680;
        --line: #e1e7f0;
        --bg: #f4f7fb;
        --brand: #1f5fe0;
        --brand-dark: #1649b3;
        --brand-tint: #eaf1ff;
        --sky: #3b7af0;      /* login page background */
        --deep: #2563eb;     /* large circle */
        --deeper: #1c4fc4;   /* darker circle */
        font-family: 'Figtree', system-ui, -apple-system, 'Segoe UI', sans-serif;
        color: var(--ink);
    }
    .jb h1, .jb h2, .jb h3, .jb h4, .jb h5, .jb h6 {
        font-family: 'Bricolage Grotesque', 'Figtree', system-ui, sans-serif;
        letter-spacing: -.01em;
    }

    /* ---------- Public layout: blue background with circles (like login) ---------- */
    .jb--public {
        position: relative; overflow: hidden; background: var(--sky); min-height: 70vh;
    }
    .jb--public::before, .jb--public::after {
        content: ""; position: absolute; border-radius: 50%; pointer-events: none;
    }
    .jb--public::before {
        width: 1500px; height: 1500px; background: var(--deep);
        top: -1180px; left: 50%; transform: translateX(-30%);
    }
    .jb--public::after {
        width: 900px; height: 900px; background: var(--deeper); opacity: .55;
        bottom: -560px; left: -300px;
    }
    .jb--public > .container { position: relative; z-index: 1; }

    /* ---------- Intro (matches "Welcome back.") ---------- */
    .jb-intro { margin-bottom: 1.5rem; }
    .jb-intro h1 {
        font-size: clamp(2.2rem, 5vw, 3.4rem); font-weight: 800; letter-spacing: -.035em;
        line-height: 1.05; margin: 0 0 .5rem; color: #fff;
    }
    .jb-intro p { color: rgba(255,255,255,.88); font-size: 1.05rem; margin: 0; }

    /* ---------- Search bar ---------- */
    .jb-search {
        background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: .6rem;
        display: flex; gap: .5rem; align-items: stretch; margin-bottom: 1.25rem;
        box-shadow: 0 18px 40px rgba(10, 26, 68, .22);
    }
    .jb-field { flex: 1; display: flex; align-items: center; gap: .65rem; padding: 0 .9rem; min-width: 0; border-radius: 12px; transition: background .2s; }
    .jb-field + .jb-field { border-left: 1px solid var(--line); border-radius: 12px; }
    .jb-field:focus-within { background: var(--bg); }
    .jb-field i { color: var(--brand); }
    .jb-field input, .jb-field select {
        border: 0; outline: 0; width: 100%; padding: .8rem 0; font-size: .98rem; color: var(--ink); background: transparent;
        font-family: inherit;
    }
    .jb-field input::placeholder { color: #8a97ad; }
    .jb-go {
        background: var(--brand); color: #fff; border: 0; border-radius: 12px; padding: 0 1.6rem; font-weight: 700;
        letter-spacing: .02em; font-family: inherit;
        display: inline-flex; align-items: center; gap: .5rem; white-space: nowrap; transition: background .2s, transform .15s;
    }
    .jb-go:hover { background: var(--brand-dark); }
    .jb-go:active { transform: scale(.97); }
    .jb-go:focus-visible { outline: 3px solid rgba(31,95,224,.4); outline-offset: 2px; }

    /* ---------- Active filter chips ---------- */
    .jb-chips { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; margin-bottom: 1rem; }
    .jb-chip {
        display: inline-flex; align-items: center; gap: .45rem; background: var(--brand-tint); color: var(--brand-dark);
        font-size: .82rem; font-weight: 600; padding: .3rem .5rem .3rem .8rem; border-radius: 999px; text-decoration: none;
        border: 1px solid transparent; transition: background .2s;
    }
    .jb-chip:hover { background: #d6e4ff; color: var(--brand-dark); }
    .jb-chip i { font-size: .95rem; }
    .jb-clear { color: var(--ink-soft); font-size: .85rem; font-weight: 600; text-decoration: none; }
    .jb-clear:hover { color: var(--brand); text-decoration: underline; }

    /* On the blue background: outlined white pills, like the "Browse jobs" button */
    .jb--public .jb-chip { background: rgba(255,255,255,.14); color: #fff; border-color: rgba(255,255,255,.55); }
    .jb--public .jb-chip:hover { background: rgba(255,255,255,.26); color: #fff; }
    .jb--public .jb-chips > .jb-clear { color: #fff; }
    .jb--public .jb-chips > .jb-clear:hover { color: #fff; opacity: .85; }

    /* ---------- Filter sidebar (white card, same as login card) ---------- */
    .jb-filter { background: #fff; border: 1px solid var(--line); border-radius: 18px; padding: 1.25rem; box-shadow: 0 18px 40px rgba(10, 26, 68, .18); }
    .jb-filter h6 { font-size: .95rem; font-weight: 700; margin: 0 0 .75rem; }
    .jb-filter h6:not(:first-child) { margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid var(--line); }

    .jb-types { display: flex; flex-wrap: wrap; gap: .5rem; }
    .jb-type { position: relative; }
    .jb-type input { position: absolute; opacity: 0; pointer-events: none; }
    .jb-type span {
        display: inline-block; padding: .4rem .9rem; border-radius: 999px; font-size: .875rem; font-weight: 500;
        border: 1px solid #cbd5e3; color: var(--ink); cursor: pointer; user-select: none;
        transition: background .2s, color .2s, border-color .2s, transform .15s;
    }
    .jb-type span:hover { border-color: var(--brand); color: var(--brand); }
    .jb-type input:checked + span { background: var(--brand); border-color: var(--brand); color: #fff; }
    .jb-type input:focus-visible + span { outline: 3px solid rgba(31,95,224,.4); outline-offset: 2px; }
    .jb-type span:active { transform: scale(.96); }

    .jb-cat {
        display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .5rem .7rem; margin: 0 -.7rem;
        border-radius: 10px; color: var(--ink-soft); text-decoration: none; font-size: .92rem;
        transition: background .2s, color .2s, padding-left .2s;
    }
    .jb-cat:hover { background: var(--bg); color: var(--ink); padding-left: .95rem; }
    .jb-cat.active { background: var(--brand-tint); color: var(--brand-dark); font-weight: 600; }
    .jb-cat.active::after { content: "\F26A"; font-family: 'bootstrap-icons'; color: var(--brand); }

    .jb-apply {
        width: 100%; margin-top: 1.25rem; background: var(--brand); color: #fff; border: 0; border-radius: 12px;
        padding: .75rem; font-weight: 700; letter-spacing: .02em; font-family: inherit; transition: background .2s;
    }
    .jb-apply:hover { background: var(--brand-dark); }
    .jb-reset {
        display: block; text-align: center; margin-top: .6rem; color: var(--brand); font-size: .85rem; font-weight: 600; text-decoration: none;
    }
    .jb-reset:hover { color: var(--brand-dark); text-decoration: underline; }

    /* ---------- Results ---------- */
    .jb-count { color: var(--ink-soft); font-size: .92rem; margin-bottom: 1rem; }
    .jb-count b { color: var(--ink); font-size: 1.05rem; }
    .jb--public .jb-count { color: rgba(255,255,255,.88); }
    .jb--public .jb-count b { color: #fff; }
    #jobList { transition: opacity .15s ease; }
    #jobList.is-loading { opacity: .5; pointer-events: none; }

    /* ---------- Job card: hover lift + click to select ---------- */
    .jb-item { margin-bottom: 1rem; }
    .jb-item > * {
        position: relative; margin-bottom: 0 !important; cursor: pointer;
        transition: background-color .28s ease, border-color .28s ease, box-shadow .28s ease,
                    transform .22s ease, color .28s ease;
    }
    .jb-item > * * { transition: color .28s ease, background-color .28s ease, border-color .28s ease; }
    .jb--public .jb-item > * { box-shadow: 0 10px 28px rgba(10, 26, 68, .16); }
    .jb-item:hover > * {
        transform: translateY(-2px);
        border-color: #9db9f2;
        box-shadow: 0 16px 34px rgba(10, 26, 68, .26);
    }

    /* Selected = solid deep-blue card with white text */
    .jb-item.is-selected > * {
        background: var(--brand-dark) !important;
        border-color: #fff !important;
        box-shadow: 0 0 0 3px rgba(255,255,255,.9), 0 18px 38px rgba(10, 26, 68, .45);
        transform: translateY(-2px);
    }
    .jb-item.is-selected > *,
    .jb-item.is-selected > * :is(h1, h2, h3, h4, h5, h6, p, div, span, small, i, a):not(.btn):not(.text-danger) {
        color: #fff !important;
    }
    .jb-item.is-selected .text-muted { opacity: .85; }
    .jb-item.is-selected .btn-primary,
    .jb-item.is-selected .btn-primary:hover {
        background: #fff !important; border-color: #fff !important; color: var(--brand) !important;
    }

    .jb-empty { text-align: center; padding: 3.5rem 1rem; background: #fff; border: 1px dashed #c3d0e4; border-radius: 18px; }
    .jb-empty i { font-size: 2.2rem; color: var(--brand); }
    .jb-empty h5 { margin: .6rem 0 .25rem; font-weight: 700; color: var(--ink); }
    .jb-empty p { color: var(--ink-soft); margin-bottom: .75rem; }

    @media (max-width: 767.98px) {
        .jb-search { flex-direction: column; }
        .jb-field + .jb-field { border-left: 0; border-top: 1px solid var(--line); }
        .jb-go { padding: .85rem; justify-content: center; }
        .jb--public::before { width: 900px; height: 900px; top: -700px; }
    }
    @media (prefers-reduced-motion: reduce) {
        .jb *, .jb *::after { transition: none !important; }
        .jb-item:hover > *, .jb-item.is-selected > * { transform: none; }
    }
</style>

<div class="jb <?= $layout === 'public' ? 'jb--public' : '' ?>">
<div class="<?= $layout === 'public' ? 'container py-5' : '' ?>">

    <?php if ($layout === 'public'): ?>
        <div class="jb-intro">
            <h1>Browse jobs.</h1>
            <p>Search open positions and apply in a few clicks.</p>
        </div>
    <?php endif; ?>

    <!--
        The form only wraps the search bar. The filter checkboxes and the Apply button
        belong to it through the form="filterForm" attribute, and the job cards sit
        OUTSIDE the form, so Save Job / Apply Now forms inside the cards are never nested.
    -->
    <form id="filterForm" method="get" role="search">
        <div class="jb-search">
            <label class="jb-field">
                <i class="bi bi-search"></i>
                <input type="search" name="q" placeholder="Search jobs, skills, company" value="<?= e($qv) ?>" aria-label="Search jobs" autocomplete="off">
            </label>
            <label class="jb-field">
                <i class="bi bi-geo-alt"></i>
                <input type="search" name="location" placeholder="Location" value="<?= e($loc) ?>" list="locList" aria-label="Location" autocomplete="off">
                <datalist id="locList"><?php foreach ($locs as $l) echo '<option value="' . e($l['location']) . '">'; ?></datalist>
            </label>
            <label class="jb-field">
                <i class="bi bi-grid"></i>
                <select name="category" aria-label="Category">
                    <option value="0">All categories</option>
                    <?php foreach ($cats as $c) echo '<option value="' . $c['id'] . '"' . ($cat == $c['id'] ? ' selected' : '') . '>' . e($c['name']) . '</option>'; ?>
                </select>
            </label>
            <button class="jb-go" type="submit"><i class="bi bi-search"></i> SEARCH</button>
        </div>
    </form>

    <div id="jbChips">
        <?php if ($chips): ?>
            <div class="jb-chips">
                <?php foreach ($chips as [$label, $href]): ?>
                    <a class="jb-chip" href="<?= e($href) ?>" title="Remove filter"><?= e($label) ?> <i class="bi bi-x"></i></a>
                <?php endforeach; ?>
                <a class="jb-clear" href="<?= e($self) ?>">Clear all</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="row g-4">

        <!-- Filters -->
        <div class="col-lg-3">
            <button class="btn btn-outline-primary w-100 d-lg-none mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#filters"
                    <?= $layout === 'public' ? 'style="color:#fff;border-color:rgba(255,255,255,.7)"' : '' ?>>
                <i class="bi bi-funnel"></i> Filters<span id="jbFilterCount"><?= $chips ? ' (' . count($chips) . ')' : '' ?></span>
            </button>

            <div class="collapse d-lg-block" id="filters">
                <div class="jb-filter">

                    <h6>Job type</h6>
                    <div class="jb-types">
                        <?php foreach ($typeOptions as $t): ?>
                            <label class="jb-type">
                                <input type="checkbox" name="type[]" value="<?= e($t) ?>" form="filterForm" id="t<?= md5($t) ?>" <?= in_array($t, $types, true) ? 'checked' : '' ?>>
                                <span><?= e($t) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <h6>Category</h6>
                    <div id="jbCats">
                        <?php foreach ($cats as $c):
                            $q = $base; $q['category'] = $c['id']; ?>
                            <a class="jb-cat <?= $cat == $c['id'] ? 'active' : '' ?>" href="<?= e(jb_url($q, $self)) ?>"><?= e($c['name']) ?></a>
                        <?php endforeach; ?>
                    </div>

                    <button class="jb-apply" type="submit" form="filterForm">APPLY FILTERS</button>
                    <a href="<?= e($self) ?>" class="jb-reset">Clear filters</a>

                </div>
            </div>
        </div>

        <!-- Results -->
        <div class="col-lg-9">
            <div id="jobList"><?= $listHtml ?></div>
        </div>

    </div>
</div>
</div>

<script>
(function () {
    var form = document.getElementById('filterForm');
    var list = document.getElementById('jobList');
    if (!form || !list) return;

    var chips = document.getElementById('jbChips');
    var cats  = document.getElementById('jbCats');
    var count = document.getElementById('jbFilterCount');
    var seq = 0, timer = null;

    // URL for the current form state (empty values dropped, page resets to 1)
    function currentUrl() {
        var all = new URLSearchParams(new FormData(form)), out = new URLSearchParams();
        all.forEach(function (v, k) {
            v = String(v).trim();
            if (v === '' || (k === 'category' && v === '0')) return;
            out.append(k, v);
        });
        var qs = out.toString();
        return location.pathname + (qs ? '?' + qs : '');
    }

    // Reload the results in place. Falls back to a normal page load if anything fails.
    function refresh() {
        var url = currentUrl(), mine = ++seq;
        list.classList.add('is-loading');
        fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
            .then(function (r) { if (!r.ok) throw new Error('http'); return r.text(); })
            .then(function (html) {
                if (mine !== seq) return;                       // a newer request is already running
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var nl = doc.getElementById('jobList');
                if (!nl) throw new Error('markup');
                list.innerHTML = nl.innerHTML;
                [['jbChips', chips], ['jbCats', cats], ['jbFilterCount', count]].forEach(function (p) {
                    var src = doc.getElementById(p[0]);
                    if (src && p[1]) p[1].innerHTML = src.innerHTML;
                });
                history.replaceState(null, '', url);
                list.classList.remove('is-loading');
            })
            .catch(function () { if (mine === seq) form.submit(); });
    }

    form.addEventListener('submit', function (e) { e.preventDefault(); clearTimeout(timer); refresh(); });

    // Job type pills and category apply immediately
    document.querySelectorAll('input[name="type[]"]').forEach(function (el) { el.addEventListener('change', refresh); });
    var catSel = form.querySelector('select[name="category"]');
    if (catSel) catSel.addEventListener('change', refresh);

    // Typing searches after a short pause
    form.querySelectorAll('input[name="q"], input[name="location"]').forEach(function (el) {
        el.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(refresh, 350); });
    });

    // Click a job to select it. Click again to unselect.
    list.addEventListener('click', function (e) {
        // Apply Now, the heart and any other link or button keep working normally
        if (e.target.closest('a, button, input, label')) return;

        var item = e.target.closest('.jb-item');
        if (!item || !list.contains(item)) return;

        var wasSelected = item.classList.contains('is-selected');
        list.querySelectorAll('.jb-item.is-selected').forEach(function (el) {
            el.classList.remove('is-selected');
        });
        if (!wasSelected) item.classList.add('is-selected');
    });
})();
</script>

<?php include ROOT_PATH . '/includes/footer.php'; ?>