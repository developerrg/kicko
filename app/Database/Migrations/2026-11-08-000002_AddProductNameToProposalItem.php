<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProductNameToProposalItem extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('product_name', 'kico_proposal_item')) {
            return;
        }

        $this->forge->addColumn('kico_proposal_item', [
            'product_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'default'    => null,
                'after'      => 'sl_no',
            ],
        ]);
    }

    public function down(): void
    {
        if ($this->db->fieldExists('product_name', 'kico_proposal_item')) {
            $this->forge->dropColumn('kico_proposal_item', 'product_name');
        }
    }
}
