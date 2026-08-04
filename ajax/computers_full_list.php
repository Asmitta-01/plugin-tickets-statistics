<?php

/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI
 * -------------------------------------------------------------------------
 */

require_once(__DIR__ . '/../../../inc/includes.php');

use GlpiPlugin\Ticketsstatistics\ComputersStatistics;

\Session::checkCentralAccess();

if (!\Session::haveRight('dashboard', READ)) {
    http_response_code(403);
    exit;
}

global $CFG_GLPI;

$resolved = ComputersStatistics::resolveComputersScope([
    'scope' => $_GET['scope'] ?? '',
    'counter_key' => $_GET['counter_key'] ?? '',
    'version' => $_GET['version'] ?? '',
    'town' => $_GET['town'] ?? '',
    'kb_code' => $_GET['kb_code'] ?? '',
    'town_id' => (int) ($_GET['town_id'] ?? 0),
]);

$rows = $resolved['rows'];
$criteria = [];

if ($rows === []) {
    $criteria[] = [
        'field' => 2,
        'searchtype' => 'equals',
        'value' => -1,
        'link' => 'AND',
    ];
} else {
    $first = true;
    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }

        $criteria[] = [
            'field' => 2,
            'searchtype' => 'equals',
            'value' => $id,
            'link' => $first ? 'AND' : 'OR',
        ];
        $first = false;
    }
}

$target = $CFG_GLPI['root_doc'] . '/front/computer.php';
?>
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars(__('Computers', 'ticketsstatistics'), ENT_QUOTES, 'UTF-8'); ?></title>
</head>

<body>
    <form id="ts-computers-full-list-form" method="post" action="<?php echo htmlspecialchars($target, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="reset" value="reset">
        <?php foreach ($criteria as $i => $criterion): ?>
            <input type="hidden" name="criteria[<?php echo (int) $i; ?>][field]" value="<?php echo (int) $criterion['field']; ?>">
            <input type="hidden" name="criteria[<?php echo (int) $i; ?>][searchtype]" value="<?php echo htmlspecialchars((string) $criterion['searchtype'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="criteria[<?php echo (int) $i; ?>][value]" value="<?php echo htmlspecialchars((string) $criterion['value'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="criteria[<?php echo (int) $i; ?>][link]" value="<?php echo htmlspecialchars((string) $criterion['link'], ENT_QUOTES, 'UTF-8'); ?>">
        <?php endforeach; ?>
    </form>
    <script>
        document.getElementById('ts-computers-full-list-form').submit();
    </script>
</body>

</html>