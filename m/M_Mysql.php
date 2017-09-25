<?php
	
define("DB_HOST","localhost");
define("DB_USER","root");
define("DB_PASSWORD","");
define("DB_NAME","express");


class M_Mysql{
	
	private static $instance;
	private $link;
	
	private function __construct(){
		$this->link = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME) or die("Error:".mysqli_error($link));
	
	}
	
	public static function getInstance(){
		if(self::$instance == null) {
			self::$instance = new M_Mysql();
		}
		return self::$instance;
	}
	
	public function Select($sql){
		$result = mysqli_query($this->link, $sql);
		
		if(!$result) die(mysqli_error($this->link));
		
		$count = mysqli_num_rows($result);
		
		$rows = array();
		for($i=0;$i<$count;$i++){
			$rows[] = mysqli_fetch_assoc($result);
		}
		
		return $rows;
	}
	
	public function Insert($table, $object){
		$columns = array();
		$values = array();
		
		foreach($object as $key => $value){
			$key = mysqli_real_escape_string($this->link, $key);
			$columns[] = $key;
			
			if($value == NULL){
				$values[] = "NULL";
			}
			else{
				$value = mysqli_real_escape_string($this->link, $value);
				$values[] = "'$value'";
			}
		}
		
		$columns_s = implode(",", $columns);
		$values_s = implode(",", $values);
		
		$sql = "INSERT INTO $table ($columns_s) VALUES ($values_s)";
		var_dump($sql);
		$result = mysqli_query($this->link, $sql);
		if(!$result) die (mysqli_error($this->link));
		
		return mysqli_insert_id($this->link);
		
	}
	
	public function Update($table, $object, $where){
		
		$sets = array();
		
		foreach($object as $key => $value){
			$key = mysqli_real_escape_string($this->link, $key);
			
			if($value == NULL){
				$sets[] = "$key=NULL";
			}
			else{
				$value = mysqli_real_escape_string($this->link, $value);
				$sets[] = "$key='$value'";
			}
		}
		
		$sets_s = implode(",",$sets);
		
		$sql = sprintf("UPDATE %s SET %s WHERE %s", mysqli_real_escape_string($this->link, $table), $sets_s, $where);
		$result = mysqli_query($this->link, $sql);
		
		if(!$result) die (mysqli_error($this->link));
		
		return mysqli_affected_rows($this->link);	
	}
	
	public function Delete($table, $where){
		$sql = sprintf("DELETE FROM %s WHERE %s", mysqli_real_escape_string($this->link, $table), $where);
		$result = mysqli_query($this->link, $sql);
		if(!$result) die (mysqli_error($this->link));
		
		return mysqli_affected_rows($this->link);
	}
	
}