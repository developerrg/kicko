<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Accessories get a unique auto-generated code (AC01, AC02, ...), like products have CB01...
 * The code replaces the old name-based "<key>Count" fields in the generatePDF payload.
 */
class AddAccessoryCode extends Migration
{
    private const INDEX = 'uq_accessory_acc_Code';

    public function up(): void
    {
        if (!$this->db->fieldExists('acc_Code', 'accessory')) {
            $this->forge->addColumn('accessory', [
                'acc_Code' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'default' => null, 'after' => 'acc_Id'],
            ]);
        }

        // Backfill existing rows (deleted ones too, so the unique index can never clash), in id order
        $max = (int)($this->db->query(
            "SELECT MAX(CAST(SUBSTRING(acc_Code, 3) AS UNSIGNED)) AS n FROM accessory WHERE acc_Code REGEXP '^AC[0-9]+$'"
        )->getRow()->n ?? 0);

        $rows = $this->db->table('accessory')->select('acc_Id')
            ->where('acc_Code IS NULL', null, false)->orderBy('acc_Id', 'ASC')->get()->getResultArray();
        foreach ($rows as $r) {
            $max++;
            $this->db->table('accessory')->where('acc_Id', $r['acc_Id'])
                ->update(['acc_Code' => 'AC' . str_pad((string)$max, 2, '0', STR_PAD_LEFT)]);
        }

        if (!isset($this->db->getIndexData('accessory')[self::INDEX])) {
            $this->db->query('ALTER TABLE accessory ADD UNIQUE INDEX ' . self::INDEX . ' (acc_Code)');
        }

        // The old name-based key is no longer used
        $this->db->query('ALTER TABLE accessory MODIFY acc_Key VARCHAR(100) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        if (isset($this->db->getIndexData('accessory')[self::INDEX])) {
            $this->db->query('ALTER TABLE accessory DROP INDEX ' . self::INDEX);
        }
        if ($this->db->fieldExists('acc_Code', 'accessory')) {
            $this->forge->dropColumn('accessory', 'acc_Code');
        }
    }
}
