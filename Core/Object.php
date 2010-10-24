<?php
/* 
	The Objectedb Engine - Judson Stephenson
	April 25, 2010
*/
namespace Objectedb\Core;
use Objectedb\Core\db\Factory;
use Objectedb\Core\db\CoreDB;
use Objectedb\Core\db\ObjectCache;

class Object
{
	// Objects that extend Object will 
	// add indexes to $_indexes;
	public static $_indexes;
	
	// special words for __get and __set
	public static $_reserved = array('_id', '_type', '_ftypeName', '_connections', '_data', '_user');
	
	// used for internal save, update checks
	private $_checksum;
	
	// publicly accessible instance variables
	public	$_data, $_connections, $id, $type, $typeName;
	
	/**
	*	Simple Construct
	*/
	public function __construct($user='')
	{
		if($user)
		{
			$this->_user = $user;
		}
	}
	
	/**
	*	Object Actions
	*/
	
	/** 
	*	Set this object as the parent of another object
	*/
	public function adopt($obj)
	{
		if($obj->_id)
		{
			if(!in_array($obj->_id, $this->_connections))
			{
				$f->insert()
					->table('connections')
					->fields(array(
							'id',
							'parent',
							'child',
							'parentType',
							'childType',
							'user'
						))
					->values(array(
							'',
							$this->_id,
							$obj->_id,
							$this->_type,
							$obj->_type,
							$this->_user
						));
				if($id = CoreDB::create($f))
				{
					$this->addConnection($obj->_type, $obj->_typeName, $id);
				}
			}
		} else
		{
			return 0;
		}
	}
	
	/**
	*	Add Connection to this class
	*	Used when Inflating Objects and Adding connections
	*	the first time
	*/
	public function addConnection($type, $typeName, $id)
	{
		$this->_connections[$typeName][] = $id;
		$this->_connections[$type][] = $id;
	}
	
	/**
	*	Save an object in its current state
	*	Detects if it the object is new, or needs updating
	*/
	public function save()
	{
		if(isset($this->_id))
		{
			if($this->getChecksum() != self::genChecksum($this))
			{
				return $this->updateObject();
			} else
			{
				return 1;
			}
		} else
		{
			// we are creating
			return $this->createObject();
		}
	}
	
	/**
	*	Private function used by 
	*	save()
	*/
	private function updateObject()
	{
		$f = new Factory();
		$f->update()
			->table('objects')
			->set(array(
					'data' => $this->_encodeData(),
					'type' => $this->_type
				))
			->where(array(
					'objects.id' => $this->_id,
					'objects.user' => $this->_user
				));
				
		$this->_updateIndexes();
		$this->setChecksum();
		return CoreDB::query($f);
	}
	
	/**
	*	Private function used by 
	*	save()
	*/
	private function createObject()
	{
		$objectType = str_replace(array('\\', 'Models', 'Core', 'Objectedb'), '', get_called_class());
		$this->_setType($objectType);
				
		$f = new Factory();
		$f->insert()
			->table('objects')
			->fields(array(
					'id',
					'data',
					'type',
					'user'
				))
			->values(array(
					'',
					CoreDB::clean($this->_encodeData()),
					$this->_type,
					$this->_user
				));
									
		$id = CoreDB::create($f);
		$this->_id = $id;
		
		$this->_updateIndexes();
		return ($this->_id) ? 1:0;
	}
	
	
	/**
	*	Private Utility Functions
	*	Used in the class for repeated functionality
	*/
	
	/**
	*	Inflate objects given a resultset
	*/
	private static function inflate($results, $array=true)
	{
		if($results->num_rows > 0)
		{
			$class = get_called_class();
			
			$results = json_decode($results->results, true);
			foreach($results as $k => $v)
			{
				$name = 'Objectedb\Models\\' . $v['typeName'];
				
				// try to create this model
				try
				{
					@$obj = new $name();
					
				} catch (\Exception $e)
				{
					// unable to load model
					die('Unable to load model');
				}
				
				// created, now inflate data
				$obj->_id 	   = $v['id'];
				$obj->_typeName = $v['typeName'];
				$obj->_type	   = $v['type'];
				$obj->_user	   = $v['user'];
								
				// now do the data
				$theData = self::_decodeData($v['data']);
				if(is_array($theData))
				{
					foreach($theData as $k => $v)
					{
						$obj->$k = $v;
					}
				}
				
				// load the connections
				$f = new Factory();
				$f->select(array(
							'connections.id',
							'childTypes.name as childTypeName',
							'childTypes.id as childType'
						))
					->from('connections')
					->left(array(
							'objectTypes as childTypes'  => 'childTypes.id=connections.childType'
						))
					->where(array(
							'connections.parent'	=> $obj->_id,
							'connections.user'		=> $obj->_user
						));
				
				$q = CoreDB::get($f);

				if($q->num_rows)
				{
					$connections = json_decode($q->results);
					foreach($connections as $k => $v)
					{
						$obj->addConnection($connections->childType, $connections->childTypeName, $connections->id);
					}
				}
				
				$obj->setChecksum();
				
				if($array)
				{
					$out[] = $obj;
				} else
				{
					return $obj;
				}
			}
			
			return $out;
		}
	}
	
