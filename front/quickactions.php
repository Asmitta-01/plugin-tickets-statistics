<?php

/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI
 * -------------------------------------------------------------------------
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * -------------------------------------------------------------------------
 */

require_once(__DIR__ . '/../../../inc/includes.php');

Session::checkRight('config', UPDATE);

/**
 * @return array<string, mixed>
 */
function ticketsstatistics_quickactions_defaults(): array
{
    return [
        'query_type' => 'select',
        'table' => '',
        'select_fields' => '',
        'update_values' => '',
        'where_clause' => '',
        'left_join' => '',
        'groupby' => '',
        'order' => '',
        'limit' => '50',
        'confirm_fingerprint' => '',
    ];
}

/**
 * @return array<int, string>
 */
function ticketsstatistics_parse_lines(string $value): array
{
    return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $value) ?: [])));
}

/**
 * @return array<mixed>
 */
function ticketsstatistics_parse_json_field(string $value, string $label, array &$errors): array
{
    if (trim($value) === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        $errors[] = sprintf(__('%s must be valid JSON.', 'ticketsstatistics'), $label);
        return [];
    }

    return $decoded;
}

/**
 * @param array<string, mixed> $data
 * @return array<string, mixed>|null
 */
function ticketsstatistics_build_request(array $data, array &$errors): ?array
{
    $query_type = $data['query_type'];
    $table = trim((string) $data['table']);

    if ($table === '') {
        $errors[] = __('Table is required.', 'ticketsstatistics');
    }

    $where = ticketsstatistics_parse_json_field((string) $data['where_clause'], __('Where clause', 'ticketsstatistics'), $errors);
    $left_join = ticketsstatistics_parse_json_field((string) $data['left_join'], __('Left join', 'ticketsstatistics'), $errors);

    if ($query_type === 'select') {
        $select_fields = ticketsstatistics_parse_lines((string) $data['select_fields']);
        if ($select_fields === []) {
            $errors[] = __('At least one SELECT field is required.', 'ticketsstatistics');
        }

        if ($errors !== []) {
            return null;
        }

        $request = [
            'SELECT' => $select_fields,
            'FROM'   => $table,
        ];

        if ($left_join !== []) {
            $request['LEFT JOIN'] = $left_join;
        }
        if ($where !== []) {
            $request['WHERE'] = $where;
        }

        $groupby = ticketsstatistics_parse_lines((string) $data['groupby']);
        $order = ticketsstatistics_parse_lines((string) $data['order']);
        $limit = (int) $data['limit'];

        if ($groupby !== []) {
            $request['GROUPBY'] = $groupby;
        }
        if ($order !== []) {
            $request['ORDER'] = $order;
        }
        if ($limit > 0) {
            $request['LIMIT'] = $limit;
        }

        return [
            'query_type' => 'select',
            'request' => $request,
            'table' => $table,
            'where' => $where,
            'left_join' => $left_join,
        ];
    }

    $values = ticketsstatistics_parse_json_field((string) $data['update_values'], __('Update values', 'ticketsstatistics'), $errors);
    if ($values === []) {
        $errors[] = __('At least one UPDATE value is required.', 'ticketsstatistics');
    }
    if ($where === []) {
        $errors[] = __('UPDATE requires a WHERE clause.', 'ticketsstatistics');
    }
    if ($left_join !== []) {
        $errors[] = __('UPDATE does not support LEFT JOIN in this tool.', 'ticketsstatistics');
    }
    if (trim((string) $data['groupby']) !== '' || trim((string) $data['order']) !== '') {
        $errors[] = __('GROUP BY and ORDER are only available for SELECT.', 'ticketsstatistics');
    }

    if ($errors !== []) {
        return null;
    }

    return [
        'query_type' => 'update',
        'table' => $table,
        'values' => $values,
        'where' => $where,
        'request' => [
            'UPDATE' => $table,
            'VALUES' => $values,
            'WHERE' => $where,
        ],
    ];
}

/**
 * @param array<string, mixed> $request
 */
function ticketsstatistics_request_fingerprint(array $request): string
{
    return hash('sha256', json_encode($request));
}

/**
 * @param array<int, array<string, mixed>> $rows
 */
