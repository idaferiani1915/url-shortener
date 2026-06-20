<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddResetTokenToUsers extends Migration
{
    public function up()
    {
        $fields = [
            'reset_token' => [
                'type'       => 'VARCHAR',
                'constraint' => '64',
                'null'       => true,
            ],
            'reset_token_expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ]
        ];
        
        $this->forge->addColumn('users', $fields);
        
        // Add index on reset_token
        $this->db->query("ALTER TABLE users ADD INDEX (reset_token)");
    }

    public function down()
    {
        $this->db->query("ALTER TABLE users DROP INDEX reset_token");
        $this->forge->dropColumn('users', ['reset_token', 'reset_token_expires_at']);
    }
}
