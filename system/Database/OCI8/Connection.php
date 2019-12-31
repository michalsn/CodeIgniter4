<?php
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2014-2019 British Columbia Institute of Technology
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
 * @copyright  2014-2019 British Columbia Institute of Technology (https://bcit.ca/)
 * @license    https://opensource.org/licenses/MIT	MIT License
 * @link       https://codeigniter.com
 * @since      Version 4.0.0
 * @filesource
 */

namespace CodeIgniter\Database\OCI8;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Database\Exceptions\DatabaseException;
use stdClass;

/**
 * Connection for Postgre
 */
class Connection extends BaseConnection implements ConnectionInterface
{

	/**
	 * Database driver
	 *
	 * @var string
	 */
	public $DBDriver = 'OCI8';

	/**
	 * Identifier escape character
	 *
	 * @var string
	 */
	public $escapeChar = '"';

	/**
	 * List of reserved identifiers
	 *
	 * Identifiers that must NOT be escaped.
	 *
	 * @var array
	 */
	protected $reservedIdentifiers = [
		'*',
		'rownum',
	];

	protected $validDSNs = [
		'tns' => '/^\(DESCRIPTION=(\(.+\)){2,}\)$/', // TNS
			// Easy Connect string (Oracle 10g+)
		'ec'  => '/^(\/\/)?[a-z0-9.:_-]+(:[1-9][0-9]{0,4})?(\/[a-z0-9$_]+)?(:[^\/])?(\/[a-z0-9$_]+)?$/i',
		'in'  => '/^[a-z0-9$_]+$/i',// Instance name (defined in tnsnames.ora)
	];

	//--------------------------------------------------------------------

	/**
	 * Reset $stmtId flag
	 *
	 * Used by storedProcedure() to prevent execute() from
	 * re-setting the statement ID.
	 */
	protected $resetStmtId = true;

	/**
	 * Statement ID
	 *
	 * @var resource
	 */
	public $stmtId;

	/**
	 * Commit mode flag
	 *
	 * @var integer
	 */
	public $commitMode = OCI_COMMIT_ON_SUCCESS;

	/**
	 * Cursor ID
	 *
	 * @var resource
	 */
	public $cursorId;

	/**
	 * confirm DNS format.
	 *
	 * @return boolean
	 */
	private function isValidDSN() : bool
	{
		foreach ($this->validDSNs as $regexp)
		{
			if (preg_match($regexp, $this->DSN))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Connect to the database.
	 *
	 * @param  boolean $persistent
	 * @return mixed
	 */
	public function connect(bool $persistent = false)
	{
		if (empty($this->DSN) && ! $this->isValidDSN())
		{
			$this->buildDSN();
		}

		$func = ($persistent === true) ? 'oci_pconnect' : 'oci_connect';

		return empty($this->charset)
			? $func($this->username, $this->password, $this->DSN)
			: $func($this->username, $this->password, $this->DSN, $this->charset);
	}

	//--------------------------------------------------------------------

	/**
	 * Keep or establish the connection if no queries have been sent for
	 * a length of time exceeding the server's idle timeout.
	 *
	 * @return void
	 */
	public function reconnect()
	{
	}

	//--------------------------------------------------------------------

	/**
	 * Close the database connection.
	 *
	 * @return void
	 */
	protected function _close()
	{
		oci_close($this->connID);
	}

	//--------------------------------------------------------------------

	/**
	 * Select a specific database table to use.
	 *
	 * @param string $databaseName
	 *
	 * @return boolean
	 */
	public function setDatabase(string $databaseName): bool
	{
		return false;
	}

	//--------------------------------------------------------------------

	/**
	 * Returns a string containing the version of the database being used.
	 *
	 * @return string
	 */
	public function getVersion(): string
	{
		if (isset($this->dataCache['version']))
		{
			return $this->dataCache['version'];
		}

		if (! $this->connID || ($version_string = oci_server_version($this->connID)) === false)
		{
			return false;
		}
		elseif (preg_match('#Release\s(\d+(?:\.\d+)+)#', $version_string, $match))
		{
			return $this->dataCache['version'] = $match[1];
		}

		return false;
	}

	//--------------------------------------------------------------------

	/**
	 * Executes the query against the database.
	 *
	 * @param string $sql
	 *
	 * @return resource
	 */
	public function execute(string $sql)
	{
		if ($this->resetStmtId === true)
		{
			$sql = rtrim($sql, ';');
			if (strpos('BEGIN', ltrim($sql)) === 0)
			{
				$sql .= ';';
			}
			$this->stmtId = oci_parse($this->connID, $sql);
		}

		oci_set_prefetch($this->stmtId, 1000);
		return (oci_execute($this->stmtId, $this->commitMode)) ? $this->stmtId : false;
	}

	//--------------------------------------------------------------------

	/**
	 * Returns the total number of rows affected by this query.
	 *
	 * @return integer
	 */
	public function affectedRows(): int
	{
		return oci_num_rows($this->stmtId);
	}

	//--------------------------------------------------------------------

	//--------------------------------------------------------------------

	/**
	 * Generates the SQL for listing tables in a platform-dependent manner.
	 *
	 * @param boolean $prefixLimit
	 *
	 * @return string
	 */
	protected function _listTables(bool $prefixLimit = false): string
	{
		$sql = 'SHOW TABLES FROM ' . $this->escapeIdentifiers($this->database);

		if ($prefixLimit !== false && $this->DBPrefix !== '')
		{
			return $sql . " LIKE '" . $this->escapeLikeStringDirect($this->DBPrefix) . "%'";
		}

		return $sql;
	}

	//--------------------------------------------------------------------

	/**
	 * Generates a platform-specific query string so that the column names can be fetched.
	 *
	 * @param string $table
	 *
	 * @return string
	 */
	protected function _listColumns(string $table = ''): string
	{
		return 'SHOW COLUMNS FROM ' . $this->protectIdentifiers($table, true, null, false);
	}

	//--------------------------------------------------------------------

	/**
	 * Returns an array of objects with field data
	 *
	 * @param  string $table
	 * @return \stdClass[]
	 * @throws DatabaseException
	 */
	public function _fieldData(string $table): array
	{
		$table = $this->protectIdentifiers($table, true, null, false);

		if (($query = $this->query('SHOW COLUMNS FROM ' . $table)) === false)
		{
			throw new DatabaseException(lang('Database.failGetFieldData'));
		}
		$query = $query->getResultObject();

		$retVal = [];
		for ($i = 0, $c = count($query); $i < $c; $i++)
		{
			$retVal[$i]       = new \stdClass();
			$retVal[$i]->name = $query[$i]->Field;

			sscanf($query[$i]->Type, '%[a-z](%d)', $retVal[$i]->type, $retVal[$i]->max_length);

			$retVal[$i]->default     = $query[$i]->Default;
			$retVal[$i]->primary_key = (int)($query[$i]->Key === 'PRI');
		}

		return $retVal;
	}

	//--------------------------------------------------------------------

	/**
	 * Returns an array of objects with index data
	 *
	 * @param  string $table
	 * @return \stdClass[]
	 * @throws DatabaseException
	 * @throws \LogicException
	 */
	public function _indexData(string $table): array
	{
		$table = $this->protectIdentifiers($table, true, null, false);

		if (($query = $this->query('SHOW INDEX FROM ' . $table)) === false)
		{
			throw new DatabaseException(lang('Database.failGetIndexData'));
		}

		if (! $indexes = $query->getResultArray())
		{
			return [];
		}

		$keys = [];

		foreach ($indexes as $index)
		{
			if (empty($keys[$index['Key_name']]))
			{
				$keys[$index['Key_name']]       = new \stdClass();
				$keys[$index['Key_name']]->name = $index['Key_name'];

				if ($index['Key_name'] === 'PRIMARY')
				{
					$type = 'PRIMARY';
				}
				elseif ($index['Index_type'] === 'FULLTEXT')
				{
					$type = 'FULLTEXT';
				}
				elseif ($index['Non_unique'])
				{
					if ($index['Index_type'] === 'SPATIAL')
					{
						$type = 'SPATIAL';
					}
					else
					{
						$type = 'INDEX';
					}
				}
				else
				{
					$type = 'UNIQUE';
				}

				$keys[$index['Key_name']]->type = $type;
			}

			$keys[$index['Key_name']]->fields[] = $index['Column_name'];
		}

		return $keys;
	}

	//--------------------------------------------------------------------

	/**
	 * Returns an array of objects with Foreign key data
	 *
	 * @param  string $table
	 * @return \stdClass[]
	 * @throws DatabaseException
	 */
	public function _foreignKeyData(string $table): array
	{
		$sql = 'SELECT
                 acc.constraint_name,
                 acc.table_name,
                 acc.column_name,
                 ccu.table_name foreign_table_name,
                 accu.column_name foreign_column_name
  FROM all_cons_columns acc
  JOIN all_constraints ac
      ON acc.owner = ac.owner
      AND acc.constraint_name = ac.constraint_name
  JOIN all_constraints ccu
      ON ac.r_owner = ccu.owner
      AND ac.r_constraint_name = ccu.constraint_name
  JOIN all_cons_columns accu
      ON accu.constraint_name = ccu.constraint_name
      AND accu.table_name = ccu.table_name
  WHERE ac.constraint_type = ' . $this->escape('R') . '
      AND acc.table_name = ' . $this->escape($table);

		if (($query = $this->query($sql)) === false)
		{
			throw new DatabaseException(lang('Database.failGetForeignKeyData'));
		}
		$query = $query->getResultObject();

		$retVal = [];
		foreach ($query as $row)
		{
			$obj                      = new \stdClass();
			$obj->constraint_name     = $row->CONSTRAINT_NAME;
			$obj->table_name          = $row->TABLE_NAME;
			$obj->column_name         = $row->COLUMN_NAME;
			$obj->foreign_table_name  = $row->FOREIGN_TABLE_NAME;
			$obj->foreign_column_name = $row->FOREIGN_COLUMN_NAME;
			$retVal[]                 = $obj;
		}

		return $retVal;
	}

	//--------------------------------------------------------------------

	/**
	 * Returns platform-specific SQL to disable foreign key checks.
	 *
	 * @return string
	 */
	protected function _disableForeignKeyChecks()
	{
		return 'SET FOREIGN_KEY_CHECKS=0';
	}

	//--------------------------------------------------------------------

	/**
	 * Returns platform-specific SQL to enable foreign key checks.
	 *
	 * @return string
	 */
	protected function _enableForeignKeyChecks()
	{
		return 'SET FOREIGN_KEY_CHECKS=1';
	}

	//--------------------------------------------------------------------

	/**
	 * Returns the last error code and message.
	 *
	 * Must return an array with keys 'code' and 'message':
	 *
	 *  return ['code' => null, 'message' => null);
	 *
	 * @return array
	 */
	public function error(): array
	{
		if (! empty($this->mysqli->connect_errno))
		{
			return [
				'code'    => $this->mysqli->connect_errno,
				'message' => $this->mysqli->connect_error,
			];
		}

		return [
			'code'    => $this->connID->errno,
			'message' => $this->connID->error,
		];
	}

	//--------------------------------------------------------------------

	/**
	 * Insert ID
	 *
	 * @return integer
	 */
	public function insertID(): int
	{
		return $this->connID->insert_id;
	}

	//--------------------------------------------------------------------

	/**
	 * Build a DSN from the provided parameters
	 *
	 * @return void
	 */
	protected function buildDSN()
	{
		$this->DSN === '' || $this->DSN = '';

		// Legacy support for TNS in the hostname configuration field
		$this->hostname = str_replace(["\n", "\r", "\t", ' '], '', $this->hostname);

		if (preg_match($this->validDSNs['tns'], $this->hostname))
		{
			$this->DSN = $this->hostname;
			return;
		}

		$isEasyConnectableHostName = $this->hostname !== '' && strpos($this->hostname, '/') === false && strpos($this->hostname, ':') === false;
		$easyConnectablePort       = (( ! empty($this->port) && ctype_digit($this->port)) ? ':' . $this->port : '');
		$easyConnectableDatabase   = ($this->database !== '' ? '/' . ltrim($this->database, '/') : '');

		if ($isEasyConnectableHostName && ($easyConnectablePort !== '' || $easyConnectableDatabase !== ''))
		{
			/* If the hostname field isn't empty, doesn't contain
			 * ':' and/or '/' and if port and/or database aren't
			 * empty, then the hostname field is most likely indeed
			 * just a hostname. Therefore we'll try and build an
			 * Easy Connect string from these 3 settings, assuming
			 * that the database field is a service name.
			 */
			$this->DSN = $this->hostname
				. $easyConnectablePort
				. $easyConnectableDatabase;

			if (preg_match($this->validDSNs['ec'], $this->DSN))
			{
				return;
			}
		}

		/* At this point, we can only try and validate the hostname and
		 * database fields separately as DSNs.
		 */
		if (preg_match($this->validDSNs['ec'], $this->hostname) || preg_match($this->validDSNs['in'], $this->hostname))
		{
			$this->DSN = $this->hostname;
			return;
		}

		$this->database = str_replace(["\n", "\r", "\t", ' '], '', $this->database);
		foreach ($valid_dsns as $regexp)
		{
			if (preg_match($regexp, $this->database))
			{
				return;
			}
		}

		/* Well - OK, an empty string should work as well.
		 * PHP will try to use environment variables to
		 * determine which Oracle instance to connect to.
		 */
		$this->DSN = '';
	}

	//--------------------------------------------------------------------

	/**
	 * Begin Transaction
	 *
	 * @return boolean
	 */
	protected function _transBegin(): bool
	{
		$this->connID->autocommit(false);

		return $this->connID->begin_transaction();
	}

	//--------------------------------------------------------------------

	/**
	 * Commit Transaction
	 *
	 * @return boolean
	 */
	protected function _transCommit(): bool
	{
		if ($this->connID->commit())
		{
			$this->connID->autocommit(true);

			return true;
		}

		return false;
	}

	//--------------------------------------------------------------------

	/**
	 * Rollback Transaction
	 *
	 * @return boolean
	 */
	protected function _transRollback(): bool
	{
		if ($this->connID->rollback())
		{
			$this->connID->autocommit(true);

			return true;
		}

		return false;
	}
	//--------------------------------------------------------------------
}