<?php
/*
 * Create 2015.06.27 20:25
 * Author: dengsgo
 * Email: deng@fensiyun.com
 */
class EasyDB extends PDO{
	
	private $db_config ;//数据库配置
	private $lastsql = '';//最后一次执行的sql语句
	private $fetch_type = PDO::FETCH_ASSOC;//查询语句返回的数据集类型
	private $sql_stmt = '';//组装的sql语句
	private $query_type = '';//当前正在执行语句类型
	
	public function __construct($config = array()){
		if (empty($config)){
			$this->db_config = Yaf_Application::app()->getConfig()->db->mysql->toArray();//这是yaf框架的用法，你也可以自己改成其他的方式
		}else{
			$this->db_config = $config;
		}
		try {
			$dsn = 'mysql:host='.$this->db_config['host'].';port='.$this->db_config['port'].';dbname='.$this->db_config['dbname'];
			parent::__construct($dsn, $this->db_config['username'], $this->db_config['password'], array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
			$this->exec('set names utf8');
		} catch (PDOException $e) {
			echo 'db cna`t connect';
			exit();
		}
	}
	
	/*
	 * 执行一条SQL语句，适用于比较复杂的SQL语句
	 * 如果是增删改查的语句，建议使用下面进一步封装的语句
	 * 返回值：执行后的结果对象
	 */
	public function sql_query($sql, $data = array()){
		$stmt = $this->prepare($sql);
		$stmt->execute($data);
		return $stmt;
	}
	
	//查询语句，返回单条结果
	//返回值：一维数组
	public function queryOne($sql, $data = array(), $type = ''){
		$type = !empty($type) ? $type : $this->fetch_type;
		return $this->sql_query($sql, $data)->fetch($type);
	}
	
	//查询语句，返回所有结果
	//返回值：二维数组
	public function queryAll($sql, $data = array(), $type = ''){
		$type = !empty($type) ? $type : $this->fetch_type;
		return $this->sql_query($sql, $data)->fetchAll($type);
	}
	
	//执行结果为影响到的行数
	//返回值：数字，影响到的行数
	private function sql_do($sql, $data = array()){
		$stmt = $this->prepare($sql);
		$stmt->execute($data);
		return $stmt->rowCount();
	}
	
	//插入语句，返回值同上
	public function insert($sql, $data = array()){
		return $this->sql_do($sql, $data);
	}
	
	//删除语句，返回值同上
	public function delete($sql, $data = array()){
		return $this->sql_do($sql, $data);
	}
	
	//更新语句，返回值同上
	public function update($sql, $data = array()){
		return $this->sql_do($sql, $data);
	}
	
	/*
	 * 下面是链式操作的一些方法
	 * 使用方式类似于   $db->table_select('mytable')->where('id=2')->go();
	 * 注意：
	 * 链式的第一个方法必须是table_????()
	 * 链式的最后一个方法必须是go(),如果在链式中使用了预编译占位符，需要在go($data)传入参数
	 */
	
	//查询链式起点，$table：表名
	public function table_select($table){
		$this->sql_stmt = 'SELECT $field$ FROM `$table$` $where$ $other$';
		$this->sql_stmt = str_replace('$table$', $table, $this->sql_stmt);
		$this->query_type = 'select';
		return $this;
	}
	
	//更新链式起点，$table：表名
	public function table_update($table){
		$this->sql_stmt = 'UPDATE `$table$` $set$ $where$';
		$this->sql_stmt = str_replace('$table$', $table, $this->sql_stmt);
		$this->query_type = 'update';
		return $this;
	}
	
	//删除链式起点，$table：表名
	public function table_delete($table){
		$this->sql_stmt = 'DELETE FROM `$table$` $where$';
		$this->sql_stmt = str_replace('$table$', $table, $this->sql_stmt);
		$this->query_type = 'delete';
		return $this;
	}
	
	//插入链式起点，$table：表名
	public function table_insert($table){
		$this->sql_stmt = 'INSERT INTO `$table$` $set$';
		$this->sql_stmt = str_replace('$table$', $table, $this->sql_stmt);
		$this->query_type = 'insert';
		return $this;
	}
	
	//链式执行结点，如果链式中使用了预编译占位符，需要在$data参数中传入
	//$data:占位符数据，
	//$type:all,one 返回数据是多条还是一条，只适用于select查询,
	//$fetch_type:返回数据集的格式,默认索引
	public function go($data = array(), $type = 'all', $fetch_type = ''){
		switch ($this->query_type){
			case 'select':
				$this->sql_stmt = str_replace('$field$', '*', $this->sql_stmt);
				$this->sql_stmt = str_replace(array(
					'$other$','$where$'
				), '', $this->sql_stmt);
			if ($type = 'all'){
				return $this->queryAll($this->sql_stmt, $data, $fetch_type);
			}else{
				return $this->queryOne($this->sql_stmt, $data, $fetch_type);
			}
			break;
			
			case 'insert':
			case 'delete':
			case 'update':
				$this->sql_stmt = str_replace('$set$', '', $this->sql_stmt);
				$this->sql_stmt = str_replace('$where$', ' WHERE 1=2', $this->sql_stmt);
				return $this->sql_do($this->sql_stmt, $data);
			break;
			
			default:break;
		}
	}
	
	//链式操作的一些方法
	//field(),where(),order(),group(),limit(),setdata()
	public function __call($name, $args){
		
		switch (strtoupper($name)){
			case 'FIELD':
				$field = !empty($args) ? $args[0] : '*';
				$this->sql_stmt = str_replace('$field$', $field, $this->sql_stmt);
				break;
			case 'WHERE':
				$where = !empty($args) ? ' WHERE '.$args[0] : ' WHERE 1=2';
				$this->sql_stmt = str_replace('$where$', $where, $this->sql_stmt);
				break;
			case 'ORDER':
				$order = !empty($args) ? ' ORDER BY '.$args[0].' $other$' : '';
				$this->sql_stmt = str_replace('$other$', $order, $this->sql_stmt);
				break;
			case 'GROUP':
				$group = !empty($args) ? ' GROUP BY '.$args[0].' $other$' : '';
				$this->sql_stmt = str_replace('$other$', $group, $this->sql_stmt);
				break;
			case 'LIMIT':
				$limit = !empty($args) ? ' LIMIT '.implode(',', $args) : '';
				$this->sql_stmt = str_replace('$other$', $limit, $this->sql_stmt);
				break;
			case 'SETDATA':
				$set = !empty($args) ? ' SET '.$args[0] : '';
				$this->sql_stmt = str_replace('$set$', $set, $this->sql_stmt);
				break;
		}
		return $this;
	}
	
	//自己调试使用,以后废弃
	public function str(){
		$this->sql_stmt = str_replace('$field$', '*', $this->sql_stmt);
		$this->sql_stmt = str_replace(array(
			'$other$','$where$','$set$'
		), '', $this->sql_stmt);
		//$this->sql_stmt = str_replace('$where$', '1=2', $this->sql_stmt);
		echo $this->sql_stmt;
	}
	
	
	
}