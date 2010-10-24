<?php
/*
	DB Class - Judson Stephenson
	April 30, 2010
*/
namespace Objectedb\Core\db;

class DB extends \Mysqli
{
	public $user, $pass, $db;
	
	public function __construct()
	{
		$this->user = '****';
		$this->pass = '****';
		$this->db	= 'Objectedb';
		
		parent::__construct('localhost', $this->user, $this->pass, $this->db);
	}
	
	public function fetch($query)
	{
		return $query->fetch_assoc();
	}
	
	public function query($q)
	{
		return parent::query($q);
	}
}
		
?>