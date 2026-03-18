<?php

$page_title = 'Changelog';
$current_page = 'changelog';

$changelog_file = __DIR__ . '/CHANGELOG.md';
$raw = file_exists($changelog_file)
    ? trim((string)file_get_contents($changelog_file))
    : "# Changelog\n\nCHANGELOG.md was not found.";

function render_changelog_markdown(string $text): string
{
    $lines = preg_split("/\r\n|\n|\r/", $text);
    $html = '';
    $inList = false;

    foreach ($lines as $line) {
        $trim = trim($line);

        if ($trim === '') {
            if ($inList) {
                $html .= "</ul>";
                $inList = false;
            }
            continue;
        }

        if (str_starts_with($trim, '# ')) {
            if ($inList) {
                $html .= "</ul>";
                $inList = false;
            }
            $html .= '<h1>' . h(substr($trim, 2)) . '</h1>';
            continue;
        }

        if (str_starts_with($trim, '## ')) {
            if ($inList) {
                $html .= "</ul>";
                $inList = false;
            }
            $html .= '<h2>' . h(substr($trim, 3)) . '</h2>';
            continue;
        }

        if (str_starts_with($trim, '### ')) {
            if ($inList) {
                $html .= "</ul>";
                $inList = false;
            }
            $html .= '<h3>' . h(substr($trim, 4)) . '</h3>';
            continue;
        }

        if (preg_match('/^- (.+)$/', $trim, $m)) {
            if (!$inList) {
                $html .= "<ul>";
                $inList = true;
            }
            $html .= '<li>' . h($m[1]) . '</li>';
            continue;
        }

        if ($inList) {
            $html .= "</ul>";
            $inList = false;
        }

        $html .= '<p>' . h($trim) . '</p>';
    }

    if ($inList) {
        $html .= "</ul>";
    }

    return $html;
}

$changelog_html = render_changelog_markdown($raw);
?>

<div class="page-head">
    <h2>Change Log</h2>
    <p class="subtext">Version history for <?= h($APP_NAME) ?>.</p>
</div>

<div class="card changelog-card">
    <div class="changelog-content">
        <?= $changelog_html ?>
    </div>
</div>

<style>
.changelog-card {
    padding: 0;
    overflow: hidden;
}

.changelog-content {
    padding: 22px 24px;
    line-height: 1.6;
}

.changelog-content h1 {
    margin: 0 0 16px;
    font-size: 28px;
}

.changelog-content h2 {
    margin: 28px 0 12px;
    font-size: 22px;
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 6px;
}

.changelog-content h3 {
    margin: 20px 0 10px;
    font-size: 17px;
}

.changelog-content p {
    margin: 0 0 12px;
}

.changelog-content ul {
    margin: 0 0 16px 20px;
    padding: 0;
}

.changelog-content li {
    margin-bottom: 8px;
}
</style>