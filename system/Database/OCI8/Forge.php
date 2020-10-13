<?php

/**
 * CodeIgniter
 *
 * An open source application development framework for PHP
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2014-2019 British Columbia Institute of Technology
 * Copyright (c) 2019-2020 CodeIgniter Foundation
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package    CodeIgniter
 * @author     CodeIgniter Dev Team
 * @copyright  2019-2020 CodeIgniter Foundation
 * @license    https://opensource.org/licenses/MIT	MIT License
 * @link       https://codeigniter.com
 * @since      Version 4.0.0
 * @filesource
 */

namespace CodeIgniter\Database\OCI8;

/**
 * Forge for OCI8
 */
class Forge extends \CodeIgniter\Database\Forge
{

	/**
	 * CREATE DATABASE statement
	 *
	 * @var string|false
	 */
	protected $createDatabaseStr = false;

	/**
	 * CREATE TABLE IF statement
	 *
	 * @var string|false
	 */
	protected $createTableIfStr = 'declare
begin
  execute immediate \'
    %s\';
  exception when others then
    if SQLCODE = -955 then null; else raise; end if;
end';

	/**
	 * DROP TABLE IF EXISTS statement
	 *
	 * @var string|false
	 */
	protected $dropTableIfStr = false;

	/**
	 * DROP DATABASE statement
	 *
	 * @var string|false
	 */
	protected $dropDatabaseStr = false;

	/**
	 * UNSIGNED support
	 *
	 * @var boolean|array
	 */
	protected $unsigned = false;

	/**
	 * NULL value representation in CREATE/ALTER TABLE statements
	 *
	 * @var string
	 */
	protected $null = 'NULL';

	/**
	 * RENAME TABLE statement
	 *
	 * @var string
	 */
	protected $renameTableStr = 'ALTER TABLE %s RENAME TO %s';

	/**
	 * DROP CONSTRAINT statement
	 *
	 * @var string
	 */
	protected $dropConstraintStr = 'ALTER TABLE %s DROP CONSTRAINT %s';

	//--------------------------------------------------------------------
	/**
	 * ALTER TABLE
	 *
	 * @param string $alterType ALTER type
	 * @param string $table     Table name
	 * @param mixed  $field     Column definition
	 *
	 * @return string|string[]
	 */
	protected function _alterTable(string $alterType, string $table, $field)
	{
		if ($alterType === 'DROP')
		{
			return parent::_alterTable($alterType, $table, $field);
		}
		if ($alterType === 'CHANGE')
		{
			$alterType = 'MODIFY';
		}

		$sql         = 'ALTER TABLE ' . $this->db->escapeIdentifiers($table);
		$nullableMap = array_column($this->db->getFieldData($table), 'nullable', 'name');
		$sqls        = [];
		for ($i = 0, $c = count($field); $i < $c; $i++)
		{
			if ($alterType === 'MODIFY')
			{
				// If a null constraint is added to a column with a null constraint,
				// ORA-01451 will occur,
				// so add null constraint is used only when it is different from the current null constraint.
				$isWantToAddNull    = (strpos($field[$i]['null'], ' NOT') === false);
				$currentNullAddable = $nullableMap[$field[$i]['name']];

				if ($isWantToAddNull === $currentNullAddable)
				{
					$field[$i]['null'] = '';
				}
			}

			if ($field[$i]['_literal'] !== false)
			{
				$field[$i] = "\n\t" . $field[$i]['_literal'];
			}
			else
			{
				$field[$i]['_literal'] = "\n\t" . $this->_processColumn($field[$i]);

				if (! empty($field[$i]['comment']))
				{
					$sqls[] = 'COMMENT ON COLUMN '
						. $this->db->escapeIdentifiers($table) . '.' . $this->db->escapeIdentifiers($field[$i]['name'])
						. ' IS ' . $field[$i]['comment'];
				}

				if ($alterType === 'MODIFY' && ! empty($field[$i]['new_name']))
				{
					$sqls[] = $sql . ' RENAME COLUMN ' . $this->db->escapeIdentifiers($field[$i]['name'])
						. ' TO ' . $this->db->escapeIdentifiers($field[$i]['new_name']);
				}

				$field[$i] = "\n\t" . $field[$i]['_literal'];
			}
		}

		$sql .= ' ' . $alterType . ' ';
		$sql .= (count($field) === 1)
				? $field[0]
				: '(' . implode(',', $field) . ')';

		// RENAME COLUMN must be executed after MODIFY
		array_unshift($sqls, $sql);
		return $sqls;
	}

	//--------------------------------------------------------------------

	/**
	 * Field attribute AUTO_INCREMENT
	 *
	 * @param array $attributes
	 * @param array $field
	 *
	 * @return void
	 */
	protected function _attributeAutoIncrement(array &$attributes, array &$field)
	{
		if (! empty($attributes['AUTO_INCREMENT']) && $attributes['AUTO_INCREMENT'] === true
			&& stripos($field['type'], 'NUMBER') !== false
			&& version_compare($this->db->getVersion(), '12.1', '>=')
		)
		{
			$field['auto_increment'] = ' GENERATED BY DEFAULT AS IDENTITY';
		}
	}

	//--------------------------------------------------------------------

	/**
	 * Process column
	 *
	 * @param array $field
	 *
	 * @return string
	 */
	protected function _processColumn(array $field): string
	{
		return $this->db->escapeIdentifiers($field['name'])
			   . ' ' . $field['type'] . $field['length']
			   . $field['unsigned']
			   . $field['default']
			   . $field['auto_increment']
			   . $field['null']
			   . $field['unique'];
	}

