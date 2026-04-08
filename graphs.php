<?php
$current_page = 'graphs';

function rl_graph_build_in_clause(array $ids): string
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) {
        return '';
    }
    return implode(',', $ids);
}

function rl_graph_format_period_label(string $date, string $group_by): string
{
    $ts = strtotime($date);
    if (!$ts) {
        return $date;
    }

    if ($group_by === 'day') {
        return date('M j, Y', $ts);
    }

    if ($group_by === 'week') {
        return 'Week of ' . date('M j, Y', $ts);
    }

    return date('M Y', $ts);
}

function rl_graph_bucket_date(string $date, string $group_by): string
{
    $ts = strtotime($date);
    if (!$ts) {
        return $date;
    }

    if ($group_by === 'day') {
        return date('Y-m-d', $ts);
    }

    if ($group_by === 'week') {
        $weekDay = (int)date('N', $ts);
        $mondayTs = strtotime('-' . ($weekDay - 1) . ' days', $ts);
        return date('Y-m-d', $mondayTs);
    }

    return date('Y-m-01', $ts);
}

function rl_graph_behavior_mode(string $behaviorType): string
{
    $value = strtolower(trim($behaviorType));

    if (in_array($value, ['income', 'profit', 'positive'], true)) {
        return 'income';
    }

    if (in_array($value, ['expense', 'withdrawal', 'negative', 'investment'], true)) {
        return 'expense';
    }

    return 'other';
}

function rl_graph_money(float $value): string
{
    $prefix = $value < 0 ? '-' : '';
    return $prefix . '$' . number_format(abs($value), 2);
}

function rl_graph_palette_classes(): array
{
    return [
        'graph-seg-1',
        'graph-seg-2',
        'graph-seg-3',
        'graph-seg-4',
        'graph-seg-5',
        'graph-seg-6',
        'graph-seg-7',
        'graph-seg-8',
        'graph-seg-9',
        'graph-seg-10',
        'graph-seg-11',
        'graph-seg-12',
    ];
}


function rl_graph_svg_line(array $points, array $labels): string
{
    if (!$points) {
        return '<div class="graph-empty">No chart data matched the current filters.</div>';
    }

    $width = 920;
    $height = 340;
    $left = 56;
    $right = 18;
    $top = 18;
    $bottom = 64;
    $plotWidth = $width - $left - $right;
    $plotHeight = $height - $top - $bottom;

    $min = min($points);
    $max = max($points);
    if ($min === $max) {
        $min -= 1;
        $max += 1;
    }

    $range = $max - $min;
    $stepX = count($points) > 1 ? $plotWidth / (count($points) - 1) : 0;

    $coords = [];
    foreach ($points as $index => $value) {
        $x = $left + ($stepX * $index);
        $ratio = ($value - $min) / $range;
        $y = $top + $plotHeight - ($ratio * $plotHeight);
        $coords[] = ['x' => $x, 'y' => $y, 'value' => $value, 'label' => $labels[$index] ?? ''];
    }

    $polyline = [];
    foreach ($coords as $coord) {
        $polyline[] = round($coord['x'], 2) . ',' . round($coord['y'], 2);
    }

    $ticks = [];
    for ($i = 0; $i < 5; $i++) {
        $tickValue = $min + (($range / 4) * $i);
        $ratio = ($tickValue - $min) / $range;
        $y = $top + $plotHeight - ($ratio * $plotHeight);
        $ticks[] = ['y' => $y, 'value' => $tickValue];
    }

    ob_start();
    ?>
    <svg viewBox="0 0 <?= $width ?> <?= $height ?>" class="graph-svg" role="img" aria-label="Net profit over time line chart">
        <?php foreach ($ticks as $tick): ?>
            <line x1="<?= $left ?>" y1="<?= round($tick['y'], 2) ?>" x2="<?= $width - $right ?>" y2="<?= round($tick['y'], 2) ?>" class="graph-grid-line"></line>
            <text x="<?= $left - 8 ?>" y="<?= round($tick['y'] + 4, 2) ?>" text-anchor="end" class="graph-axis-label"><?= h(rl_graph_money((float)$tick['value'])) ?></text>
        <?php endforeach; ?>

        <line x1="<?= $left ?>" y1="<?= $top ?>" x2="<?= $left ?>" y2="<?= $height - $bottom ?>" class="graph-axis-line"></line>
        <line x1="<?= $left ?>" y1="<?= $height - $bottom ?>" x2="<?= $width - $right ?>" y2="<?= $height - $bottom ?>" class="graph-axis-line"></line>

        <polyline points="<?= h(implode(' ', $polyline)) ?>" fill="none" class="graph-line-path"></polyline>

        <?php foreach ($coords as $index => $coord): ?>
            <circle cx="<?= round($coord['x'], 2) ?>" cy="<?= round($coord['y'], 2) ?>" r="4.5" class="graph-line-point">
                <title><?= h(($coord['label'] !== '' ? $coord['label'] . ': ' : '') . rl_graph_money((float)$coord['value'])) ?></title>
            </circle>
            <?php if ($index === 0 || $index === count($coords) - 1 || $index % max(1, (int)ceil(count($coords) / 6)) === 0): ?>
                <text x="<?= round($coord['x'], 2) ?>" y="<?= $height - 14 ?>" text-anchor="middle" class="graph-axis-label"><?= h($coord['label']) ?></text>
            <?php endif; ?>
        <?php endforeach; ?>
    </svg>
    <?php
    return (string)ob_get_clean();
}

