<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUrlsTable extends Migration
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
            'short_code' => [
                'type'       => 'VARCHAR',
                'constraint' => '10',
            ],
            'original_url' => [
                'type' => 'TEXT',
            ],
            'custom_alias' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'null'       => true,
            ],
            'user_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => true,
            ],
            'idempotency_key' => [
                'type'       => 'VARCHAR',
                'constraint' => '64',
                'null'       => true,
            ],
            'clicks_count' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('short_code');
        $this->forge->addKey('idempotency_key');
        $this->forge->addKey('user_id');
        $this->forge->createTable('urls');
    }

    public function down()
    {
        $this->forge->dropTable('urls');
    }
}
