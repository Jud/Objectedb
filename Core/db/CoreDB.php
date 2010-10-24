<?php
/*
	CoreDB - Jud Stephenson
*/
namespace Objectedb\Core\db;
use Objectedb\Core\db\DB;

class CoreDB
{	
	/**
	*	Return a Result Set
	*/
	static function get($f)
	{
		if($f instanceof Factory)
		{
			if($f->getType() == 'select')
			{
				// opporotunity to cache aggressively
				$query 	= $f->getQuery();

				$db = new DB();
				$query = $db->query($query);
				
				$return = new \stdClass();
				$return->num_rows = self::num($query);
									
				if($return->num_rows > 0)
				{
					while($data = $db->fetch($query))
					{
						$results[] = $data;
					}
					
					$return->results = json_encode($results);
				}
				
				return $return;
			}
		}
	}
	
	/**
	*	Create an object
	*/
	static function create($f)
	{
		if($f->getType() == 'insert')
		{
			$db = new DB();
			$query = $db->query($f->getQuery());
			return $db->insert_id;
		} else
		{
			// can't create something that isn't an insert
			return false;
		}
	}
	
	/**
	*	Execute a query
	*/
	static function query($f)
	{
		$db = new DB();
		return $db->query($f->getQuery());
	}
	
	/**
	*	Return the number of query results
	*/
	static function num($query)
	{		
		return $query->num_rows;
	}
	
	/**
	*	Clean DB input
	*/
	static function clean($str)
	{
		$db = new DB();
		return $db->escape_string($str);
		$db->close();
	}
}

?>