function rl_graph_svg_bars(array $income, array $expense, array $labels): string
{
    if (!$labels) {
        return '<div class="graph-empty">No chart data matched the current filters.</div>';
    }

    $width = 920;
    $height = 320;
    $left = 56;
    $right = 18;
    $top = 18;
    $bottom = 42;
    $plotWidth = $width - $left - $right;
    $plotHeight = $height - $top - $bottom;
    $maxValue = max(1.0, max($income + $expense));
    $groupWidth = $plotWidth / max(1, count($labels));
    $barWidth = max(10, min(28, ($groupWidth - 12) / 2));

    ob_start();
    ?>
    <svg viewBox="0 0 <?= $width ?> <?= $height ?>" class="graph-svg" role="img" aria-label="Income versus expense bar chart">
        <?php for ($i = 0; $i < 5; $i++):
            $tickValue = ($maxValue / 4) * $i;
            $y = $top + $plotHeight - (($tickValue / $maxValue) * $plotHeight);
        ?>
            <line x1="<?= $left ?>" y1="<?= round($y, 2) ?>" x2="<?= $width - $right ?>" y2="<?= round($y, 2) ?>" class="graph-grid-line"></line>
            <text x="<?= $left - 8 ?>" y="<?= round($y + 4, 2) ?>" text-anchor="end" class="graph-axis-label"><?= h(rl_graph_money((float)$tickValue)) ?></text>
        <?php endfor; ?>

        <line x1="<?= $left ?>" y1="<?= $top ?>" x2="<?= $left ?>" y2="<?= $height - $bottom ?>" class="graph-axis-line"></line>
        <line x1="<?= $left ?>" y1="<?= $height - $bottom ?>" x2="<?= $width - $right ?>" y2="<?= $height - $bottom ?>" class="graph-axis-line"></line>

        <?php foreach ($labels as $index => $label):
            $groupX = $left + ($groupWidth * $index);
            $incomeHeight = (($income[$index] ?? 0) / $maxValue) * $plotHeight;
            $expenseHeight = (($expense[$index] ?? 0) / $maxValue) * $plotHeight;
            $incomeX = $groupX + max(4, ($groupWidth / 2) - $barWidth - 2);
            $expenseX = $groupX + max(6, ($groupWidth / 2) + 2);
            $incomeY = $top + $plotHeight - $incomeHeight;
            $expenseY = $top + $plotHeight - $expenseHeight;
        ?>
            <rect x="<?= round($incomeX, 2) ?>" y="<?= round($incomeY, 2) ?>" width="<?= round($barWidth, 2) ?>" height="<?= round($incomeHeight, 2) ?>" rx="4" class="graph-bar-income">
                <title><?= h($label . ': Income ' . rl_graph_money((float)($income[$index] ?? 0))) ?></title>
            </rect>
            <rect x="<?= round($expenseX, 2) ?>" y="<?= round($expenseY, 2) ?>" width="<?= round($barWidth, 2) ?>" height="<?= round($expenseHeight, 2) ?>" rx="4" class="graph-bar-expense">
                <title><?= h($label . ': Expense ' . rl_graph_money((float)($expense[$index] ?? 0))) ?></title>
            </rect>
            <?php if ($index === 0 || $index === count($labels) - 1 || $index % max(1, (int)ceil(count($labels) / 6)) === 0): ?>
                <text x="<?= round($groupX + ($groupWidth / 2), 2) ?>" y="<?= $height - 14 ?>" text-anchor="middle" class="graph-axis-label"><?= h($label) ?></text>
            <?php endif; ?>
        <?php endforeach; ?>
    </svg>
    <?php
    return (string)ob_get_clean();
}

