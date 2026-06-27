<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AlterShortCodeLength extends Migration
{
    public function up()
    {
        $fields = [
            'short_code' => [
                'name'       => 'short_code',
                'type'       => 'VARCHAR',
                'constraint' => '100',
            ],
        ];
        $this->forge->modifyColumn('urls', $fields);
    }

    public function down()
    {
        $fields = [
            'short_code' => [
                'name'       => 'short_code',
                'type'       => 'VARCHAR',
                'constraint' => '10',
            ],
        ];
        $this->forge->modifyColumn('urls', $fields);
    }
}
