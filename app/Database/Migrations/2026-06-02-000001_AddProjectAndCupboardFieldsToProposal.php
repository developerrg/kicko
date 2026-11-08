<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProjectAndCupboardFieldsToProposal extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('kico_proposal', [
            'project_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null, 'after' => 'name'],
            'pin'          => ['type' => 'VARCHAR', 'constraint' => 20,  'null' => true, 'default' => null, 'after' => 'project_name'],
        ]);

        $this->forge->addColumn('kico_proposal_item', [
            'cupboard_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'default' => null, 'after' => 'handle_type'],
            'size'          => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'default' => null, 'after' => 'cupboard_type'],
            'design_code'   => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'default' => null, 'after' => 'size'],
            'door_state'    => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'default' => null, 'after' => 'design_code'],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('kico_proposal', ['project_name', 'pin']);
        $this->forge->dropColumn('kico_proposal_item', ['cupboard_type', 'size', 'design_code', 'door_state']);
    }
}
