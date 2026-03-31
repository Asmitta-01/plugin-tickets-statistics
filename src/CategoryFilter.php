<?php

namespace GlpiPlugin\Ticketsstatistics;

class CategoryFilter
{

    /**
     * Applies a category filter to the provided where clause array, including the selected category,
     * its direct children, and their children (up to two levels deep).
     *
     * @param array  $where      Reference to the array of where conditions to be modified.
     * @param string $table      The table name to use in the category condition.
     * @param int    $categoryId The category ID to filter by.
     *
     * @return void
     */
    public static function apply(array &$where, string $table, int $categoryId): void
    {
        global $DB;
        if ($categoryId > 0) {
            // Récupère tous les enfants de cette catégorie
            $childIds = [$categoryId];
            $children = $DB->request([
                'SELECT' => ['id'],
                'FROM'   => 'glpi_itilcategories',
                'WHERE'  => ['itilcategories_id' => $categoryId],
            ]);
            foreach ($children as $child) {
                $childIds[] = (int) $child['id'];
            }

            // Récupère aussi les petits-enfants (un niveau de plus)
            $grandchildren = $DB->request([
                'SELECT' => ['id'],
                'FROM'   => 'glpi_itilcategories',
                'WHERE'  => ['itilcategories_id' => $childIds],
            ]);
            foreach ($grandchildren as $child) {
                $childIds[] = (int) $child['id'];
            }

            $where["$table.itilcategories_id"] = $childIds;
        }
    }
}
