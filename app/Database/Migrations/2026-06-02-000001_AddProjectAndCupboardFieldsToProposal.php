<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProjectAndCupboardFieldsToProposal extends Migration
{
    public function up(): void
    {
        $this->addMissing('kico_proposal', [
            'project_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'default' => null, 'after' => 'name'],
            'pin'          => ['type' => 'VARCHAR', 'constraint' => 20,  'null' => true, 'default' => null, 'after' => 'project_name'],
        ]);

        $this->addMissing('kico_proposal_item', [
            'cupboard_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'default' => null, 'after' => 'handle_type'],
            'size'          => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'default' => null, 'after' => 'cupboard_type'],
            'design_code'   => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'default' => null, 'after' => 'size'],
            'door_state'    => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'default' => null, 'after' => 'design_code'],
        ]);
    }

    public function down(): void
    {
        $this->dropExisting('kico_proposal', ['project_name', 'pin']);
        $this->dropExisting('kico_proposal_item', ['cupboard_type', 'size', 'design_code', 'door_state']);
    }

    /** Add each column only if it is not already in the table. */
    private function addMissing(string $table, array $columns): void
    {
        foreach ($columns as $name => $def) {
            if (! $this->db->fieldExists($name, $table)) {
                $this->forge->addColumn($table, [$name => $def]);
            }
        }
    }

    /** Drop each column only if it exists in the table. */
    private function dropExisting(string $table, array $names): void
    {
        foreach ($names as $name) {
            if ($this->db->fieldExists($name, $table)) {
                $this->forge->dropColumn($table, $name);
            }
        }
    }
}