function rl_graph_svg_stacked_bars(array $periods, array $selectedAppNames = []): string
{
    if (!$periods) {
        return '<div class="graph-empty">No chart data matched the current filters.</div>';
    }

    $palette = rl_graph_palette_classes();
    $legendMap = [];
    foreach ($selectedAppNames as $appName) {
        $appName = trim((string)$appName);
        if ($appName === '' || isset($legendMap[$appName])) {
            continue;
        }
        $legendMap[$appName] = $palette[count($legendMap) % count($palette)];
    }
    $maxValue = 1.0;
    foreach ($periods as $period) {
        $incomeTotal = 0.0;
        foreach (($period['income'] ?? []) as $appName => $value) {
            $incomeTotal += (float)$value;
            if (!isset($legendMap[$appName])) {
                $legendMap[$appName] = $palette[count($legendMap) % count($palette)];
            }
        }

        $expenseTotal = 0.0;
        foreach (($period['expense'] ?? []) as $appName => $value) {
            $expenseTotal += (float)$value;
            if (!isset($legendMap[$appName])) {
                $legendMap[$appName] = $palette[count($legendMap) % count($palette)];
            }
        }

        $maxValue = max($maxValue, $incomeTotal, $expenseTotal);
    }

    if (!$legendMap) {
        return '<div class="graph-empty">No chart data matched the current filters.</div>';
    }

    $width = 920;
    $height = 320;
    $left = 56;
    $right = 18;
    $top = 18;
    $bottom = 42;
    $plotWidth = $width - $left - $right;
    $plotHeight = $height - $top - $bottom;
    $groupWidth = $plotWidth / max(1, count($periods));
    $barWidth = max(12, min(30, ($groupWidth - 16) / 2));

    ob_start();
    ?>
    <div class="graph-stack-wrap">
        <svg viewBox="0 0 <?= $width ?> <?= $height ?>" class="graph-svg" role="img" aria-label="Income versus expense stacked bar chart by app">
            <?php for ($i = 0; $i < 5; $i++):
                $tickValue = ($maxValue / 4) * $i;
                $y = $top + $plotHeight - (($tickValue / $maxValue) * $plotHeight);
            ?>
                <line x1="<?= $left ?>" y1="<?= round($y, 2) ?>" x2="<?= $width - $right ?>" y2="<?= round($y, 2) ?>" class="graph-grid-line"></line>
                <text x="<?= $left - 8 ?>" y="<?= round($y + 4, 2) ?>" text-anchor="end" class="graph-axis-label"><?= h(rl_graph_money((float)$tickValue)) ?></text>
            <?php endfor; ?>

            <line x1="<?= $left ?>" y1="<?= $top ?>" x2="<?= $left ?>" y2="<?= $height - $bottom ?>" class="graph-axis-line"></line>
            <line x1="<?= $left ?>" y1="<?= $height - $bottom ?>" x2="<?= $width - $right ?>" y2="<?= $height - $bottom ?>" class="graph-axis-line"></line>

            <?php foreach ($periods as $index => $period):
                $label = (string)($period['label'] ?? '');
                $groupX = $left + ($groupWidth * $index);
                $incomeX = $groupX + max(4, ($groupWidth / 2) - $barWidth - 2);
                $expenseX = $groupX + max(6, ($groupWidth / 2) + 2);

                $incomeBaseY = $top + $plotHeight;
                foreach (($period['income'] ?? []) as $appName => $value):
                    $segmentHeight = (((float)$value) / $maxValue) * $plotHeight;
                    if ($segmentHeight <= 0) {
                        continue;
                    }
                    $incomeBaseY -= $segmentHeight;
                    $segmentClass = $legendMap[$appName] ?? $palette[0];
            ?>
                <rect x="<?= round($incomeX, 2) ?>" y="<?= round($incomeBaseY, 2) ?>" width="<?= round($barWidth, 2) ?>" height="<?= round($segmentHeight, 2) ?>" rx="0" class="graph-bar-stack <?= h($segmentClass) ?>">
                    <title><?= h($label . ': Income / ' . $appName . ' / ' . rl_graph_money((float)$value)) ?></title>
                </rect>
            <?php endforeach; ?>

            <?php
                $expenseBaseY = $top + $plotHeight;
                foreach (($period['expense'] ?? []) as $appName => $value):
                    $segmentHeight = (((float)$value) / $maxValue) * $plotHeight;
                    if ($segmentHeight <= 0) {
                        continue;
                    }
                    $expenseBaseY -= $segmentHeight;
                    $segmentClass = $legendMap[$appName] ?? $palette[0];
            ?>
                <rect x="<?= round($expenseX, 2) ?>" y="<?= round($expenseBaseY, 2) ?>" width="<?= round($barWidth, 2) ?>" height="<?= round($segmentHeight, 2) ?>" rx="0" class="graph-bar-stack <?= h($segmentClass) ?>">
                    <title><?= h($label . ': Expense / ' . $appName . ' / ' . rl_graph_money((float)$value)) ?></title>
                </rect>
            <?php endforeach; ?>

            <text x="<?= round($incomeX + ($barWidth / 2), 2) ?>" y="<?= $height - 34 ?>" text-anchor="middle" class="graph-bar-sub-label">Inc.</text>
            <text x="<?= round($expenseX + ($barWidth / 2), 2) ?>" y="<?= $height - 34 ?>" text-anchor="middle" class="graph-bar-sub-label">Exp.</text>
            <?php if ($index === 0 || $index === count($periods) - 1 || $index % max(1, (int)ceil(count($periods) / 6)) === 0): ?>
                <text x="<?= round($groupX + ($groupWidth / 2), 2) ?>" y="<?= $height - 14 ?>" text-anchor="middle" class="graph-axis-label"><?= h($label) ?></text>
            <?php endif; ?>
            <?php endforeach; ?>
        </svg>

        <div class="graph-app-legend">
            <?php foreach ($legendMap as $appName => $segmentClass): ?>
                <div class="graph-legend-row">
                    <span class="graph-legend-dot <?= h($segmentClass) ?>"></span>
                    <span class="graph-legend-label"><?= h($appName) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return (string)ob_get_clean();
}