	/**
	*	Set the type of object
	*	Grabbing type from db
	*/
	private function _setType($objectType)
	{
		$f = new Factory();
		$f->select(array(
					'objectTypes.name',
					'objectTypes.id'
				))
			->from(array(
					'objectTypes'
				))
			->where(array(
					'objectTypes.name'		=> $objectType,
					'objectTypes.user'		=> $this->_user
				));
		
		$q = CoreDB::get($f);
		$results = json_decode($q->results);
				
		$this->_typeName = $results[0]->name;
		$this->_type	 = $results[0]->id;
	}
	
	/**
	*	Update the indexes on this type
	*/
	private function _updateIndexes()
	{
		$class = get_called_class();
		foreach($this->_data as $k => $v)
		{
			if(@in_array($k, $class::$_indexes))
			{
				$f = new Factory();
				$f->insert()
					->table('indexes')
					->fields(array(
							'id',
							'name',
							'value',
							'object',
							'objectType',
							'user'
							
					))
					->values(array(
							'',
							$k,
							$v,
							$this->_id,
							$this->_type,
							$this->_user
					))
					->onDuplicateUpdate(array(
							'value' => $v
					));
					
				CoreDB::query($f);
			}
		}
	}

	
	/**
	*	Encode Data
	*	Private function used to encode class
	*	for mysql storage
	*/
	private function _encodeData()
	{
		return base64_encode(gzcompress(json_encode($this->_data)));
	}
	
	/**
	*	return an array after decoding 
	*	encoded data
	*/
	private static function _decodeData($data)
	{
		return json_decode(gzuncompress(base64_decode($data)), true);
	}
	
	
	/**
	*	Misc Getters and setters
	*	Used for accessing private variables within this class
	*/
	
	/**
	*	Set Checksum
	*/
	public function setChecksum()
	{
		$this->_checksum = self::genChecksum($this);
	}
	
	/**
	*	Generate Checksum
	*/
	public static function genChecksum($obj)
	{
		return md5(serialize($obj));
	}
	
	/**
	*	Return the checksum of current obj
	*/
	public function getChecksum()
	{
		return $this->_checksum;
	}
	
	
	/**
	*	Object Information
	*	Getting information about an object
	*/
	
	/* 
	*	Get an objects children
	*/
	public static function getChildren($type, $user, $id='', $objects=true)
	{
		$objectType = str_replace(array('\\', 'Models', 'Core', 'Objectedb'), '', get_called_class());
		$class = get_called_class();
		
		$f = new Factory();
		$f->select(array(
					'objects.id', 
					'objects.data', 
					'objects.type',
					'objectTypes.name as typeName'
				 ))
			->from('objects')
			->left(array(
					'connections'  => 'connections.child=objects.id',
					'objectTypes' => 'objects.type=objectTypes.id',
				))
			->where(array(
					'objectTypes.name'		=> $type,
					'connections.parent'	=> (($id)?$id:$this->_id),
					'connections.user'		=> $user
				));
		
		$q = CoreDB::get($f);
		
		return ($objects) ? (self::inflate($q)) : $q;
	}
	
	/**
	*	Return the indexes for this object type
	*/
	public static function getIndexes($user, $search=true)
	{
		$objectType = str_replace(array('\\', 'Models', 'Core', 'Objectedb'), '', get_called_class());
		$class = get_called_class();
		
		if($search)
		{
			$f = new Factory();
			$f->select(array(
						'distinct(indexes.name)',
						'indexes.id'
					))
				->from('objectTypes')
				->left(array(
						'indexes' => 'indexes.objectType=objectTypes.id'
					))
				->where(array(
						'objectTypes.name'		=> $objectType,
						'indexes.user'			=> $user
					));

			$q = CoreDB::get($f);
			
			if($q->num_rows == 0)
			{
				return $class::getIndexes(false);
			}
			
			return $q;
		} else
		{
			if($class)
			{
				$obj = new \stdClass();
				$obj->num_rows = count($class::$_indexes);
				
				if(@$class::$_indexes)
				{
					$obj->results = json_encode($class::$_indexes);
				}
				
				return $obj;
			}
		}
	}
	

