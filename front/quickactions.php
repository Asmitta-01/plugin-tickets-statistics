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
        $html .= '<th>' . htmlspecialchars($column) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($columns as $column) {
            $value = $row[$column] ?? '';
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $html .= '<td>' . htmlspecialchars((string) $value) . '</td>';
        }
        $html .= '</tr>';
    }

    $html .= '</tbody></table></div>';
    return $html;
}

global $DB;
$data = \GlpiPlugin\Ticketsstatistics\TicketsStatisticsQuickActions::getDefaults();
$data = array_merge($data, array_intersect_key($_POST, $data));
$action = $_POST['action'] ?? '';
$errors = [];
$preview_message = '';
$result_message = '';
$request_export = '';
$result_table = '';
$show_execute = false;

if ($action !== '') {
    $built = \GlpiPlugin\Ticketsstatistics\TicketsStatisticsQuickActions::buildRequest($data, $errors);

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
            try {
                $count_iterator = $DB->request($count_request);
                $target_count = (int) ($count_iterator->current()['cpt'] ?? 0);

                $preview_message = sprintf(
                    __('This command will update %d row(s) in %s.', 'ticketsstatistics'),
                    $target_count,
                    $built['table']
                );
            } catch (Exception $e) {
                $errors[] = __('An error occurred while executing the UPDATE request: ', 'ticketsstatistics') . $e->getMessage();
            }

            if ($action === 'execute') {
                if (($data['confirm_fingerprint'] ?? '') !== ($_POST['confirm_fingerprint'] ?? '')) {
                    $errors[] = __('Please preview the request before executing it.', 'ticketsstatistics');
                } else {
                    try {
                        $DB->update($built['table'], $built['values'], $built['where']);
                    } catch (Exception $e) {
                        $errors[] = __('An error occurred while executing the UPDATE request: ', 'ticketsstatistics') . $e->getMessage();
                    }
                    $affected = (int) $DB->affectedRows();
                    $result_message = sprintf(__('UPDATE completed. %d row(s) affected.', 'ticketsstatistics'), $affected);
                }
            } else {
                $show_execute = true;
            }
        }
    }
}

