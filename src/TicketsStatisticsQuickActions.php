<?php

/**
 * -------------------------------------------------------------------------
 * TicketsStatistics plugin for GLPI
 * -------------------------------------------------------------------------
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Ticketsstatistics;

class TicketsStatisticsQuickActions
{
    public static function getTypeName($nb = 0): string
    {
        return __('Tickets Statistics Quick Actions', 'ticketsstatistics');
    }

    public static function getMenuContent(): array
    {
        return [
            'title'   => self::getTypeName(),
            'page'    => '/plugins/ticketsstatistics/front/quickactions.php',
            'icon'    => 'ti ti-code',
            'default' => '/plugins/ticketsstatistics/front/quickactions.php',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getDefaults(): array
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
    public static function parseLines(string $value): array
    {
        // Décode les séquences échappées littérales
        $value = stripcslashes($value);

        // Normalise tous les types de sauts de ligne
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        return array_values(
            array_filter(
                array_map('trim', explode("\n", $value))
            )
        );
    }

    /**
     * @return array<mixed>
     */
    public static function parseJsonField(string $value, string $label, array &$errors): array
    {
        if (trim($value) === '') {
            return [];
        }

        $value = stripcslashes($value);

        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $errors[] = sprintf(__('%s must be valid JSON.', 'ticketsstatistics'), $label) . ' (' . json_last_error_msg() . ')';
            return [];
        }

        return self::escapeLikeValues($decoded);
    }

    private static function escapeLikeValues(array $data): array
    {
        $exprClass = class_exists('\Glpi\DBAL\QueryExpression')
            ? \Glpi\DBAL\QueryExpression::class
            : \QueryExpression::class;

        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value) && isset($value['LIKE']) && is_string($value['LIKE'])) {
                $likeValue = $GLOBALS['DB']->escape($value['LIKE']);
                $result[]  = new $exprClass("`$key` LIKE '$likeValue'");
            } elseif (is_array($value)) {
                $result[$key] = self::escapeLikeValues($value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    public static function buildRequest(array $data, array &$errors): ?array
    {
        $query_type = $data['query_type'];
        $table = trim((string) $data['table']);

        if ($table === '') {
            $errors[] = __('Table is required.', 'ticketsstatistics');
        }

        $where = self::parseJsonField((string) $data['where_clause'], __('Where clause', 'ticketsstatistics'), $errors);
        $left_join = self::parseJsonField((string) $data['left_join'], __('Left join', 'ticketsstatistics'), $errors);

        if ($query_type === 'select') {
            $select_fields = self::parseLines((string) $data['select_fields']);
            if ($select_fields === []) {
                $errors[] = __('At least one SELECT field is required.', 'ticketsstatistics');
            }

            if ($errors !== []) {
                return null;
            }

            if ($select_fields === ['*']) {
                $select_fields = "$table.*";
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

            $groupby = self::parseLines((string) $data['groupby']);
            $order = self::parseLines((string) $data['order']);
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

        $values = self::parseJsonField((string) $data['update_values'], __('Update values', 'ticketsstatistics'), $errors);
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
}
