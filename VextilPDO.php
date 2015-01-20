<?php

/**
 *
 *  PHP PDO helper class.
 *
 *  @author     Joaquin Cuitiño (vextil@gmail.com)
 *  @copyright  (c) 2015 Joaquin Cuitiño
 *  @license    The MIT License (MIT) - https://github.com/Vextil/VextilPDO/blob/master/LICENSE
 *  @package    VextilPDO
 */

class VextilPDO
{

	private static $_db;
	private static $_debug;
	private static $_queryQuantity = 0;
	private static $_totalQueryTime = 0;
	private static $_queries= array();

	public static function instantiate($dblib, $host, $db, $user, $password, $debug = false)
	{	
		if(!isset(self::$_db)) {
			self::$_db = new PDO(
				$dblib . ':host=' . $host . ';dbname=' . $db, $user, $password, 
				array(
					PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8", 
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
				)
			);
			self::$_debug = $debug;
		}
	}

	public static function row($query, $values = array(), $pdoFetchType = null)
	{
		self::_queryStart($query);
		$row = self::$_db->prepare($query);
		if (is_array($values)) {
			foreach ($values as $key => $data) {
				if (!isset($data['param'])) {
					$data['param'] = PDO::PARAM_STR;
				}
				$row->bindParam(":{$key}", $data['value'], $data['param']);
			}
		}
		$row->execute();
		$result = $row->fetch($pdoFetchType);
		self::_queryEnd();
		return $result;
	}

	public static function rows($query, $values = array(), $pdoFetchType = null)
	{
		self::_queryStart($query);
		$row = self::$_db->prepare($query);
		if (is_array($values)) {
			foreach ($values as $key => $data) {
				if (!isset($data['param'])) {
					$data['param'] = PDO::PARAM_STR;
				}
				$row->bindParam(":{$key}", $data['value'], $data['param']);
			}
		}
		$row->execute();
		$results = $row->fetchAll($pdoFetchType);
		self::_queryEnd();
		return $results;
	}