	/**
	*	Magic Methods
	*	PHP Magic Methods that do things like get and set
	*/

	/** 
	*	Override the set function to route to
	*	the _data variable unless its in the reserved list
	*/
	public function __set($name, $val)
	{
		// set values
		if(!in_array($name, self::$_reserved))
		{
			$this->_data[$name] = $val;
		} else
		{
			$this->$name = $val;
		}
	}
	
	/**
	*	Do the same for __get
	*/
	public function __get($name)
	{
		if(!in_array($name, self::$_reserved))
		{
			return $this->_data[$name];
		} else
		{
			return $this->$name;
		}
	}
	
	/**
	*	isset also needs to be overridden
	*	not too complicated
	*/
	public function __isset($name)
	{
		if(!in_array($name, self::$_reserved))
		{
			return isset($this->_data[$name]);
		} else
		{
			return isset($this->$name);
		}
	}
	
	
	/**
	*	Static Methods
	*	Static methods return new instances of the class
	*/
	
	/**
	*	__callStatic allows us to do late binding for the ORM Goodness
	*/
	public static function __callStatic($method, $arguments)
	{
		$objectType = str_replace(array('\\', 'Models', 'Core', 'Objectedb'), '', get_called_class());
		$class		= get_called_class();
		
		switch(substr($method, 0, 6))
		{
			case 'findBy':
				
				// check to see if this is an "And" query and Remove the first "And"
				$indicies = $class::_getIndicies($method);
								
				if(!@is_array($indicies['conj']))
				{
					if(@in_array($indicies['index'][0], $class::$_indexes))
					{
						// the index exists so we can look it up
						return $class::_getRecordsByIndexLookup($objectType, $indicies['index'][0], $arguments[0][1], $arguments[1]);
						
					} else
					{
						// throw error
						throw new \Exception('Index does not exist');
					}
				} else
				{
					for($i=0; $i<count($indicies['index']); $i++)
					{
						if(!@in_array($indicies['index'][$i], $class::$_indexes))
						{
							throw new \Exception('Index does not exist');
						}
					}
					
					return $class::_getRecordsByMultipleIndexLookup($objectType, $indicies['index'], $indicies['conj'], $arguments);				
				}
			break;
			
			case 'find':
				return $class::_getRecordsByObjectId($objectType, $arguments[0], $arguments[1]);
			break;
		}
	}
	
