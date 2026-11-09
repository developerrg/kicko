<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUniqueProductCode extends Migration
{
    private const INDEX = 'uq_product_pr_Code';

    public function up(): void
    {
        $indexes = $this->db->getIndexData('product');
        if (isset($indexes[self::INDEX])) {
            return;
        }

        // Empty codes would collide with each other under a unique index; NULLs do not.
        $this->db->query("UPDATE product SET pr_Code = NULL WHERE TRIM(pr_Code) = ''");

        // Refuse to add the index if duplicates exist (case-insensitive, deleted products included).
        $dups = $this->db->query(
            'SELECT pr_Code, COUNT(*) AS n, GROUP_CONCAT(pr_Id) AS ids FROM product
             WHERE pr_Code IS NOT NULL GROUP BY pr_Code HAVING COUNT(*) > 1'
        )->getResultArray();

        if ($dups) {
            $list = array_map(static fn($d) => "'{$d['pr_Code']}' (product ids {$d['ids']})", $dups);
            throw new \RuntimeException(
                'Cannot add unique index on product.pr_Code - duplicate codes exist: ' . implode(', ', $list)
                . '. Rename or remove the duplicates (check deleted products too), then run the migration again.'
            );
        }

        $this->db->query('ALTER TABLE product ADD UNIQUE INDEX ' . self::INDEX . ' (pr_Code)');
    }

    public function down(): void
    {
        $indexes = $this->db->getIndexData('product');
        if (isset($indexes[self::INDEX])) {
            $this->db->query('ALTER TABLE product DROP INDEX ' . self::INDEX);
        }
    }
}