function rl_graph_svg_donut(array $segments): string
{
    if (!$segments) {
        return '<div class="graph-empty">No category totals matched the current filters.</div>';
    }

    $total = 0.0;
    foreach ($segments as $segment) {
        $total += (float)$segment['value'];
    }
    if ($total <= 0) {
        return '<div class="graph-empty">No category totals matched the current filters.</div>';
    }

    $circumference = 2 * M_PI * 70;
    $palette = rl_graph_palette_classes();
    $offset = 0.0;

    ob_start();
    ?>
    <div class="graph-donut-wrap">
        <svg viewBox="0 0 200 200" class="graph-donut" role="img" aria-label="Category breakdown donut chart">
            <circle cx="100" cy="100" r="70" class="graph-donut-base"></circle>
            <?php foreach ($segments as $index => $segment):
                $value = (float)$segment['value'];
                $dash = ($value / $total) * $circumference;
                $class = $palette[$index % count($palette)];
            ?>
                <circle
                    cx="100"
                    cy="100"
                    r="70"
                    class="graph-donut-segment <?= h($class) ?>"
                    stroke-dasharray="<?= round($dash, 2) ?> <?= round($circumference - $dash, 2) ?>"
                    stroke-dashoffset="<?= round(-$offset, 2) ?>"
                >
                    <title><?= h($segment['label'] . ': ' . rl_graph_money($value)) ?></title>
                </circle>
            <?php
                $offset += $dash;
            endforeach; ?>
            <circle cx="100" cy="100" r="48" class="graph-donut-hole"></circle>
            <text x="100" y="94" text-anchor="middle" class="graph-donut-total-label">Total</text>
            <text x="100" y="116" text-anchor="middle" class="graph-donut-total-value"><?= h(rl_graph_money($total)) ?></text>
        </svg>
        <div class="graph-legend">
            <?php foreach ($segments as $index => $segment):
                $class = $palette[$index % count($palette)];
                $percent = $total > 0 ? (((float)$segment['value'] / $total) * 100) : 0;
            ?>
                <div class="graph-legend-row">
                    <span class="graph-legend-dot <?= h($class) ?>"></span>
                    <span class="graph-legend-label"><?= h($segment['label']) ?></span>
                    <span class="graph-legend-value"><?= h(rl_graph_money((float)$segment['value'])) ?> · <?= h(number_format($percent, 1)) ?>%</span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return (string)ob_get_clean();
}

$dateMode = $_GET['date_mode'] ?? get_setting($conn, 'dashboard_date_mode', 'all_time');
if (!in_array($dateMode, ['all_time', 'current_year', 'custom'], true)) {
    $dateMode = 'all_time';
}

$groupBy = $_GET['group_by'] ?? 'month';
if (!in_array($groupBy, ['day', 'week', 'month'], true)) {
    $groupBy = 'month';
}

$displayMode = $_GET['display_mode'] ?? 'stacked_apps';
if (!in_array($displayMode, ['combined', 'stacked_apps'], true)) {
    $displayMode = 'stacked_apps';
}

$currentYearStart = date('Y-01-01');
$startDate = $_GET['start_date'] ?? ($dateMode === 'current_year' ? $currentYearStart : '');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

if ($dateMode !== 'custom') {
    if ($dateMode === 'current_year') {
        $startDate = $currentYearStart;
        $endDate = date('Y-m-d');
    } else {
        $startDate = '';
        $endDate = '';
    }
}

$selectedApps = isset($_GET['apps']) && is_array($_GET['apps']) ? array_values(array_filter(array_map('intval', $_GET['apps']))) : [];
$selectedCategories = isset($_GET['categories']) && is_array($_GET['categories']) ? array_values(array_filter(array_map('intval', $_GET['categories']))) : [];
$selectedAssets = isset($_GET['assets']) && is_array($_GET['assets']) ? array_values(array_filter(array_map('intval', $_GET['assets']))) : [];

$appOptions = [];
$appRes = $conn->query("SELECT id, app_name FROM apps WHERE is_active = 1 ORDER BY sort_order ASC, app_name ASC");
if ($appRes) {
    while ($row = $appRes->fetch_assoc()) {
        $appOptions[] = $row;
    }
}

$appNameOrder = [];
foreach ($appOptions as $appOption) {
    $name = trim((string)($appOption['app_name'] ?? ''));
    if ($name !== '') {
        $appNameOrder[$name] = count($appNameOrder);
    }
}

