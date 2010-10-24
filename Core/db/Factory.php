<?php
/* 
	The SQL Builder Engine - Judson Stephenson
	April 25, 2010
*/
namespace Objectedb\Core\db;

class Factory
{
	// Objects that extend Object will
	// add their indexes to this variable
	private $_query, $_prefix, $_body, $_where;
	private $_type, $_user;
	
	/******
	*	Prefix Modifiers
	*	Select, Delete, Update, Insert
	**********/

	public function select($fields=array())
	{
		$start = 'select ';
		$sql = '';
		if(is_array($fields))
		{
			foreach($fields as $k => $field)
			{
				$sql .= $field . ', ';
			}
		} else
		{
			$sql = $fields;
		}
		
		$this->_type   = 'select';	
		$this->_prefix = $start . trim($sql, ', ');
		$this->_query  = $this->_prefix . $this->_body . $this->_where;
		
		return $this;
	}
	
	// delete a record
	public function delete()
	{
		$start = 'delete ';
		
		$this->_type   = 'delete';	
		$this->_prefix = $start;
		$this->_query  = $this->_prefix . $this->_body . $this->_where;
		
		return $this;
	}
	
	// insert a record
	public function insert()
	{
		$start = 'insert into ';
		
		$this->_type   = 'insert';	
		$this->_prefix = $start;
		$this->_query  = $this->_prefix . $this->_body . $this->_where;
		
		return $this;
	}
	
	// update a record
	public function update()
	{
		$start = 'update ';
		
		$this->_type   = 'update';	
		$this->_prefix = $start;
		$this->_query  = $this->_prefix . $this->_body . $this->_where;
		
		return $this;
	}
	
	/******
	*	Body Modifiers
	*	table, fields, values, set, from and left Join
	**********/
		
	public function table($name)
	{
		$table = $name;
		
		$this->_body .= $table;
		$this->_query  = $this->_prefix . $this->_body . $this->_where;

		return $this;
	}
	
	public function fields($fields)
	{
		$sql = '(';
		foreach($fields as $k => $field)
		{
			$sql .= '`' . $field . '`' . ', ';
		}
		$sql = trim($sql, ', ') . ') ';
		
		$this->_body .= $sql;
		$this->_query  = $this->_prefix . $this->_body . $this->_where;

		return $this;
	}
	
	public function values($values)
	{
		$sql = 'VALUES (';
		foreach($values as $k => $value)
		{
			$sql .= '\'' . $value . '\'' . ', ';
		}
		$sql = trim($sql, ', ') . ') ';
		
		$this->_body .= $sql;
		$this->_query  = $this->_prefix . $this->_body . $this->_where;
		
		return $this;
	}
	
	public function set($values)
	{
		$start = ' set ';
		$sql = '';
		if(is_array($values))
		{
			foreach($values as $k => $v)
			{
				if(strstr($v, ':noescape'))
				{
					$sql .= $k . '=' . str_replace(':noescape', '', $v) . ', ';
				} else
				{
					$sql .= $k . '=\'' . $v . '\', ';
				}
			}
		} else
		{
			$sql = $values;
		}
		
		$this->_body  .= $start . trim($sql, ', ');
		$this->_query  = $this->_prefix . $this->_body . $this->_where;
		
		return $this;
	}
	
	// get the from tables
	public function from($tables=array())
	{
		$start = ' from ';
		$sql = '';
		if(is_array($tables))
		{
			foreach($tables as $k => $table)
			{
				$sql .= $table . ',';
			}
		} else
		{
			$sql = $tables;
		}
		
		$this->_body   = $start . trim($sql, ',');
		$this->_query  = $this->_prefix . $this->_body . $this->_where;
		
		return $this;
	}

	// do a left join
	public function left($tables=array())
	{
		$sql = '';
		if(is_array($tables))
		{
			foreach($tables as $t => $on)
			{
				$sql .= ' left join ' . $t . ' on ' . $on;
			}
		} else
		{
			$sql = $tables;
		}
		
		$this->_body  .= trim($sql, ',');
		$this->_query  = $this->_prefix . $this->_body . $this->_where;
		
		return $this;
	}
	
	
	/******
	*	Where Modifiers
	*	Where, onDuplicateKeyUpdate, Sort, Group, Limit
	**********/
	
	public function where($cond=array('1' => '1'))
	{
		$start = ' where ';
		$sql = '';
		if(is_array($cond))
		{
			foreach($cond as $k => $v)
			{
				if(strstr($v, ':noescape'))
				{
					$sql .= $k . '=' . str_replace(':noescape', '', $v) . ' and ';
				} else
				{
					$sql .= $k . '=\'' . $v . '\' and ';
				}
			}
		} else
		{
			$sql = $cond;
		}
		
		$this->_where  .= $start . ((substr(trim($sql), -3) == 'and') ? substr(trim($sql), 0, (strlen(trim($sql))-3)) : trim($sql));
		$this->_query  = $this->_prefix . $this->_body . $this->_where;
		
		return $this;
	}
	
	public function onDuplicateUpdate($array)
	{
		$start = 'on duplicate key update ';
		$sql = '';
		if(is_array($array))
		{
			foreach($array as $k => $v)
			{
				if(strstr($v, ':noescape'))
				{
					$sql .= $k . '=' . str_replace(':noescape', '', $v) . ', ';
				} else
				{
					$sql .= $k . '=\'' . $v . '\', ';
				}
			}
		}
		
		$this->_where  .= $start . trim($sql, ', ');
		$this->_query  = $this->_prefix . $this->_body . $this->_where;
		
		return $this;
	}
	
	public function orderBy($array)
	{
		
	}
	
	public function limit($start, $end)
	{
	
	}
	
	public function user($id)
	{
		$this->_user = $id;
	}
	
	
	/*
		Getters : Jud Stephenson
		April 27, 2010
	*/
	public function getType()
	{
		return $this->_type;
	}
	
	public function getQuery()
	{
		return $this->_query;
	}
	
	public function cacheQuery()
	{
		return md5(strtolower($this->_user . ':' . $this->_query));
	}

	public function getUser()
	{
		return $this->_user;
	}
	
	/*
		Clear Query and Start Fresh : Jud Stephenson
		April 27, 2010
	*/
	public function clear()
	{
		unset($this->_prefix, $this->_body, $this->_query, $this->_where, $this->_type);
		return $this;
	}
}
?>
