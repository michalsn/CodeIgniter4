<?php namespace Tests\Support\Database\Migrations;

class Migration_Create_test_tables extends \CodeIgniter\Database\Migration
{
	public function up()
	{
		// User Table
		$this->forge->addField([
			'id'         => [
				'type'           => 'INTEGER',
				'constraint'     => 3,
				'auto_increment' => true,
			],
			'name'       => [
				'type'       => 'VARCHAR',
				'constraint' => 80,
			],
			'email'      => [
				'type'       => 'VARCHAR',
				'constraint' => 100,
			],
			'country'    => [
				'type'       => 'VARCHAR',
				'constraint' => 40,
			],
			'created_at' => [
				'type' => 'DATETIME',
				'null' => true,
			],
			'updated_at' => [
				'type' => 'DATETIME',
				'null' => true,
			],
			'deleted_at' => [
				'type' => 'DATETIME',
				'null' => true,
			],
		]);
		$this->forge->addKey('id', true);
		$this->forge->createTable('user', true);

		// Job Table
		$this->forge->addField([
			'id'          => [
				'type'           => 'INTEGER',
				'constraint'     => 3,
				'auto_increment' => true,
			],
			'name'        => [
				'type'       => 'VARCHAR',
				'constraint' => 40,
			],
			'description' => [
				'type' => 'TEXT',
				'null' => true,
			],
			'created_at'  => [
				'type'       => 'INTEGER',
				'constraint' => 11,
				'null'       => true,
			],
			'updated_at'  => [
				'type'       => 'INTEGER',
				'constraint' => 11,
				'null'       => true,
			],
			'deleted_at'  => [
				'type'       => 'INTEGER',
				'constraint' => 11,
				'null'       => true,
			],
		]);
		$this->forge->addKey('id', true);
		$this->forge->createTable('job', true);

		// Misc Table
		$this->forge->addField([
			'id'    => [
				'type'           => 'INTEGER',
				'constraint'     => 3,
				'auto_increment' => true,
			],
			'key'   => [
				'type'       => 'VARCHAR',
				'constraint' => 40,
			],
			'value' => ['type' => 'TEXT'],
		]);
		$this->forge->addKey('id', true);
		$this->forge->createTable('misc', true);

		//Database Type test table
		//missing types :
		//TINYINT,MEDIUMINT,BIT,YEAR,BINARY , VARBINARY, TINYTEXT,LONGTEXT,YEAR,JSON,Spatial data types
		$data_type_fields = [
			'id'              => [
				'type'           => 'INTEGER', //must be interger else SQLite3 error on not null for autoinc field
				'constraint'     => 20,
				'auto_increment' => true,
			],
			'type_varchar'    => [
				'type'       => 'VARCHAR',
				'constraint' => 40,
				'null'       => true,
			],
			'type_char'       => [
				'type'       => 'CHAR',
				'constraint' => 10,
				'null'       => true,
			],
			'type_text'       => [
				'type' => 'TEXT',
				'null' => true,
			],
			'type_smallint'   => [
				'type' => 'SMALLINT',
				'null' => true,
			],
			'type_integer'    => [
				'type' => 'INTEGER',
				'null' => true,
			],
			'type_float'      => [
				'type' => 'FLOAT',
				'null' => true,
			],
			'type_numeric'    => [
				'type'       => 'NUMERIC',
				'constraint' => '18,2',
				'null'       => true,
			],
			'type_date'       => [
				'type' => 'DATE',
				'null' => true,
			],
			'type_time'       => [
				'type' => 'TIME',
				'null' => true,
			],

			'type_datetime'   => [
				'type' => 'DATETIME',
				'null' => true,
			],
			'type_timestamp'  => [
				'type' => 'TIMESTAMP',
				'null' => true,
			],
			'type_bigint'     => [
				'type' => 'BIGINT',
				'null' => true,
			],
			'type_real'       => [
				'type' => 'REAL',
				'null' => true,
			],
			'type_enum'       => [
				'type'       => 'ENUM',
				'constraint' => [
					'appel',
					'pears',
					'bananas',
				],
				'null'       => true,
			],
			'type_set'        => [
				'type'       => 'SET',
				'constraint' => [
					'one',
					'two',
				],
				'null'       => true,
			],
			'type_mediumtext' => [
				'type' => 'MEDIUMTEXT',
				'null' => true,
			],
			'type_double'     => [
				'type' => 'DOUBLE',
				'null' => true,
			],

			'type_blob'       => [
				'type' => 'BLOB',
				'null' => true,
			],

		];

		if ($this->db->DBDriver === 'Postgre')
		{
			unset(
				$data_type_fields['type_real'],
				$data_type_fields['type_enum'],
				$data_type_fields['type_set'],
				$data_type_fields['type_mediumtext'],
				$data_type_fields['type_double'],
				$data_type_fields['type_blob'],
			);
		}

		if ($this->db->DBDriver === 'Sqlsrv')
		{
			unset(
				$data_type_fields['type_enum'],
				$data_type_fields['type_set'],
				$data_type_fields['type_mediumtext'],
				$data_type_fields['type_double'],
				$data_type_fields['type_blob'],
				$data_type_fields['type_timestamp'],
			);
		}

		$this->forge->addField($data_type_fields);
		$this->forge->addKey('id', true);
		$this->forge->createTable('type_test', true);

		// Empty Table
		$this->forge->addField([
			'id'         => [
				'type'           => 'INTEGER',
				'constraint'     => 3,
				'auto_increment' => true,
			],
			'name'       => [
				'type'       => 'VARCHAR',
				'constraint' => 40,
			],
			'created_at' => [
				'type' => 'DATE',
				'null' => true,
			],
			'updated_at' => [
				'type' => 'DATE',
				'null' => true,
			],
		]);
		$this->forge->addKey('id', true);
		$this->forge->createTable('empty', true);

		// Secondary Table
		$this->forge->addField([
			'id'    => [
				'type'           => 'INTEGER',
				'constraint'     => 3,
				'auto_increment' => true,
			],
			'key'   => [
				'type'       => 'VARCHAR',
				'constraint' => 40,
			],
			'value' => ['type' => 'TEXT'],
		]);
		$this->forge->addKey('id', true);
		$this->forge->createTable('secondary', true);

		// Stringify Primary key Table
		$this->forge->addField([
			'id'    => [
				'type'       => 'VARCHAR',
				'constraint' => 3,
			],
			'value' => ['type' => 'TEXT'],
		]);
		$this->forge->addKey('id', true);
		$this->forge->createTable('stringifypkey', true);

		// Table without auto increment field
		$this->forge->addField([
			'key'   => [
				'type'       => 'VARCHAR',
				'constraint' => 40,
				'unique'     => true,
			],
			'value' => ['type' => 'TEXT'],
		]);
		$this->forge->addKey('key', true);
		$this->forge->createTable('without_auto_increment', true);
	}

	//--------------------------------------------------------------------

	public function down()
	{
		$this->forge->dropTable('user', true);
		$this->forge->dropTable('job', true);
		$this->forge->dropTable('misc', true);
		$this->forge->dropTable('type_test', true);
		$this->forge->dropTable('empty', true);
		$this->forge->dropTable('secondary', true);
		$this->forge->dropTable('stringifypkey', true);
		$this->forge->dropTable('without_auto_increment', true);
	}

	//--------------------------------------------------------------------

}
