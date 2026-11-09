<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIsAccessoryToProposalItem extends Migration
{
    public function up(): void
    {
        if ($this->db->fieldExists('is_accessory', 'kico_proposal_item')) {
            return;
        }

        $this->forge->addColumn('kico_proposal_item', [
            'is_accessory' => [
                'type'    => 'TINYINT',
                'default' => 0,
                'after'   => 'is_category',
                'comment' => '1=accessory sub-row of the item above',
            ],
        ]);
    }

    public function down(): void
    {
        if ($this->db->fieldExists('is_accessory', 'kico_proposal_item')) {
            $this->forge->dropColumn('kico_proposal_item', 'is_accessory');
        }
    }
}