	public static function insert($table, $values, $update = false)
	{
		self::_queryStart($table);
		foreach ($values as $key => $value) {
			$columns[] = $key;
			$inserts[] = ":{$key}";
		}
		if ($update) { 
			foreach ($columns as $column) {
				$updates[] = "{$column} = :{$column}";
			}
			$updates = implode(', ', $updates);
			$update = "ON DUPLICATE KEY UPDATE {$updates}";
		}
		$columns = implode(', ', $columns);
		$inserts = implode(', ', $inserts);
		$insert = self::$_db->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$inserts}) {$update}");
		foreach ($values as $key => $data) {
			if (!isset($data['param'])) {
				$data['param'] = PDO::PARAM_STR;
			}
			$insert->bindParam(":{$key}", $data['value'], $data['param']);
		}
		$insert->execute();
		self::_queryEnd();
		return $insert->rowCount();
	}

	public static function update($table, $updts = array(), $where = array())
	{
		self::_queryStart($table);
		foreach ($updts as $key => $data) {
			$updates[] = " {$key} = :update_{$key}";
		}
		$first = true;
		foreach ($where as $key => $data){
			if(!isset($data['operator'])) {
				$data['operator'] = '=';
			}
			if ($first) {
				$wheres[] = " WHERE {$key} {$data['operator']} :where_{$key}";
				$first = false;
			} else {
				$wheres[] = " AND {$key} {$data['operator']} :where_{$key}";
			}
		}
		$updates = implode(',', $updates);
		$wheres = implode('', $wheres);
		$update = self::$_db->prepare("UPDATE {$table} SET{$updates}{$wheres}");
		foreach ($updts as $key => $data) {
			if (!isset($data['param'])) {
				$data['param'] = PDO::PARAM_STR;
			}
			$update->bindParam(":update_{$key}", $data['value'], $data['param']);
		}
		foreach ($where as $key => $data) {
			if (!isset($data['param'])) {
				$data['param'] = PDO::PARAM_STR;
			}
			$update->bindParam(":where_{$key}", $data['value'], $data['param']);
		}
		$update->execute();
		self::_queryEnd();
		return $update->rowCount();
	}

	public static function delete($table, $where = array())
	{
		self::_queryStart($table);
		$first = true;
		foreach ($where as $key => $data){
			if(!isset($data['operator'])) {
				$data['operator'] = '=';
			}
			if ($first) {
				$wheres[] = " WHERE {$key} {$data['operator']} :{$key}";
				$first = false;
			} else {
				$wheres[] = " AND {$key} {$data['operator']} :{$key}";
			}
		}
		$wheres = implode('', $wheres);
		$delete = self::$_db->prepare("DELETE FROM {$table}{$wheres}");
		foreach ($where as $key => $data) {
			if (!isset($data['param'])) {
				$data['param'] = PDO::PARAM_STR;
			}
			$delete->bindParam(":{$key}", $data['value'], $data['param']);
		}
		$delete->execute();
		self::_queryEnd();
		return $delete->rowCount();
	}

	public static function exists($table, $values)
	{
		self::_queryStart($table);
		$firstStatement = true;
		foreach ($values as $key => $data){
			if(!isset($data['operator'])) {
				$data['operator'] = '=';
			}
			if ($firstStatement) {
				$where[] = " WHERE {$key} {$data['operator']} :{$key}";
				$firstStatement = false;
			} else {
				$where[] = " AND {$key} {$data['operator']} :{$key}";
			}
		}
		$where = implode('', $where);
		$check = self::$_db->prepare("SELECT COUNT(*) FROM {$table}{$where}");
		foreach ($values as $key => $data) {
			if (!isset($data['param'])) {
				$data['param'] = PDO::PARAM_STR;
			}
			$check->bindParam(":{$key}", $data['value'], $data['param']);
		}
		$check->execute();
		self::_queryEnd();
		return $check->fetchColumn();
	}

	public static function num($table, $values = array())
	{
		self::_queryStart($table);
		$firstStatement = true;
				foreach ($values as $key => $data){
			if(!isset($data['operator'])) {
				$data['operator'] = '=';
			}
			if ($firstStatement) {
				$where[] = " WHERE {$key} {$data['operator']} :{$key}";
				$firstStatement = false;
			} else {
				$where[] = " AND {$key} {$data['operator']} :{$key}";
			}
		}
		if (isset($where)) {
			$where = implode('', $where);
		} else {
			$where = null;
		}
		$check = self::$_db->prepare("SELECT COUNT(*) FROM {$table}{$where}");
		foreach ($values as $value) {
			if (!isset($data['param'])) {
				$data['param'] = PDO::PARAM_STR;
			}
			$check->bindParam(":{$key}", $data['value'], $data['param']);
		}
		$check->execute();
		$check = $check->fetchColumn();
		self::_queryEnd();
		return $check;
	}

	public static function in($query, $values, $pdoFetchType = null)
	{
		self::_queryStart($query);
		$questionMarks = str_repeat('?,', count($values) - 1) . '?';
		$query = str_replace(':inValues', $questionMarks, $query);
		$in = self::$_db->prepare($query);
		$in->execute($values);
		$results = $in->fetchAll($pdoFetchType);
		self::_queryEnd();
		return $results;
	}

	public static function lastInsert($param){
		return self::$_db->lastInsertId($param);
	}

	public static function getQueryQuantity()
	{
		return self::$_queryQuantity;
	}

	public static function getTotalQueryTime()
	{
		++self::$_queryQuantity;
		$begin = microtime(true);
		++self::$_queryQuantity;
		return self::$_totalQueryTime;
				$end = microtime(true);
		self::$_totalQueryTime += ($end - $begin);
	}

	private static function _queryStart($query)
	{
		if (self::$_debug) {
			++self::$_queryQuantity;
			self::$_queries[self::$_queryQuantity]['begin'] = microtime(true);
			self::$_queries[self::$_queryQuantity]['query'] = $query;
		}
	}

	private static function _queryEnd()
	{
		if (self::$_debug) {
			self::$queries[self::$_queryQuantity]['time'] = microtime(true) - self::$queries[self::$_queryQuantity]['begin'];
			unset(self::$queries[self::$_queryQuantity]['begin']);
		}
	}

}