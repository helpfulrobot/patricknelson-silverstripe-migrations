<?php

/**
 * All migrations that must be executed must be descended from this class and define both an ->up() and a ->down()
 * method. Migrations will be executed in alphanumeric order
 *
 * @author	Patrick Nelson, pat@catchyour.com
 * @since	2015-02-17
 */

abstract class Migration {

	abstract public function up();

	abstract public function down();


	#######################################
	## DATABASE MIGRATION HELPER METHODS ##
	#######################################

	/**
	 * Returns true if the table exists in the database
	 *
	 * @param string $table
	 * @return boolean
	 */
	protected static function tableExists($table) {
		$tables = DB::tableList();
		return array_key_exists(strtolower($table), $tables);
	}

	/**
	 * Returns true if a column exists in a database table
	 *
	 * @param string $table
	 * @param string $column
	 * @return boolean
	 */
	protected static function tableColumnExists($table, $column) {
		if (!self::tableExists($table)) return false;
		$columns = self::getTableColumns($table);
		return array_key_exists($column, $columns);
	}

	/**
	 * Returns true if an array of columns exist on a database table
	 *
	 * @param string $table
	 * @param array $columns
	 * @return boolean
	 */
	protected static function tableColumnsExist($table, array $columns) {
		if (!self::tableExists($table)) return false;
		return count(array_intersect($columns, array_keys(self::getTableColumns($table)))) === count($columns);
	}

	/**
	 * Returns an array of columns for a database table
	 *
	 * @param string $table
	 * @return array (empty if table doesn't exist)
	 */
	protected static function getTableColumns($table) {
		if (!self::tableExists($table)) return [];
		return DB::fieldList($table);
	}

	/**
	 * Drops columns from a database table.
	 * Returns false if the table or any of the columns do not exist.
	 * Returns true if the SQL query was executed.
	 *
	 * @param string $table
	 * @param array $columns
	 * @return boolean
	 */
	protected static function dropColumnsFromTable($table, array $columns) {
		$queried = false;
		$existingColumns = self::getTableColumns($table);
		if ($existingColumns) {
			foreach ($columns as $column) {
				if (array_key_exists($column, $existingColumns)) {
					DB::query("ALTER TABLE $table DROP COLUMN $column;");
					$queried = true;
				}
			}
		}
		return $queried;
	}

	/**
	 * Add columns to a database table if they don't exist.
	 * Returns false if the table does not exist.
	 * Returns true if the SQL query was executed.
	 *
	 * @param string $table
	 * @param array $columns e.g. ['MyColumn' => 'VARCHAR(255) CHARACTER SET utf8']
	 * @return boolean
	 */
	protected static function addColumnsToTable($table, array $columns) {
		$queried = false;
		$existingColumns = self::getTableColumns($table);
		if ($existingColumns) {
			foreach ($columns as $column => $properties) {
				if (!array_key_exists($column, $existingColumns)) {
					DB::query("ALTER TABLE $table ADD $column $properties;");
					$queried = true;
				}
			}
		}
		return $queried;
	}

	/**
	 * Gets the value for a single column in a row from the database by the ID column.
	 * Useful when a field has been removed from the class' `$db` property,
	 * and therefore is no longer accessible through the ORM.
	 * Returns `null` if the table, column or row does not exist.
	 *
	 * @param string $table
	 * @param string $columns
	 * @param string||int $id
	 * @return string
	 */
	protected static function getRowValueFromTable($table, $field, $id) {
		$value = null;
		if (self::tableColumnExists($table, $field)) {
			$query = new SQLQuery();
			$query->setFrom($table)->setSelect([$field])->setWhere("ID = $id");
			$results = $query->execute();
			if ($results) {
				foreach ($results as $result) {
					$value = $result[$field];
					break;
				}
			}
		}
		return $value;
	}

	/**
	 * Gets the values for multiple rows on a database table by the ID column.
	 * Useful when fields have been removed from the class' `$db` property,
	 * and therefore are no longer accessible through the ORM.
	 * Returns an empty array if the table, any of the columns or the row do not exist.
	 *
	 * @param string $table
	 * @param array $columns
	 * @param string||int $id
	 * @return array ['FieldName' => value]
	 */
	protected static function getRowValuesFromTable($table, array $fields, $id) {
		$values = [];
		if (self::tableColumnsExist($table, $fields)) {
			$query = new SQLQuery();
			$query->setFrom($table)->setSelect($fields)->setWhere("ID = $id");
			$results = $query->execute();
			if ($results) {
				foreach ($results as $result) {
					foreach ($fields as $field) {
						$values[$field] = $result[$field];
					}
					break;
				}
			}
		}
		return $values;
	}

	/**
	 * Sets the values for multiple rows on a database table by the ID column.
	 * Useful when fields have been removed from the class' `$db` property,
	 * and therefore are no longer accessible through the ORM.
	 * Returns false if the table or any of the rows do not exist.
	 * Returns true if the SQL query was executed.
	 *
	 * @param string $table
	 * @param array $values ['FieldName' => value]
	 * @param string||int $id
	 * @return boolean
	 */
	protected static function setRowValuesOnTable($table, array $values, $id) {
		$queried = false;
		if (self::tableColumnsExist($table, array_keys($values))) {
			$query = "UPDATE $table SET";
			$valuesCount = count($values);
			$i = 0;
			foreach ($values as $field => $value) {
				if (is_string($value)) $value = "'" . Convert::raw2sql($value) . "'";
				$query .= " $field = " . $value;
				if ($i < $valuesCount - 1) $query .= ",";
				$i++;
			}
			$query .= " WHERE ID = $id;";
			DB::query($query);
			$queried = true;
		}
		return $queried;
	}

}