Html::header(__('Tickets Statistics Quick Actions', 'ticketsstatistics'), $_SERVER['PHP_SELF'], 'config', \GlpiPlugin\Ticketsstatistics\TicketsStatisticsQuickActions::class);
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
                <div class="alert align-items-center alert-danger text-white bg-danger ">
                    <i class="ti ti-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endforeach; ?>

            <?php if ($preview_message !== '') : ?>
                <div class="alert alert-info"><?= htmlspecialchars($preview_message) ?></div>
            <?php endif; ?>

            <?php if ($result_message !== '') : ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($result_message) ?>
                    <?php if ($result_table !== '') : ?>
                        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#resultTable">
                            <i class="ti ti-table"></i>
                            <?= __('Show result table', 'ticketsstatistics') ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="row g-3">
                <input type="hidden" name="confirm_fingerprint" value="<?= htmlspecialchars($data['confirm_fingerprint']) ?>">

                <div class="col-md-3">
                    <label class="form-label" for="query_type"><?= __('Request type', 'ticketsstatistics') ?></label>
                    <select class="form-select" id="query_type" name="query_type">
                        <option value="select" <?= $data['query_type'] === 'select' ? ' selected' : '' ?>>SELECT</option>
                        <option value="update" <?= $data['query_type'] === 'update' ? ' selected' : '' ?>>UPDATE</option>
                    </select>
                </div>

                <div class="col-md-9">
                    <label class="form-label" for="table"><?= __('Table', 'ticketsstatistics') ?></label>
                    <input class="form-control" id="table" name="table" value="<?= htmlspecialchars($data['table']) ?>" placeholder="glpi_tickets">
                </div>

                <div class="col-12">
                    <label class="form-label" for="select_fields"><?= __('SELECT fields', 'ticketsstatistics') ?></label>
                    <textarea class="form-control" id="select_fields" name="select_fields" rows="5" placeholder="glpi_tickets.id&#10;glpi_tickets.name&#10;glpi_tickets.status"><?= htmlspecialchars($data['select_fields']) ?></textarea>
                    <div class="form-text"><?= __('One field per line.', 'ticketsstatistics') ?></div>
                </div>

                <div class="col-12">
                    <label class="form-label" for="update_values"><?= __('UPDATE values', 'ticketsstatistics') ?></label>
                    <textarea class="form-control" id="update_values" name="update_values" rows="4" placeholder='{"status": 2}'><?= htmlspecialchars($data['update_values']) ?></textarea>
                    <div class="form-text"><?= __('JSON object used only for UPDATE requests.', 'ticketsstatistics') ?></div>
                </div>

                <div class="col-12">
                    <label class="form-label" for="where_clause"><?= __('WHERE clause', 'ticketsstatistics') ?></label>
                    <textarea class="form-control" id="where_clause" name="where_clause" rows="6" placeholder='{"glpi_tickets.is_deleted": 0, "glpi_tickets.status": 1}'><?= htmlspecialchars($data['where_clause']) ?></textarea>
                    <div class="form-text"><?= __('Use JSON matching the GLPI DB abstraction array format.', 'ticketsstatistics') ?></div>
                </div>

                <div class="col-12">
                    <label class="form-label" for="left_join"><?= __('LEFT JOIN', 'ticketsstatistics') ?></label>
                    <textarea class="form-control" id="left_join" name="left_join" rows="6" placeholder='{"glpi_itilcategories": {"ON": {"glpi_itilcategories": "id", "glpi_tickets": "itilcategories_id"}}}'><?= htmlspecialchars($data['left_join']) ?></textarea>
                    <div class="form-text"><?= __('Optional JSON join definition. SELECT only.', 'ticketsstatistics') ?></div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="groupby"><?= __('GROUP BY', 'ticketsstatistics') ?></label>
                    <textarea class="form-control" id="groupby" name="groupby" rows="4" placeholder="glpi_tickets.status"><?= htmlspecialchars($data['groupby']) ?></textarea>
                    <div class="form-text"><?= __('One field per line. SELECT only.', 'ticketsstatistics') ?></div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="order"><?= __('ORDER', 'ticketsstatistics') ?></label>
                    <textarea class="form-control" id="order" name="order" rows="4" placeholder="glpi_tickets.date DESC"><?= htmlspecialchars($data['order']) ?></textarea>
                    <div class="form-text"><?= __('One field per line. SELECT only.', 'ticketsstatistics') ?></div>
                </div>

                <div class="col-md-3">
                    <label class="form-label" for="limit"><?= __('Limit', 'ticketsstatistics') ?></label>
                    <input class="form-control" id="limit" name="limit" type="number" min="1" value="<?= htmlspecialchars($data['limit']) ?>">
                </div>

                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary" type="submit" name="action" value="preview"><?= __('Preview command', 'ticketsstatistics') ?></button>
                    <?php if ($show_execute && $errors === []) : ?>
                        <button class="btn btn-danger" type="submit" name="action" value="execute"><?= __('Confirm and execute', 'ticketsstatistics') ?></button>
                    <?php endif; ?>
                </div>

                <?= Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]) ?>
            </form>

            <?php if ($request_export !== '') : ?>
                <hr>
                <h3 class="h5"><?= __('Built request', 'ticketsstatistics') ?></h3>
                <pre class="text-dark bg-light border rounded p-3 mb-0"><?= htmlspecialchars($request_export) ?></pre>
            <?php endif; ?>

            <?php if ($result_table !== '') : ?>
                <div class="modal fade" id="resultTable" tabindex="-1" role="dialog" aria-labelledby="modalTitleId" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered modal-lg" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="modalTitleId">
                                    <?= __('Result', 'ticketsstatistics') ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body"><?= $result_table ?></div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <?= __('Close', 'ticketsstatistics') ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        <?php foreach ($errors as $error) : ?>
            glpi_toast_error('<?= htmlspecialchars($error) ?>');
        <?php endforeach; ?>

        <?php if ($result_message !== '') : ?>
            glpi_toast_info('<?= htmlspecialchars($result_message) ?>');
        <?php endif; ?>
    });
</script>

<?php
Html::footer();
