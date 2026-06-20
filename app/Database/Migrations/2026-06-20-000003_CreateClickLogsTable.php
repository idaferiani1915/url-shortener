<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateClickLogsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'url_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => '45',
            ],
            'user_agent' => [
                'type' => 'TEXT',
            ],
            'referrer' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'clicked_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('url_id');
        $this->forge->createTable('click_logs');
    }

    public function down()
    {
        $this->forge->dropTable('click_logs');
    }
}
