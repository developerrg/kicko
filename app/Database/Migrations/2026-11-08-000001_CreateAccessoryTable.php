<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAccessoryTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'acc_Id'        => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'acc_Name'      => ['type' => 'VARCHAR', 'constraint' => 150],
            // camelCase key generated from the name on create; API item field is "<acc_Key>Count"
            'acc_Key'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'acc_Price'     => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0.00],
            'acc_Status'    => ['type' => 'TINYINT', 'default' => 1, 'comment' => '1=active, 2=inactive, 3=deleted'],
            'acc_createdon' => ['type' => 'DATETIME', 'null' => true],
            'acc_createdby' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'acc_modifyon'  => ['type' => 'DATETIME', 'null' => true],
            'acc_modifyby'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ]);

        $this->forge->addKey('acc_Id', true);
        $this->forge->addKey('acc_Key');
        $this->forge->createTable('accessory', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('accessory', true);
    }
}