	//--------------------------------------------------------------------

	/**
	 * Field attribute TYPE
	 *
	 * Performs a data type mapping between different databases.
	 *
	 * @param array $attributes
	 *
	 * @return void
	 */
	protected function _attributeType(array &$attributes)
	{
		// Reset field lengths for data types that don't support it
		// Usually overridden by drivers
		switch (strtoupper($attributes['TYPE']))
		{
			case 'TINYINT':
				$attributes['CONSTRAINT'] = $attributes['CONSTRAINT'] ?? 3;
			case 'SMALLINT':
				$attributes['CONSTRAINT'] = $attributes['CONSTRAINT'] ?? 5;
			case 'MEDIUMINT':
				$attributes['CONSTRAINT'] = $attributes['CONSTRAINT'] ?? 7;
			case 'INT':
			case 'INTEGER':
				$attributes['CONSTRAINT'] = $attributes['CONSTRAINT'] ?? 11;
			case 'BIGINT':
				$attributes['CONSTRAINT'] = $attributes['CONSTRAINT'] ?? 19;
			case 'NUMERIC':
				$attributes['TYPE'] = 'NUMBER';
				return;
			case 'DATETIME':
				$attributes['TYPE'] = 'DATE';
				return;
			case 'TEXT':
			case 'VARCHAR':
				$attributes['TYPE']       = 'VARCHAR2';
				$attributes['CONSTRAINT'] = $attributes['CONSTRAINT'] ?? 255;
				return;
			default: return;
		}
	}

	//--------------------------------------------------------------------

	/**
	 * Create Table
	 *
	 * @param string  $table       Table name
	 * @param boolean $ifNotExists Whether to add 'IF NOT EXISTS' condition
	 * @param array   $attributes  Associative array of table attributes
	 *
	 * @return mixed
	 */
	protected function _createTable(string $table, bool $ifNotExists, array $attributes)
	{
		// For any platforms that don't support Create If Not Exists...
		if ($ifNotExists === true && $this->createTableIfStr === false)
		{
			if ($this->db->tableExists($table))
			{
				return true;
			}

			$ifNotExists = false;
		}

		//$sql = ($ifNotExists) ? sprintf($this->createTableIfStr, $this->db->escapeIdentifiers($table))
		//	: 'CREATE TABLE';
		$sql = 'CREATE TABLE';

		$columns = $this->_processFields(true);
		for ($i = 0, $c = count($columns); $i < $c; $i++)
		{
			$columns[$i] = ($columns[$i]['_literal'] !== false) ? "\n\t" . $columns[$i]['_literal']
				: "\n\t" . $this->_processColumn($columns[$i]);
		}

		$columns = implode(',', $columns);

		$columns .= $this->_processPrimaryKeys($table);
		$columns .= $this->_processForeignKeys($table);

		// Are indexes created from within the CREATE TABLE statement? (e.g. in MySQL)
		if ($this->createTableKeys === true)
		{
			$indexes = $this->_processIndexes($table);
			if (is_string($indexes))
			{
				$columns .= $indexes;
			}
		}

		// createTableStr will usually have the following format: "%s %s (%s\n)"
		$sql = sprintf($this->createTableStr . '%s', $sql, $this->db->escapeIdentifiers($table), $columns,
			$this->_createTableAttributes($attributes));

		if ($this->createTableIfStr !== false)
		{
			$sql = sprintf($this->createTableIfStr, $sql);
		}

		return $sql;
	}

	/**
	 * Drop Table
	 *
	 * Generates a platform-specific DROP TABLE string
	 *
	 * @param string  $table    Table name
	 * @param boolean $ifExists Whether to add an IF EXISTS condition
	 * @param boolean $cascade
	 *
	 * @return string
	 */
	protected function _dropTable(string $table, bool $ifExists, bool $cascade): string
	{
		$sql = parent::_dropTable($table, $ifExists, $cascade);

		if ($sql !== '' && $cascade === true)
		{
			$sql .= ' CASCADE CONSTRAINTS PURGE';
		}
		elseif ($sql !== '')
		{
			$sql .= ' PURGE';
		}

		return $sql;
	}

	//--------------------------------------------------------------------

	/**
	 * Process foreign keys
	 *
	 * @param string $table Table name
	 *
	 * @return string
	 */
	protected function _processForeignKeys(string $table): string
	{
		$sql = '';

		$allowActions = [
			'CASCADE',
			'SET NULL',
			'NO ACTION',
		];

		if (count($this->foreignKeys) > 0)
		{
			foreach ($this->foreignKeys as $field => $fkey)
			{
				$nameIndex = $table . '_' . $field . '_fk';

				$sql .= ",\n\tCONSTRAINT " . $this->db->escapeIdentifiers($nameIndex)
					. ' FOREIGN KEY(' . $this->db->escapeIdentifiers($field) . ') REFERENCES ' . $this->db->escapeIdentifiers($this->db->DBPrefix . $fkey['table']) . ' (' . $this->db->escapeIdentifiers($fkey['field']) . ')';

				if ($fkey['onDelete'] !== false && in_array($fkey['onDelete'], $allowActions))
				{
					$sql .= ' ON DELETE ' . $fkey['onDelete'];
				}
			}
		}

		return $sql;
	}
}