$appOptionsById = [];
foreach ($appOptions as $appOption) {
    $appId = (int)($appOption['id'] ?? 0);
    if ($appId > 0) {
        $appOptionsById[$appId] = (string)($appOption['app_name'] ?? '');
    }
}

$selectedAppNames = [];
if ($selectedApps) {
    foreach ($selectedApps as $appId) {
        if (isset($appOptionsById[$appId])) {
            $appName = trim((string)$appOptionsById[$appId]);
            if ($appName !== '' && !in_array($appName, $selectedAppNames, true)) {
                $selectedAppNames[] = $appName;
            }
        }
    }
}

$categoryOptions = [];
$catRes = $conn->query("SELECT c.id, c.category_name, ap.app_name FROM categories c LEFT JOIN apps ap ON ap.id = c.app_id WHERE c.is_active = 1 ORDER BY c.sort_order ASC, c.category_name ASC");
if ($catRes) {
    while ($row = $catRes->fetch_assoc()) {
        $categoryOptions[] = $row;
    }
}

$assetOptions = [];
$assetRes = $conn->query("SELECT id, asset_name, asset_symbol FROM assets WHERE is_active = 1 ORDER BY sort_order ASC, asset_name ASC");
if ($assetRes) {
    while ($row = $assetRes->fetch_assoc()) {
        $assetOptions[] = $row;
    }
}

$whereParts = ['1=1'];
if ($startDate !== '') {
    $whereParts[] = "b.batch_date >= '" . $conn->real_escape_string($startDate) . "'";
}
if ($endDate !== '') {
    $whereParts[] = "b.batch_date <= '" . $conn->real_escape_string($endDate) . "'";
}
$appClause = rl_graph_build_in_clause($selectedApps);
if ($appClause !== '') {
    $whereParts[] = "b.app_id IN ($appClause)";
}
$categoryClause = rl_graph_build_in_clause($selectedCategories);
if ($categoryClause !== '') {
    $whereParts[] = "bi.category_id IN ($categoryClause)";
}
$assetClause = rl_graph_build_in_clause($selectedAssets);
if ($assetClause !== '') {
    $whereParts[] = "bi.asset_id IN ($assetClause)";
}

$sql = "
    SELECT
        b.batch_date,
        bi.amount,
        c.category_name,
        c.behavior_type,
        ap.app_name,
        a.asset_name,
        a.asset_symbol
    FROM batch_items bi
    INNER JOIN batches b ON b.id = bi.batch_id
    LEFT JOIN categories c ON c.id = bi.category_id
    LEFT JOIN apps ap ON ap.id = b.app_id
    LEFT JOIN assets a ON a.id = bi.asset_id
    WHERE " . implode(' AND ', $whereParts) . "
    ORDER BY b.batch_date ASC, bi.id ASC
";

$result = $conn->query($sql);
$rows = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

$periodStats = [];
$periodAppStats = [];
$categoryBreakdown = [];
$totalIncome = 0.0;
$totalExpense = 0.0;

foreach ($rows as $row) {
    $date = (string)($row['batch_date'] ?? '');
    if ($date === '') {
        continue;
    }
    $bucket = rl_graph_bucket_date($date, $groupBy);
    if (!isset($periodStats[$bucket])) {
        $periodStats[$bucket] = ['income' => 0.0, 'expense' => 0.0, 'net' => 0.0];
    }
    if (!isset($periodAppStats[$bucket])) {
        $periodAppStats[$bucket] = ['income' => [], 'expense' => []];
    }

    $amount = (float)($row['amount'] ?? 0);
    $magnitude = abs($amount);
    $mode = rl_graph_behavior_mode((string)($row['behavior_type'] ?? ''));
    $categoryName = trim((string)($row['category_name'] ?? 'Uncategorized'));
    $appName = trim((string)($row['app_name'] ?? 'Unassigned App'));
    if ($appName === '') {
        $appName = 'Unassigned App';
    }

    if ($mode === 'income') {
        $periodStats[$bucket]['income'] += $magnitude;
        $periodStats[$bucket]['net'] += $magnitude;
        $totalIncome += $magnitude;

        if (!isset($periodAppStats[$bucket]['income'][$appName])) {
            $periodAppStats[$bucket]['income'][$appName] = 0.0;
        }
        $periodAppStats[$bucket]['income'][$appName] += $magnitude;

        if (!isset($categoryBreakdown[$categoryName])) {
            $categoryBreakdown[$categoryName] = 0.0;
        }
        $categoryBreakdown[$categoryName] += $magnitude;
    } elseif ($mode === 'expense') {
        $periodStats[$bucket]['expense'] += $magnitude;
        $periodStats[$bucket]['net'] -= $magnitude;
        $totalExpense += $magnitude;

        if (!isset($periodAppStats[$bucket]['expense'][$appName])) {
            $periodAppStats[$bucket]['expense'][$appName] = 0.0;
        }
        $periodAppStats[$bucket]['expense'][$appName] += $magnitude;

        if (!isset($categoryBreakdown[$categoryName])) {
            $categoryBreakdown[$categoryName] = 0.0;
        }
        $categoryBreakdown[$categoryName] += $magnitude;
    }
}

