<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAccessoriesToProposalItem extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('accessories', 'kico_proposal_item')) {
            return;
        }

        // JSON text: [{"name":"Door","count":2,"price":50}, ...] — only counts > 0
        $this->forge->addColumn('kico_proposal_item', [
            'accessories' => [
                'type'    => 'TEXT',
                'null'    => true,
                'default' => null,
                'after'   => 'texture_code',
            ],
        ]);
    }

    public function down(): void
    {
        if ($this->db->fieldExists('accessories', 'kico_proposal_item')) {
            $this->forge->dropColumn('kico_proposal_item', 'accessories');
        }
    }
}
