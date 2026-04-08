<?php
$wiki_pages = [
    'index' => 'Overview',
    'getting_started' => 'Getting Started',
    'core_concepts' => 'Core Concepts',
    'quick_entry' => 'Quick Entry',
    'templates_batches' => 'Templates & Batches',
    'balance_tracking' => 'Balance & Tracking',
    'ai_import' => 'AI Import',
    'transfers' => 'Transfers & Wallet Logic',
    'categories' => 'Categories',
    'dashboard' => 'Dashboard',
    'graphs' => 'Graphs',
    'data_integrity' => 'Data Integrity',
    'settings' => 'Settings',
    'backup_restore' => 'Backup & Restore',
    'troubleshooting' => 'Troubleshooting',
];

$slug = $_GET['slug'] ?? 'index';
$slug = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$slug);
if (!isset($wiki_pages[$slug])) {
    $slug = 'index';
}

$page_file = __DIR__ . '/wiki/' . $slug . '.html';
$page_title = $wiki_pages[$slug];
$page_html = is_file($page_file)
    ? file_get_contents($page_file)
    : '<div class="wiki-prose"><h2>Page not found</h2><p>The requested wiki page could not be found.</p></div>';
?>

<style>
.wiki-layout { display:grid; grid-template-columns:280px 1fr; gap:18px; align-items:start; }
.wiki-sidebar { position:sticky; top:16px; }
.wiki-card { overflow:hidden; }
.wiki-toolbar { display:flex; gap:10px; flex-wrap:wrap; margin:0 0 14px; }
.wiki-search { width:100%; padding:10px 12px; border:1px solid #d7dce3; border-radius:10px; font:inherit; background:#fff; }
.wiki-nav { list-style:none; margin:0; padding:0; display:grid; gap:8px; }
.wiki-nav a { display:block; padding:10px 12px; border-radius:10px; text-decoration:none; color:#2d3748; transition:all .15s ease; background:#f8fafc; border:1px solid #e5e7eb; }
.wiki-nav a:hover, .wiki-nav a.active { background:#eef4ff; border-color:#c9d8ff; color:#1f3b7a; }
.wiki-nav li.hidden-by-search { display:none; }
.wiki-prose { color:#273444; }
.wiki-hero { display:flex; justify-content:space-between; gap:16px; align-items:flex-start; margin-bottom:16px; }
.wiki-eyebrow { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:#61708a; margin-bottom:6px; }
.wiki-prose h1 { margin:0 0 8px; font-size:30px; line-height:1.15; }
.wiki-intro { margin:0; color:#55657d; max-width:900px; }
.wiki-hero-actions { display:flex; gap:8px; flex-wrap:wrap; }
.wiki-btn, .wiki-copy { appearance:none; border:1px solid #d7dce3; background:#fff; color:#2d3748; border-radius:10px; padding:8px 11px; cursor:pointer; font:inherit; }
.wiki-btn:hover, .wiki-copy:hover { background:#f7fafc; }
.details-count { font-size:12px; color:#77839a; }
.wiki-grid { display:grid; gap:14px; margin:14px 0; }
.wiki-grid.two { grid-template-columns:repeat(2, minmax(0, 1fr)); }
.wiki-panel { border:1px solid #e5e7eb; background:#fafbfd; border-radius:14px; padding:14px; }
.wiki-panel h3 { margin:0 0 8px; font-size:16px; }
.wiki-panel p, .wiki-panel ul { margin:0; }
.wiki-panel.success { background:#f1fbf4; border-color:#ccebd3; }
.wiki-panel.danger { background:#fff4f4; border-color:#f0caca; }
.wiki-panel.accent { background:#f5f1ff; border-color:#d7c8ff; }
.wiki-callout { padding:12px 14px; border-radius:12px; margin:0 0 14px; border:1px solid transparent; }
.wiki-callout.info { background:#f2f7ff; border-color:#cfe0ff; }
.wiki-callout.warn { background:#fff8e8; border-color:#f2dfaa; }
.wiki-callout.success { background:#eefbf2; border-color:#cde9d6; }
.wiki-section { border:1px solid #e5e7eb; border-radius:14px; padding:0; margin:0 0 14px; background:#fff; overflow:hidden; }
.wiki-section > summary { list-style:none; cursor:pointer; padding:14px 16px; font-weight:700; position:relative; background:#fcfcfd; }
.wiki-section > summary::-webkit-details-marker { display:none; }
.wiki-section > summary::after { content:'+'; position:absolute; right:16px; top:11px; font-size:22px; color:#8090a8; }
.wiki-section[open] > summary::after { content:'−'; }
.wiki-section > div { padding:0 16px 16px; }
.wiki-section ul, .wiki-section ol { margin:0; padding-left:22px; }
.wiki-section p { margin:0 0 10px; }
.wiki-section li + li { margin-top:6px; }
.wiki-prose pre { background:#0f172a; color:#e2e8f0; padding:14px; border-radius:12px; overflow:auto; margin:0 0 10px; }
.wiki-prose code { font-family:Consolas, Monaco, monospace; }
.wiki-empty { padding:12px; color:#77839a; border:1px dashed #d7dce3; border-radius:10px; }
@media (max-width: 980px) {
  .wiki-layout { grid-template-columns:1fr; }
  .wiki-sidebar { position:static; }
  .wiki-grid.two { grid-template-columns:1fr; }
  .wiki-hero { flex-direction:column; }
}
</style>

<div class="page-head">
    <h2>📘 Wiki</h2>
    <p class="subtext">Built-in documentation for RewardLedger. These pages are static HTML files loaded by the app so they are easy to edit, back up, and ship with the project.</p>
</div>

<div class="wiki-layout">
    <div class="wiki-sidebar">
        <div class="card">
            <div class="wiki-toolbar">
                <input type="search" id="wikiSearch" class="wiki-search" placeholder="Search wiki pages..." aria-label="Search wiki pages">
            </div>
            <ul class="wiki-nav" id="wikiNav">
                <?php foreach ($wiki_pages as $page_slug => $title): ?>
                    <li data-title="<?= h(strtolower($title)) ?>" data-slug="<?= h(strtolower($page_slug)) ?>">
                        <a href="index.php?page=wiki&slug=<?= h($page_slug) ?>" class="<?= $slug === $page_slug ? 'active' : '' ?>">
                            <?= h($title) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div id="wikiSearchEmpty" class="wiki-empty" style="display:none; margin-top:12px;">No wiki pages matched your search.</div>
        </div>
    </div>

    <div class="wiki-main">
        <div class="card wiki-card">
            <?= $page_html ?>
        </div>
    </div>
</div>

<script>
(function () {
  const nav = document.getElementById('wikiNav');
  const search = document.getElementById('wikiSearch');
  const empty = document.getElementById('wikiSearchEmpty');
  if (nav && search) {
    const items = Array.from(nav.querySelectorAll('li'));
    const filter = () => {
      const q = search.value.trim().toLowerCase();
      let visible = 0;
      items.forEach((li) => {
        const hay = (li.dataset.title + ' ' + li.dataset.slug).toLowerCase();
        const show = !q || hay.includes(q);
        li.classList.toggle('hidden-by-search', !show);
        if (show) visible++;
      });
      if (empty) empty.style.display = visible ? 'none' : 'block';
    };
    search.addEventListener('input', filter);
  }

  const sections = Array.from(document.querySelectorAll('.wiki-section'));
  document.querySelectorAll('.wiki-expand-all').forEach((btn) => {
    btn.addEventListener('click', () => sections.forEach((section) => section.open = true));
  });
  document.querySelectorAll('.wiki-collapse-all').forEach((btn) => {
    btn.addEventListener('click', () => sections.forEach((section) => section.open = false));
  });

  document.querySelectorAll('.wiki-copy').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const original = btn.textContent;
      const text = btn.getAttribute('data-copy') || '';
      try {
        await navigator.clipboard.writeText(text.replace(/&#10;/g, '
'));
        btn.textContent = 'Copied!';
      } catch (err) {
        btn.textContent = 'Copy failed';
      }
      setTimeout(() => btn.textContent = original, 1200);
    });
  });
})();
</script>