ksort($periodStats);
ksort($periodAppStats);
arsort($categoryBreakdown);

$lineLabels = [];
$lineValues = [];
$barIncome = [];
$barExpense = [];
$stackedPeriods = [];
foreach ($periodStats as $bucket => $stats) {
    $lineLabels[] = rl_graph_format_period_label($bucket, $groupBy);
    $lineValues[] = (float)$stats['net'];
    $barIncome[] = (float)$stats['income'];
    $barExpense[] = (float)$stats['expense'];

    $incomeApps = $periodAppStats[$bucket]['income'] ?? [];
    $expenseApps = $periodAppStats[$bucket]['expense'] ?? [];

    uksort($incomeApps, static function ($a, $b) use ($appNameOrder) {
        $aOrder = $appNameOrder[$a] ?? 999999;
        $bOrder = $appNameOrder[$b] ?? 999999;
        if ($aOrder === $bOrder) {
            return strcasecmp($a, $b);
        }
        return $aOrder <=> $bOrder;
    });

    uksort($expenseApps, static function ($a, $b) use ($appNameOrder) {
        $aOrder = $appNameOrder[$a] ?? 999999;
        $bOrder = $appNameOrder[$b] ?? 999999;
        if ($aOrder === $bOrder) {
            return strcasecmp($a, $b);
        }
        return $aOrder <=> $bOrder;
    });

    $stackedPeriods[] = [
        'label' => rl_graph_format_period_label($bucket, $groupBy),
        'income' => $incomeApps,
        'expense' => $expenseApps,
    ];
}

$donutSegments = [];
$segmentCount = 0;
foreach ($categoryBreakdown as $label => $value) {
    $donutSegments[] = ['label' => $label, 'value' => $value];
    $segmentCount++;
    if ($segmentCount >= 8) {
        break;
    }
}

$netProfit = $totalIncome - $totalExpense;
$incomeExpenseChartHtml = $displayMode === 'stacked_apps' ? rl_graph_svg_stacked_bars($stackedPeriods, $selectedAppNames) : rl_graph_svg_bars($barIncome, $barExpense, $lineLabels);
$rangeText = 'All recorded dates';
if ($dateMode === 'current_year') {
    $rangeText = 'Current year (' . date('M j, Y', strtotime($currentYearStart)) . ' to ' . date('M j, Y') . ')';
} elseif ($dateMode === 'custom' && $startDate !== '' && $endDate !== '') {
    $rangeText = date('M j, Y', strtotime($startDate)) . ' to ' . date('M j, Y', strtotime($endDate));
}
?>