function ticketsstatistics_render_result_table(array $rows): string
{
    if ($rows === []) {
        return '<div class="alert alert-info mb-0">' . __('No result returned.', 'ticketsstatistics') . '</div>';
    }

    $columns = array_keys($rows[0]);
    $html = '<div class="table-responsive">';
    $html .= '<table class="table table-sm table-hover align-middle mb-0">';
    $html .= '<thead><tr>';
    foreach ($columns as $column) {
        $html .= '<th>' . Html::clean($column) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($columns as $column) {
            $value = $row[$column] ?? '';
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $html .= '<td>' . Html::clean((string) $value) . '</td>';
        }
        $html .= '</tr>';
    }

    $html .= '</tbody></table></div>';
    return $html;
}

$data = ticketsstatistics_quickactions_defaults();
$data = array_merge($data, array_intersect_key($_POST, $data));
$action = $_POST['action'] ?? '';
$errors = [];
$preview_message = '';
$result_message = '';
$request_export = '';
$result_table = '';
$show_execute = false;

if ($action !== '') {
    $built = ticketsstatistics_build_request($data, $errors);

    if ($built !== null) {
        $request_export = var_export($built['request'], true);
        $fingerprint = ticketsstatistics_request_fingerprint($built['request']);
        $data['confirm_fingerprint'] = $fingerprint;

        if ($built['query_type'] === 'select') {
            $limit = $built['request']['LIMIT'] ?? __('all', 'ticketsstatistics');
            $preview_message = sprintf(
                __('This command will run a SELECT on %s and fetch up to %s row(s).', 'ticketsstatistics'),
                $built['table'],
                (string) $limit
            );

            if ($action === 'execute') {
                if (($data['confirm_fingerprint'] ?? '') !== ($_POST['confirm_fingerprint'] ?? '')) {
                    $errors[] = __('Please preview the request before executing it.', 'ticketsstatistics');
                } else {
                    $rows = [];
                    foreach ($DB->request($built['request']) as $row) {
                        $rows[] = $row;
                    }
                    $result_message = sprintf(__('SELECT completed with %d row(s).', 'ticketsstatistics'), count($rows));
                    $result_table = ticketsstatistics_render_result_table($rows);
                }
            } else {
                $show_execute = true;
            }
        } else {
            $count_request = [
                'COUNT' => 'cpt',
                'FROM'  => $built['table'],
                'WHERE' => $built['where'],
            ];
            $count_iterator = $DB->request($count_request);
            $target_count = (int) ($count_iterator->current()['cpt'] ?? 0);

            $preview_message = sprintf(
                __('This command will update %d row(s) in %s.', 'ticketsstatistics'),
                $target_count,
                $built['table']
            );

            if ($action === 'execute') {
                if (($data['confirm_fingerprint'] ?? '') !== ($_POST['confirm_fingerprint'] ?? '')) {
                    $errors[] = __('Please preview the request before executing it.', 'ticketsstatistics');
                } else {
                    $DB->update($built['table'], $built['values'], $built['where']);
                    $affected = (int) $DB->affectedRows();
                    $result_message = sprintf(__('UPDATE completed. %d row(s) affected.', 'ticketsstatistics'), $affected);
                }
            } else {
                $show_execute = true;
            }
        }
    }
}

