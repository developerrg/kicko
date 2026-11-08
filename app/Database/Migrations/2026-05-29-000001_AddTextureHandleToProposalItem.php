<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTextureHandleToProposalItem extends Migration
{
    private const TABLE = 'kico_proposal_item';

    public function up(): void
    {
        $columns = [
            'texture_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'default'    => null,
                'after'      => 'unit',
            ],
            'handle_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'default'    => null,
                'after'      => 'texture_code',
            ],
        ];

        // Add in order, skipping columns that already exist
        foreach ($columns as $name => $def) {
            if (! $this->db->fieldExists($name, self::TABLE)) {
                $this->forge->addColumn(self::TABLE, [$name => $def]);
            }
        }
    }

    public function down(): void
    {
        foreach (['texture_code', 'handle_type'] as $name) {
            if ($this->db->fieldExists($name, self::TABLE)) {
                $this->forge->dropColumn(self::TABLE, $name);
            }
        }
    }
}