	/**
	*	private function that assists in the static binding
	*	allows us to look up by index
	*/
	private static function _getRecordsByIndexLookup($type, $index, $value, $user, $inflate=true)
	{
		
		// build query
		$f = new Factory();
		$f->select(array(
					'objects.id', 
					'objects.data', 
					'objects.type',
					'objectTypes.name as typeName',
					'objects.user as user'
				 ))
		  ->from('indexes')
		  ->left(array(
		  			'objects'		=>	'indexes.object=objects.id',
		  			'objectTypes' 	=>	'indexes.objectType=objectTypes.id'
		  		 ))
		  ->where(array(
		  			'objectTypes.name' 	=> $type,
		  			'indexes.name'		=> $index,
		  			'indexes.value'		=> $value,
		  			'objects.user'		=> $user
		  		  ));		  		  
		
		// we're going to do the caching here
		
		$oc = new ObjectCache();
		
		// get "dirty" caches for this user
		$cache 		= false;
		$is_dirty 	= false;
		$dirtyToken = '';
		$dirty = $oc->get(md5($f->getUser() . ':' . 'dirty'), null, &$dirtyToken);
		
		// see if this item is available cached
		$obj = $oc->get($f->cacheQuery());
		
		if($obj)
		{
			$cache = true;
			$results = json_decode($obj->results);

			if(is_array($results))
			{
				foreach($obj as $k => $o)
				{
					if(@in_array($o->_id, $dirty))
					{
						$is_dirty = true;
					}
				}
				
			} else
			{
				$cache = false;
			}
		}
		
		if(($cache == true) && ($is_dirty == false))
		{
			// set q to cached instance
			$q = $obj;

		} else
		{
			// pull from db
			$q = CoreDB::get($f);
			$oc->set($f->cacheQuery(), $q);
			if($is_dirty)
			{
				// update dirty array
			}
		}
				
		return ($inflate) ? (self::inflate($q, false)) : $q;
	}
	
/**
	*	private function that assists in the static binding
	*	allows us to look up by index
	*/
	private static function _getRecordsByMultipleIndexLookup($type, $indicies, $conj, $values, $inflate=true)
	{
		// build query
		$f = new Factory();
		$f->select(array(
					'objects.id', 
					'objects.data', 
					'objects.type',
					'objectTypes.name as typeName',
					'objects.user as user'
				 ))
		  ->from('objects')
		  ->left(array(
		  		'objectTypes' => 'objectTypes.id=objects.type'
		  		));

		// build joins and where
		foreach($indicies as $k => $index)
		{
			$joins['indexes as idex' . $k] = 'idex' . $k . '.object=objects.id';
			$where['idex' . $k . '.name'] = $index;
			$where['idex' . $k . '.value'] = $values[0][$k];
		}
		
		$where['objectTypes.name'] = $type;
		$where['objects.user'] = $values[1];
				
		$f->left($joins)
		  ->where($where);
				
		// we're going to do the caching here
		$oc = new ObjectCache();

		// get "dirty" caches for this user
		$cache 		= false;
		$is_dirty 	= false;
		$dirtyToken = '';
		$dirty = $oc->get(md5($f->getUser() . ':' . 'dirty'), null, &$dirtyToken);
		
		// see if this item is available cached
		$obj = $oc->get($f->cacheQuery());
		
		if($obj)
		{
			$cache = true;
			$results = json_decode($obj->results);

			if(is_array($results))
			{
				foreach($obj as $k => $o)
				{
					if(@in_array($o->_id, $dirty))
					{
						$is_dirty = true;
					}
				}
				
			} else
			{
				$cache = false;
			}
		}
		
		if(($cache == true) && ($is_dirty == false))
		{
			// set q to cached instance
			$q = $obj;

		} else
		{
			// pull from db
			$q = CoreDB::get($f);
			$oc->set($f->cacheQuery(), $q);
			if($is_dirty)
			{
				// update dirty array
			}
		}
				
		return ($inflate) ? (self::inflate($q, false)) : $q;
	}

	
	/**
	*	Used by static function find() to
	*	find the object by its Object_id
	*/
	private static function _getRecordsByObjectId($type, $id, $user, $inflate=true)
	{
		// build query
		$f = new Factory();
		$f->select(array(
					'objects.id', 
					'objects.data', 
					'objects.type',
					'objectTypes.name as typeName',
					'objects.user as user'
				 ))
		  ->from('objects')
		  ->left(array(
		  			'objectTypes' 	=>	'objects.type=objectTypes.id'
		  		 ))
		  ->where(array(
		  			'objectTypes.name' 	=> $type,
		  			'objects.id'		=> $id,
		  			'objects.user'		=> $user
		  		  ));

		$q = CoreDB::get($f);		
		return ($inflate) ? (self::inflate($q, false)) : $q;
	}
	
	
	/**
	*	Find the Specified indices
	*	from a given string
	**/
	private static function _getIndicies($s)
	{
		$return = array();

		// set action
		$method = ucwords(substr($s, 6));

		// check to see if this is an "And" query
		for($i=0; $i<strlen($method); $i++)
		{
			if(ord($method{$i})>64 && ord($method{$i})<91)
			{
				// if this letter is upper case
				// check if it is And or Or
				if((@ord($method{($i+3)})>64 && @ord($method{($i+3)})<91) || (@ord($method{($i+2)})>64 && @ord($method{($i+2)})<91))
				{
					// if the 4rd or 3nd letter is capitalized, it might be an "And" or "Or"
					$len = 0;
					if(substr($method, $i, 3) == 'And')
					{
						$len = 3;
						$return['conj'][] = 'And';
						
					} else if(substr($method, $i, 2) == 'Or')
					{
						$len = 2;
						$return['conj'][] = 'Or';
					}
					
					$i = ($i+$len-1);
				}
			}else
			{
				// now find what index they are talking about
				$tmpidex = substr($method, $i-1);

				for($j=1; $j<strlen($tmpidex); $j++)
				{
					if(ord($tmpidex{$j})>64 && ord($tmpidex{$j})<91)
					{
						if((@ord($tmpidex{($j+3)})>64 && @ord($tmpidex{($j+3)})<91) || (@ord($tmpidex{($j+2)})>64 && @ord($tmpidex{($j+2)})<91))
						{
							if(substr($tmpidex, $j, 3) == 'And')
							{
								break;
							} else if(substr($tmpidex, $j, 2) == 'Or')
							{
								break;
							}
						}
					}
					
				}
	
				$return['index'][] = strtolower(substr($tmpidex, 0, $j));
				$i = ($i+$j-2);
			}
		}

		return $return;
	}
}
?>