Html::header(__('Tickets Statistics Quick Actions', 'ticketsstatistics'), $_SERVER['PHP_SELF'], 'admin', 'ticketsstatistics', 'quickactions');
?>
<div class="container-fluid mt-3">
    <div class="card shadow-sm">
        <div class="card-header">
            <h2 class="mb-0"><?= __('Tickets Statistics Quick Actions', 'ticketsstatistics') ?></h2>
        </div>
        <div class="card-body">
            <div class="alert alert-warning">
                <?= __('This tool only supports SELECT and UPDATE requests built from form inputs. Raw PHP, DELETE, INSERT, and other SQL statements are not allowed.', 'ticketsstatistics') ?>
            </div>

            <?php foreach ($errors as $error) : ?>
                <div class="alert alert-danger"><?= Html::clean($error) ?></div>
            <?php endforeach; ?>

            <?php if ($preview_message !== '') : ?>
                <div class="alert alert-info"><?= Html::clean($preview_message) ?></div>
            <?php endif; ?>

            <?php if ($result_message !== '') : ?>
                <div class="alert alert-success"><?= Html::clean($result_message) ?></div>
            <?php endif; ?>

            <form method="post" class="row g-3">
                <input type="hidden" name="confirm_fingerprint" value="<?= Html::clean($data['confirm_fingerprint']) ?>">

                <div class="col-md-3">
                    <label class="form-label" for="query_type"><?= __('Request type', 'ticketsstatistics') ?></label>
                    <select class="form-select" id="query_type" name="query_type">
                        <option value="select"<?= $data['query_type'] === 'select' ? ' selected' : '' ?>>SELECT</option>
                        <option value="update"<?= $data['query_type'] === 'update' ? ' selected' : '' ?>>UPDATE</option>
                    </select>
                </div>

                <div class="col-md-9">
                    <label class="form-label" for="table"><?= __('Table', 'ticketsstatistics') ?></label>
                    <input class="form-control" id="table" name="table" value="<?= Html::clean($data['table']) ?>" placeholder="glpi_tickets">
                </div>

                <div class="col-12">
                    <label class="form-label" for="select_fields"><?= __('SELECT fields', 'ticketsstatistics') ?></label>
                    <textarea class="form-control" id="select_fields" name="select_fields" rows="5" placeholder="glpi_tickets.id&#10;glpi_tickets.name&#10;glpi_tickets.status"><?= Html::clean($data['select_fields']) ?></textarea>
                    <div class="form-text"><?= __('One field per line.', 'ticketsstatistics') ?></div>
                </div>

                <div class="col-12">
                    <label class="form-label" for="update_values"><?= __('UPDATE values', 'ticketsstatistics') ?></label>
                    <textarea class="form-control" id="update_values" name="update_values" rows="4" placeholder='{"status": 2}'><?= Html::clean($data['update_values']) ?></textarea>
                    <div class="form-text"><?= __('JSON object used only for UPDATE requests.', 'ticketsstatistics') ?></div>
                </div>

                <div class="col-12">
                    <label class="form-label" for="where_clause"><?= __('WHERE clause', 'ticketsstatistics') ?></label>
                    <textarea class="form-control" id="where_clause" name="where_clause" rows="6" placeholder='{"glpi_tickets.is_deleted": 0, "glpi_tickets.status": 1}'><?= Html::clean($data['where_clause']) ?></textarea>
                    <div class="form-text"><?= __('Use JSON matching the GLPI DB abstraction array format.', 'ticketsstatistics') ?></div>
                </div>

                <div class="col-12">
                    <label class="form-label" for="left_join"><?= __('LEFT JOIN', 'ticketsstatistics') ?></label>
                    <textarea class="form-control" id="left_join" name="left_join" rows="6" placeholder='{"glpi_itilcategories": {"ON": {"glpi_itilcategories": "id", "glpi_tickets": "itilcategories_id"}}}'><?= Html::clean($data['left_join']) ?></textarea>
                    <div class="form-text"><?= __('Optional JSON join definition. SELECT only.', 'ticketsstatistics') ?></div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="groupby"><?= __('GROUP BY', 'ticketsstatistics') ?></label>
                    <textarea class="form-control" id="groupby" name="groupby" rows="4" placeholder="glpi_tickets.status"><?= Html::clean($data['groupby']) ?></textarea>
                    <div class="form-text"><?= __('One field per line. SELECT only.', 'ticketsstatistics') ?></div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="order"><?= __('ORDER', 'ticketsstatistics') ?></label>
                    <textarea class="form-control" id="order" name="order" rows="4" placeholder="glpi_tickets.date DESC"><?= Html::clean($data['order']) ?></textarea>
                    <div class="form-text"><?= __('One field per line. SELECT only.', 'ticketsstatistics') ?></div>
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="limit"><?= __('Limit', 'ticketsstatistics') ?></label>
                    <input class="form-control" id="limit" name="limit" type="number" min="1" value="<?= Html::clean($data['limit']) ?>">
                </div>

                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary" type="submit" name="action" value="preview"><?= __('Preview command', 'ticketsstatistics') ?></button>
                    <?php if ($show_execute && $errors === []) : ?>
                        <button class="btn btn-danger" type="submit" name="action" value="execute"><?= __('Confirm and execute', 'ticketsstatistics') ?></button>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($request_export !== '') : ?>
                <hr>
                <h3 class="h5"><?= __('Built request', 'ticketsstatistics') ?></h3>
                <pre class="bg-light border rounded p-3 mb-0"><?= Html::clean($request_export) ?></pre>
            <?php endif; ?>

            <?php if ($result_table !== '') : ?>
                <hr>
                <h3 class="h5"><?= __('Result', 'ticketsstatistics') ?></h3>
                <?= $result_table ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
Html::footer();
