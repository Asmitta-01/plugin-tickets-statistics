<?php

require_once(__DIR__ . '/../../../inc/includes.php');

\Session::checkCentralAccess();

if (!\Session::haveRight('dashboard', READ)) {
    \Html::displayRightError();
}

$townId = (int) ($_GET['town_id'] ?? 0);
$manufacturerId = (int) ($_GET['manufacturer_id'] ?? 0);
$counterKey = $_GET['counter_key'] ?? '';
$label = $_GET['label'] ?? '';

// We reuse getPrintersList but filter it if necessary
$printers = \GlpiPlugin\Ticketsstatistics\PrintersStatistics::getPrintersList($townId, $manufacturerId);

if ($counterKey === 'model' && $label !== '') {
    $printers = array_filter($printers, fn($p) => $p['model'] === $label || ($label === __('Unknown', 'ticketsstatistics') && $p['model'] === ''));
} elseif ($counterKey === 'town' && $label !== '') {
    $printers = array_filter($printers, fn($p) => $p['town'] === $label || ($label === __('Unknown', 'ticketsstatistics') && $p['town'] === ''));
} elseif ($counterKey === 'top_pages' && $label !== '') {
    $printers = array_filter($printers, fn($p) => $p['name'] === $label);
}

// Ensure array keys are reset
$printers = array_values($printers);

$html = '<div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
    <thead class="table-light">
        <tr>
            <th>' . __('ID', 'ticketsstatistics') . '</th>
            <th>' . __('Name', 'ticketsstatistics') . '</th>
            <th>' . __('Hardware / Model', 'ticketsstatistics') . '</th>
            <th>' . __('Serial number', 'ticketsstatistics') . '</th>
            <th>' . __('Total Pages', 'ticketsstatistics') . '</th>
            <th>' . __('Town', 'ticketsstatistics') . '</th>
            <th>' . __('Location', 'ticketsstatistics') . '</th>
        </tr>
    </thead>
    <tbody>';

if (count($printers) === 0) {
    $html .= '<tr><td colspan="7" class="text-center py-4 text-muted">' . __('No printers found for this selection.', 'ticketsstatistics') . '</td></tr>';
} else {
    foreach ($printers as $p) {
        $html .= '<tr>
            <td>' . $p['id'] . '</td>
            <td><a href="' . htmlspecialchars($p['url'], ENT_QUOTES, 'UTF-8') . '" target="_blank" class="fw-semibold">' . htmlspecialchars($p['name'], ENT_QUOTES, 'UTF-8') . '</a></td>
            <td>' . htmlspecialchars($p['manufacturer'], ENT_QUOTES, 'UTF-8') . ($p['model'] !== '' ? ' ' . htmlspecialchars($p['model'], ENT_QUOTES, 'UTF-8') : '') . '</td>
            <td>' . htmlspecialchars($p['serial'], ENT_QUOTES, 'UTF-8') . '</td>
            <td class="fw-bold text-blue">' . number_format($p['pages'], 0, '.', ' ') . '</td>
            <td>' . htmlspecialchars($p['town'], ENT_QUOTES, 'UTF-8') . '</td>
            <td>' . htmlspecialchars($p['location'], ENT_QUOTES, 'UTF-8') . '</td>
        </tr>';
    }
}

$html .= '</tbody></table></div>';

// Full list criteria could be returned here as JSON if needed. We'll just return HTML for simplicity.
header('Content-Type: application/json');
echo json_encode([
    'html' => $html,
    'count' => count($printers)
]);