<style>
.graph-layout { display:grid; gap:20px; }
.graph-filter-grid { display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:16px; }
.graph-filter-grid .graph-span-2 { grid-column: span 2; }
.graph-multi-select { min-height: 150px; }
.graph-summary-grid { display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap:16px; }
.graph-summary-card { border-radius:14px; border:1px solid #dde3ea; padding:16px 18px; background:#f9fbfd; }
.graph-summary-card h3 { margin:0 0 8px; font-size:14px; color:#637184; text-transform:uppercase; letter-spacing:.05em; }
.graph-summary-value { font-size:28px; font-weight:700; line-height:1.2; }
.graph-summary-note { margin-top:6px; font-size:13px; color:#718096; }
.graph-summary-card.income { background:#eff9f1; border-color:#cfe9d4; }
.graph-summary-card.expense { background:#fff2f2; border-color:#efcccc; }
.graph-summary-card.net { background:#f4f6fb; border-color:#d7deea; }
.graph-panels { display:grid; grid-template-columns: 1fr; gap:20px; }
.graph-panel-head { display:flex; justify-content:space-between; gap:12px; align-items:flex-start; margin-bottom:14px; }
.graph-panel-head h3 { margin:0 0 6px; }
.graph-panel-head .subtext { margin:0; }
.graph-pill { display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; background:#eef2f6; color:#425062; font-size:12px; font-weight:700; }
.graph-svg { width:100%; height:auto; display:block; }
.graph-grid-line { stroke:#e5e9ef; stroke-width:1; }
.graph-axis-line { stroke:#9aa7b7; stroke-width:1.25; }
.graph-axis-label { fill:#69788d; font-size:11px; }
.graph-bar-sub-label { fill:#516073; font-size:10px; font-weight:700; letter-spacing:.02em; }
.graph-line-path { stroke:#4b6cb7; stroke-width:3; stroke-linecap:round; stroke-linejoin:round; }
.graph-line-point { fill:#4b6cb7; stroke:#ffffff; stroke-width:2; }
.graph-bar-income { fill:#56a96b; }
.graph-bar-expense { fill:#d46b6b; }
.graph-bar-stack { stroke:#ffffff; stroke-width:1; }
.graph-stack-wrap { display:grid; gap:14px; }
.graph-app-legend { display:flex; flex-wrap:wrap; gap:10px; }
.graph-app-legend .graph-legend-row { grid-template-columns: 14px auto; }
.graph-empty { padding:18px; border:1px dashed #ccd5df; border-radius:12px; background:#fafbfd; color:#6f7d90; }
.graph-donut-wrap { display:grid; grid-template-columns: 280px 1fr; gap:18px; align-items:center; }
.graph-donut { width:100%; max-width:260px; margin:0 auto; display:block; }
.graph-donut-base { fill:none; stroke:#edf1f5; stroke-width:24; }
.graph-donut-segment { fill:none; stroke-width:24; transform:rotate(-90deg); transform-origin:100px 100px; }
.graph-donut-hole { fill:#fff; }
.graph-donut-total-label { fill:#718096; font-size:12px; font-weight:700; }
.graph-donut-total-value { fill:#2f3540; font-size:16px; font-weight:700; }
.graph-seg-1 { fill:#4b6cb7; stroke:#4b6cb7; background:#4b6cb7; }
.graph-seg-2 { fill:#5aa469; stroke:#5aa469; background:#5aa469; }
.graph-seg-3 { fill:#d08b3d; stroke:#d08b3d; background:#d08b3d; }
.graph-seg-4 { fill:#a86dc4; stroke:#a86dc4; background:#a86dc4; }
.graph-seg-5 { fill:#d46b6b; stroke:#d46b6b; background:#d46b6b; }
.graph-seg-6 { fill:#4aa3a2; stroke:#4aa3a2; background:#4aa3a2; }
.graph-seg-7 { fill:#d4b04a; stroke:#d4b04a; background:#d4b04a; }
.graph-seg-8 { fill:#7c8aa5; stroke:#7c8aa5; background:#7c8aa5; }
.graph-seg-9 { fill:#2f855a; stroke:#2f855a; background:#2f855a; }
.graph-seg-10 { fill:#3182ce; stroke:#3182ce; background:#3182ce; }
.graph-seg-11 { fill:#dd6b20; stroke:#dd6b20; background:#dd6b20; }
.graph-seg-12 { fill:#b83280; stroke:#b83280; background:#b83280; }
.graph-legend { display:grid; gap:10px; }
.graph-legend-row { display:grid; grid-template-columns: 14px minmax(0, 1fr) auto; gap:10px; align-items:center; padding:8px 10px; border:1px solid #e4e9f0; border-radius:10px; background:#fafbfd; }
.graph-legend-dot { width:12px; height:12px; border-radius:999px; display:inline-block; }
.graph-legend-label { font-weight:700; color:#334155; }
.graph-legend-value { color:#64748b; font-size:13px; }
.graph-help-text { font-size:13px; color:#6b7788; margin-top:6px; }
@media (max-width: 1100px) {
    .graph-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .graph-filter-grid .graph-span-2 { grid-column: span 2; }
    .graph-summary-grid { grid-template-columns: 1fr; }
    .graph-donut-wrap { grid-template-columns: 1fr; }
}
@media (max-width: 700px) {
    .graph-filter-grid { grid-template-columns: 1fr; }
    .graph-filter-grid .graph-span-2 { grid-column: span 1; }
}
</style>

<div class="page-head">
    <h2>Graphs</h2>
    <p class="subtext">Visualize profit trends, compare income vs expense periods, and see which categories are driving the numbers.</p>
</div>

<div class="graph-layout">
    <div class="card">
        <form method="get" action="index.php">
            <input type="hidden" name="page" value="graphs">
            <div class="graph-filter-grid">
                <div class="form-row">
                    <label for="date_mode">Date Mode</label>
                    <select name="date_mode" id="date_mode">
                        <option value="all_time" <?= $dateMode === 'all_time' ? 'selected' : '' ?>>All Time</option>
                        <option value="current_year" <?= $dateMode === 'current_year' ? 'selected' : '' ?>>Current Year</option>
                        <option value="custom" <?= $dateMode === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                    </select>
                </div>

                <div class="form-row">
                    <label for="group_by">Group By</label>
                    <select name="group_by" id="group_by">
                        <option value="day" <?= $groupBy === 'day' ? 'selected' : '' ?>>Day</option>
                        <option value="week" <?= $groupBy === 'week' ? 'selected' : '' ?>>Week</option>
                        <option value="month" <?= $groupBy === 'month' ? 'selected' : '' ?>>Month</option>
                    </select>
                </div>

                <div class="form-row">
                    <label for="display_mode">Income / Expense View</label>
                    <select name="display_mode" id="display_mode">
                        <option value="stacked_apps" <?= $displayMode === 'stacked_apps' ? 'selected' : '' ?>>Stacked by App</option>
                        <option value="combined" <?= $displayMode === 'combined' ? 'selected' : '' ?>>Combined Totals</option>
                    </select>
                </div>

                <div class="form-row">
                    <label for="start_date">Start Date</label>
                    <input type="date" name="start_date" id="start_date" value="<?= h($startDate) ?>" <?= $dateMode === 'custom' ? '' : 'disabled' ?>>
                </div>

                <div class="form-row">
                    <label for="end_date">End Date</label>
                    <input type="date" name="end_date" id="end_date" value="<?= h($endDate) ?>" <?= $dateMode === 'custom' ? '' : 'disabled' ?>>
                </div>

                <div class="form-row graph-span-2">
                    <label for="apps">Apps</label>
                    <select name="apps[]" id="apps" class="graph-multi-select" multiple>
                        <?php foreach ($appOptions as $option): ?>
                            <option value="<?= (int)$option['id'] ?>" <?= in_array((int)$option['id'], $selectedApps, true) ? 'selected' : '' ?>><?= h($option['app_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="graph-help-text">Leave blank to include all apps. Ctrl/Cmd-click to select more than one.</div>
                </div>

                <div class="form-row graph-span-2">
                    <label for="categories">Categories</label>
                    <select name="categories[]" id="categories" class="graph-multi-select" multiple>
                        <?php foreach ($categoryOptions as $option): ?>
                            <option value="<?= (int)$option['id'] ?>" <?= in_array((int)$option['id'], $selectedCategories, true) ? 'selected' : '' ?>><?= h(($option['app_name'] ? $option['app_name'] . ' — ' : '') . $option['category_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="graph-help-text">Filter to one or more categories or leave blank for everything.</div>
                </div>

                <div class="form-row graph-span-2">
                    <label for="assets">Assets</label>
                    <select name="assets[]" id="assets" class="graph-multi-select" multiple>
                        <?php foreach ($assetOptions as $option): ?>
                            <?php $assetLabel = (string)$option['asset_name']; if (!empty($option['asset_symbol'])) { $assetLabel .= ' (' . $option['asset_symbol'] . ')'; } ?>
                            <option value="<?= (int)$option['id'] ?>" <?= in_array((int)$option['id'], $selectedAssets, true) ? 'selected' : '' ?>><?= h($assetLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="graph-help-text">Use asset filters when you want to chart only BTC, only GMT, or any other specific asset set.</div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Apply Filters</button>
                <a href="index.php?page=graphs" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="graph-summary-grid">
        <div class="graph-summary-card income">
            <h3>Total Income</h3>
            <div class="graph-summary-value"><?= h(rl_graph_money($totalIncome)) ?></div>
            <div class="graph-summary-note">Filtered range: <?= h($rangeText) ?></div>
        </div>
        <div class="graph-summary-card expense">
            <h3>Total Expense</h3>
            <div class="graph-summary-value"><?= h(rl_graph_money($totalExpense)) ?></div>
            <div class="graph-summary-note">Outflow behaviors use absolute amounts for charting.</div>
        </div>
        <div class="graph-summary-card net">
            <h3>Net Profit</h3>
            <div class="graph-summary-value"><?= h(rl_graph_money($netProfit)) ?></div>
            <div class="graph-summary-note">Transfers, neutral items, and adjustments are excluded from profit math.</div>
        </div>
    </div>

    <div class="graph-panels">
        <div class="card">
            <div class="graph-panel-head">
                <div>
                    <h3>Net Profit Over Time</h3>
                    <p class="subtext">Shows income minus expense by <?= h($groupBy) ?> for the selected range.</p>
                </div>
                <span class="graph-pill"><?= h(ucfirst($groupBy)) ?> grouping</span>
            </div>
            <?= rl_graph_svg_line($lineValues, $lineLabels) ?>
        </div>

        <div class="card">
            <div class="graph-panel-head">
                <div>
                    <h3>Income vs Expense</h3>
                    <p class="subtext">Compare period-by-period inflow and outflow side by side. Use stacked mode to see how much of each bar came from each app.</p>
                </div>
                <span class="graph-pill"><?= h($displayMode === 'stacked_apps' ? 'Stacked by app' : (count($lineLabels) . ' period' . (count($lineLabels) === 1 ? '' : 's'))) ?></span>
            </div>
            <?= $incomeExpenseChartHtml ?>
        </div>

        <div class="card">
            <div class="graph-panel-head">
                <div>
                    <h3>Category Breakdown</h3>
                    <p class="subtext">Top categories by absolute total across the current filters.</p>
                </div>
                <span class="graph-pill">Top 8 categories</span>
            </div>
            <?= rl_graph_svg_donut($donutSegments) ?>
        </div>
    </div>
</div>

<script>
(function () {
    var dateMode = document.getElementById('date_mode');
    var startDate = document.getElementById('start_date');
    var endDate = document.getElementById('end_date');
    function syncDateFields() {
        var custom = dateMode && dateMode.value === 'custom';
        if (startDate) startDate.disabled = !custom;
        if (endDate) endDate.disabled = !custom;
    }
    if (dateMode) {
        dateMode.addEventListener('change', syncDateFields);
        syncDateFields();
    }
})();
</script>
