<?php

/**
 * DB构造器
 * 构造后交给底层DB处理 
 * @desc    cr180内部核心框架
 * @date    2019年12月24日 21:52:42
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file Db.php (KSAOS底层 / UTF-8)
 */
namespace ksaOS;

if(!defined('KSAOS')) {
	exit('Error.');
}

/**
 * 数据库操作
 * @param String 传入表名(不带前缀)
 * @return \class 返回对象
 * DB('setting')->get();
 */
function DB($table , $as=''){
	$ExtensionFile = PATHS.'table/table_'.$table.'.php';
	if(is_file($ExtensionFile)){
		include_once $ExtensionFile;
		$new = 'ksaOS\DB_'.$table;
		$new = new $new();
	}else{
		$new = new DB();
	}
	return $new->table($table, $as);
}



class DB{
	
	const _Name = 'DB构造器';
	
	public static $DB;
	
	private $table ='';
	private $tableJoin = []; //联合查询构造表
	//构造器变量
	private $selects = [];
	private $__where = [];
	private $order = [];
	private $limits = [];
	private $group = [];
	private $__SQL = '';


	//缓存器
	private $__isCache = 0; //缓存开关 内部使用
	private $__CacheKEY = 0;
	private $__CacheTime = 0;


	/** 
	 * 初始化DB连接
	 */
	public function init($config=[]) {
		APP::debug()->Start('DBconnect');
		self::$DB = new Db\Mysqls();
		self::$DB->set_config($config);
		self::$DB->connect();
		APP::debug()->End('DBconnect');
		APP::hook(__CLASS__ , __FUNCTION__);
		return $this;
	}
	
	public function close(){
		self::$DB->close();
	}

	/**
	 * 构造器 初始化数据表 所有用到的数据表必须使用此函数，否则无法执行分布式部署
	 * 联合查询例子：DB('user','a')->table('user_mobile','b','b.uid=a.uid','LEFT')->table('user_sms','c',['c.uid=a.uid','c.uid=b.uid'],'LEFT')->where(['a.uid'=>1])
	 * @param type $table 表名 （不带前缀）
	 * @param type $as 联合查询 AS别名
	 * @param type $on 联合查询 ON条件 (支持单个和数组模式：a.field=b.field 或 ['a.field=b.field', 'c.field=b.field'])
	 * @param type $join 联合查询 联合方式（LEFT RIGHT INNER FULL）
	 * @return $this
	 */
	public function table($table, $as='', $on='', $join=''){
		$join = $join ? strtoupper($join) : '';
		$on = $on ? trims($on) : '';
		if(($this->table && $as && in_array($join,['LEFT','RIGHT','INNER','FULL']) && $on && $this->tableJoin[$this->table]) || (!$this->table && $as)){
			if(is_array($on)){
				foreach($on as $key => $value){
					list($l,$r) = explode('=',$value);
					$l = $this->__fieldQ($l);
					$r = $this->__fieldQ($r);
					$on[$key] = $l.'='.$r;
				}
				$on = implode(' AND ',$on);
			}
			$this->tableJoin[$table] = [$as, $join, $on];
		}
		if(!$this->table){
			$this->table = $table;
		}
		return $this;
	}
	
	/**
	 * 构造器 SELECT （可选，默认*）
	 * 一个参数代表一个select 'field-1','field-2',...... || 'count(field) as count','MAX(field) as max'
	 * 参数支持闭包函数返回值 （PHP7特性 一般用于子查询）
	 * @return $this
	 */
	public function select(){
		foreach(func_get_args() as $val){
			if(is_object($val)){
				$this->selects[] = $val();
			}else{
				$val = trim($val);
				if($val){
					$this->selects[] = $val;
				}
			}
		}
		return $this;
	}
	
	private function _We($v){
		$sql = '';
		if(is_array($v)){
			$c = count($v);
			//[key , = , val]
			if($c ==3){
				$sql = $this->__field($v[0], $v[2], $v[1]);
			//[key,val]
			}elseif($c == 2){
				$sql = $this->__field($v[0], $v[1]);
			}elseif(isset($v[0])){
				$sql = $this->__field($v[0], '', 'null');
			}
		//function
		}elseif(is_object($v)){
			$sql = $v();
		}
		return $sql;
	}
	
	/**
	 * 构造器 WHERE（可选）介绍
	 * 
	 * 一个参数：where([key=>val, [key2,'操作符',val2], key3=>val3 .... ])
	 *一个参数支持函数：where([ key=>val, function(){return '';},  [key2,'操作符',val2], key3=>val3])
	 * 两个参数：where(key,val)
	 * 三个参数：where(key, '操作符', value)
	 * N个参数：where([key,val] , [key2,'操作符',val2] , [key3,val3] ...)
	 * N个参数支持函数：where([key,val] , [key2,'操作符',val2] , function(){return '';})
	 * 
	 * 函数支持介绍（PHP7特性 一般用于子查询）
	 *	每组where支持闭包函数 return的值作为一个where条件
	 * 
	 * 操作符支持： = - + | & ^ >  < <= >= != null isnull like in notin
	 * 
	 * val介绍：
	 *	快捷IN where(key , [1,2,3])  where([key=>[1,2,3]]) 解析后 key IN(1,2,3)
	 *	快捷IN 数组内只有一个值则作为条件=  where(key=>[1]) 解析后 key=1
	 *	快捷NULL(判断is_null() ) where([key=>NULL]) where(key,NULL) 解析后 key IS NULL
	 *	如果数组内只有一个值则作为条件=  where([key=>[1]]) where(key,[1]) 解析后 key=1
	 * @return $this
	 */
	public function where() {
		$args = func_get_args();
		$count = count($args);
		//只有一个参数时 必须为数组
		if($count == 1 && is_array($args[0])){
			foreach($args[0] as $key => $value){
				//如果键名是数字
				if(is_int($key)){
					//值是数组 则表示一组 2-3参数
					if(is_array($value)){
						$c = count($value);
						$value = $c ==3 ? [$value[0],$value[1],$value[2]] : [$value[0],$value[1]];
						$this->__where[] = $this->_We($value);
					}elseif(is_object($value)){
						$this->__where[] = $this->_We($value);
					}
				}else{
					$this->__where[] = $this->_We([$key, $value]);
				}
			}
		//两个参数(第一个参数为字符串) 1=键名 2=值
		}elseif($count == 2 && is_string($args[0])){
			$this->__where[] = $this->_We([$args[0], $args[1]]);
			
		//三个参数(1、2参数为字符串) 1=键名 2=操作符 3=值
		}elseif($count == 3 && is_string($args[0])){
			if(is_string($args[1])){
				$this->__where[] = $this->_We([$args[0], $args[1], $args[2]]);
			}
		//N个参数时 每个参数必须是数组 每组2-3个参数
		}else{
			foreach($args as $key => $value){
				if(is_array($value)){
					if(count($value) ==2){
						$value = [$value[0],$value[1]];
					}
				}
				$this->__where[] = $this->_We($value);
			}
		}
		return $this;
	}
	
	/**
	 * 缓存容器
	 * 必须开启redis
	 * 支持所有链式衍生函数 （增删改必须使用cache后，查询时才能使用cache）
	 * @param string/array $Skeys 缓存键名 必须（增删改时可传入多个键名的数组[key1 , key2 , key3]）
	 * @param number $time 缓存时间 秒，0=永久缓存(由config文件控制)
	 */
	public function cache($Skeys=NULL, $time=0){
		$T = gettype($Skeys);
		if($T =='integer'){
			$time = $Skeys;
			$Skeys = NULL;
		}
		if($Skeys){
			if(is_array($Skeys)){
				$this->__CacheKEY = [];
				foreach($Skeys as $value){
					$this->__CacheKEY[] = trim($value);
				}
			}else{
				$this->__CacheKEY = trim($Skeys);
			}
		}
		$this->__isCache = 1;
		$this->__CacheTime = intval($time);
		
		return $this;
	}
	
	/**
	 * 构造器 order
	 * 传入参数例子：
	 * order('field','asc')	= ORDER BY field ASC (有第二个参数时，第二个为排序方式 asc desc 不区分大小写)
	 * order('field')		= ORDER BY field DESC (只有第一个参数，排序方式默认DESC)
	 * order(['field-1'=>'desc','field-2'=>'asc'])	= ORDER BY field-1 DESC , field-2 ASC
	 * order(['field-1', 'field-2'=>'asc'])	= ORDER BY field-1 DESC , field-2 ASC
	 * @return $this
	 */
	public function order(){
		$args = func_get_args();
		$def = 'DESC';
		if(is_array($args[0]) && !isset($args[1])){
			foreach($args[0] as $key => $value){
				$key = trim($key);
				$value = trim($value);
				$k = $key;
				$v = $value;
				if(is_numeric($key)){
					$k = $value;
					$v = '';
				}
				$v = strtoupper($v);
				if($k){
					$v = $v ? $v : $def;
					$this->order[] = [$k, $v];
				}
			}
		}elseif($args[0] && !is_array($args[0]) && (!isset($args[1]) || $args[1] && !is_array($args[1]))){
			$args[1] = strtoupper(trim($args[1]));
			if(!in_array($args[1],['ASC','DESC'])){
				$args[1] = $def;
			}
			$this->order[] = [$args[0], $args[1]];
		}
		return $this;
	}
	
	/**
	 * 构造器 group
	 * group('field')	= GROUP BY field
	 * group('field1','field2')		= GROUP BY field1, field2
	 * @return $this
	 */
	public function group(){
		foreach(func_get_args() as $value){
			if(($value = trim($value))){
				$this->group[] = $value;
			}
		}
		return $this;
	}
	
	/**
	 * 构造器 LIMIT
	 * limit(1)		SQL解析：limit 1
	 * limit(0,10)		SQL解析：limit 0,10
	 * @return $this
	 */
	public function limit(){
		$args = func_get_args();
		$this->limits = [];
		if(isset($args[0])){
			$this->limits[] = intval($args[0]);
		}
		if(isset($args[1])){
			$this->limits[] = intval($args[1]);
		}
		return $this;
	}
	
	public function tableName($table=''){
		return self::$DB->pre.$table;
	}
	
	/**
	 * 返回最终生成的SQL语句字符串
	 */
	public function sql($idef='select'){
		if($this->order){
			foreach($this->order as $k => $v){
				$this->order[$k] = $v[0].' '.$v[1];
			}
		}
		$sql = [];
		
		if($idef =='select'){
			$sql[] = 'SELECT '.($this->selects ? implode(',',$this->selects) : '*').' FROM';
		}elseif($idef =='update'){
			$sql[] = 'UPDATE';
		}elseif($idef =='insert'){
			$sql[] = 'INSERT INTO';
		}elseif($idef =='replace'){
			$sql[] = 'REPLACE INTO';
		}elseif($idef =='delete'){
			$sql[] = 'DELETE FROM';
		}
		
		if($this->tableJoin){
			$tableJoins = '';
			foreach(array_keys($this->tableJoin) as $value){
				$tableJoins .= ($this->tableJoin[$value][1] ? ' '.$this->tableJoin[$value][1].' JOIN ' : '').$this->tableName($value).' '.$this->tableJoin[$value][0].($this->tableJoin[$value][2] ? ' ON '.$this->tableJoin[$value][2] :'');
			}
			$sql[] = $tableJoins;
		}else{
			$sql[] = $this->tableName($this->table);
		}
		if(in_array($idef, ['update','insert','replace'])){
			$sql[] = 'SET {%idef%}';
		}
		
		//update select delete三种模式才能使用where
		if(in_array($idef, ['select', 'update', 'delete'])){
			$sql[] = $this->__where ? 'WHERE '.implode(' AND ',$this->__where) : '';
		}
		if($idef =='select'){
			$sql[] = $this->order ? 'ORDER BY '.implode(', ',$this->order) : '';
			$sql[] = $this->group ? 'GROUP BY '.implode(', ',$this->group) : '';
		}
		if(in_array($idef, ['select', 'delete'])){
			$sql[] = $this->limits ? 'LIMIT '.implode(',',$this->limits) : '';
		}
		$sql = array_filter($sql);
		$sql = implode(' ',$sql);
		$this->__SQL = $sql;
		return $sql;
	}
	
	private function tableLink(){
		//DB查询前初始化 DB连接
		foreach($this->tableJoin as $table => $value){
			self::$DB->table($this->table);
		}
		if($this->table){
			self::$DB->table($this->table);
		}
	}
	
	/**
	 * SQL查询 返回所有查询到的数据
	 * @param type $keyfield 返回的数据键名字段名（默认为空 自然排序0-9）
	 * @param type $silent 静默模式 false=否 true=是 默认为false
	 * @return array
	 */
	public function fetch_all($keyfield = '', $silent=false) {
		if(!$this->table){
			return [];
		}
		
		$sql = $this->sql('select');
		//取缓存
		if(($ret = $this->__cache('get')) !== NULL){
			return $ret;
		}
		$this->tableLink();
		$ret = self::$DB->fetch_all($sql);
		$data = [];
		foreach($ret as $value){
			if ($keyfield && isset($value[$keyfield])) {
				$data[$value[$keyfield]] = $value;
			} else {
				$data[] = $value;
			}
		}
		unset($ret);
		$this->__cache('set',$data);//写缓存
		return $data;
	}
	
	/**
	 * SQL查询 只返回第一条记录
	 * @param type $silent 是否为静默查询 默认false=否
	 * @return array
	 */
	public function fetch_first($silent=false){
		if(!$this->table){
			return [];
		}
		if(!$this->limits){
			$this->limits = [0,1];
		}
		$sql = $this->sql('select');
		//取缓存
		if(($ret = $this->__cache('get')) !== NULL){
			return $ret;
		}
		$this->tableLink();
		$ret = self::$DB->fetch_first($sql);
		$this->__cache('set',$ret);//写缓存
		
		return $ret ? $ret : [];
	}
	
	/**
	 * SQL查询 统计符合sql的数量(等同于mysql_free_result)
	 * @param type $silent 静默模式 false=否 true=是 默认为false
	 * @return number
	 */
	public function fetch_count($silent = false) {
		if(!$this->table){
			return false;
		}
		if(!$this->selects){
			$this->selects[] = 'count(*)';
		}
		$sql = $this->sql('select');
		//取缓存
		if(($ret = $this->__cache('get')) !== NULL){
			return $ret;
		}
		$this->tableLink();
		$ret = self::$DB->fetch_count($sql, $silent, false);
		$this->__cache('set',$ret);//写缓存
		return intval($ret);
	}
	
	
	/**
	 * DB操作 - 删除 delete 
	 * 必须存在where()
	 * @return boolean
	 */
	public function delete() {
		if(!$this->table){
			return false;
		}
		$sql = $this->sql('delete');
		if(!$this->__where){
			return false;
		}
		$this->tableLink();
		$ret = self::$DB->delete($sql);
		$this->__cache('del');//删缓存
		return $ret;
	}
	
	/** 
	 * DB操作 - 插入数据 insert
	 * $data = 需要插入的数据数组，key=字段名 value=记录值
	 * $insert_id 是否返回主键自增值
	 * $replace 是否以替换形式插入
	 * $silent 静默模式，不返回错误
	 */
	public function insert($data=[], $insert_id = false, $replace = false, $silent = false) {
		if(!$this->table || empty($data)){
			return false;
		}
		$sql = $this->sql($replace ? 'replace' : 'insert');
		foreach($data as $key => $val){
			$data[$key] = $this->__field($key, $val, '=');
		}
		$set = implode(' , ',$data);
		$sql = str_replace('{%idef%}',$set,$sql);
		$cmd = $replace ? 'REPLACE INTO' : 'INSERT INTO';
		$this->tableLink();
		$ret = self::$DB->insert($sql, $insert_id);
		$this->__cache('del');//删缓存
		return $ret;
	}
	
	
	/**
	 * DB操作 - 更新数据 update
	 * 必须存在where()
	 * @param type $data 需要更新的数据数组，key=字段名 value=记录值
	 * @param type $step 数值累加减模式（阅读量等操作 默认false，开启后$data数据结构：['key'=>+1 , 'key2'=>-1]）
	 * @return boolean
	 */
	public function update($data=[], $step = false) {
		if(!$this->table || empty($data) || empty($this->__where)){
			return false;
		}
		$sql = $this->sql('update');

		foreach($data as $key => $val){
			$s = '=';
			if($step){
				if(is_numeric($val)){
					$k = substr($val, 0,1);
					$val = abs($val);
					if(in_array($k,['+','-'])){
						$s = $k.$s;
					}
				}
			}
			$data[$key] = $this->__field($key, $val, $s);
		}
		$set = implode(' , ',$data);
		$sql = str_replace('{%idef%}',$set,$sql);
		$this->tableLink();
		$res = self::$DB->update($sql);
		$this->__cache('del');//删缓存
		return $res;
	}
	
	
	
	/**
	 * SQL查询 核心函数
	 * @param type $sql 要执行的sql
	 * @param type $silent 静默模式，不返回错误 
	 * @param type $unbuffered 是否取缓存数据 (true=是 false=否默认false）
	 * @return type
	 */
	public function __query($sql='', $silent = false, $unbuffered = false) {
		
		$ret = self::$DB->query($sql, $silent, $unbuffered);
		if (!$unbuffered && $ret) {
			$cmd = trim(strtoupper(substr($sql, 0, strpos($sql, ' '))));
			if ($cmd === 'SELECT') {

			} elseif ($cmd === 'UPDATE' || $cmd === 'DELETE') {
				$ret = self::$DB->affected_rows();
			} elseif ($cmd === 'INSERT') {
				$ret = self::$DB->insert_id();
			}
		}
		
		return $ret;
	}

	/**
	 * 内部函数 where 字段二次格式化处理 统一格式化为 `a` = 'xx'
	 * @param type $field 字段名
	 * @param type $val 值 （支持数组 function）
	 * @param type $glue 运算符 (支持 = - + | & ^ >  < <> <= >= != null like likes in  notin)
	 * @return type
	 * @throws DbException
	 */
	public function __field($field='', $val='', $glue = '=') {
		$glue = $glue === false ? '=' : strtolower($glue);
		
		//查询条件标准组合函数 外部使用
		$field = self::__fieldQ($field);
		$isObj = 0;
		if(is_object($val)){
			$v = $val();
			$val = $v;
			$isObj = 1;
			unset($v);
		}elseif($val === NULL){
			$glue = 'null';
		}
		if (!$isObj && in_array($glue,['=','in','notin'])) {
			if(is_array($val)){
				if(count($val) ==1){
					$val = reset($val);
					$glue = '=';
				}else{
					$glue = $glue == 'notin' ? 'notin' : 'in';
				}
			} elseif ($glue == 'in') {
				$glue = '=';
			}
		}
		
		switch ($glue) {
			case '=':
				return $field . $glue . self::__valueQ($val);
			case '+=':
				return $field . '=' .$field.'+'. abs($val);
			case '-=':
				return $field . '=' .$field.'-'. abs($val);
			case '-':
				return $field . '=' . $field . $glue . self::__valueQ((string) $val);
			case '+':
				return $field . '=' . $field . $glue . self::__valueQ((string) $val);
			case '|':
			case '&':
			case '^':
				return $field . '=' . $field . $glue . self::__valueQ($val);
			case '>':
			case '<':
			case '<>':
			case '<=':
			case '>=':
				return $field . $glue . self::__valueQ($val);
			case '!=':
				return $field . $glue . self::__valueQ($val);
			case 'null':
				return $field . ' IS NULL';
			case 'notnull':
				return $field . ' IS NOT NULL';
			case 'like': //likes值支持数组多个值 and方式连接
				$val = str_replace(['(',')',';','$','`'],'',$val);
				if(is_array($val)){
					foreach($val as $k => $v){
						$val[$k] = $field.' LIKE '.self::__valueQ($v);
					}
					return implode(' AND ',$val);
				}else{
					return $field . ' LIKE '.self::__valueQ($val);
				}
			case 'in':
				if($isObj){
					return $field.' IN ('.$val.')';
				}else{
					$val = $val ? implode(',', self::__valueQ($val)) : '\'\'';
					return $field.' IN ('.$val.')';
				}
			case 'notin':
				$val = $val ? implode(',', self::__valueQ($val)) : '\'\'';
				return $field.' NOT IN ('.$val.')';
			default:
				throw new \Exception('系统不支持该类型的组合: '.$glue);
		}
	}
	
	/**
	 * 内部函数 字段名统一增加引号`
	 * @param type $field
	 * @return string
	 */
	public static function __fieldQ($field=[]) {
		if (is_array($field)) {
			foreach ($field as $k => $v) {
				$field[$k] = self::__fieldQ($v);
			}
		} else {
			$as = '';
			if(strpos($field,'.') !== false){
				list($as,$field) = explode('.',$field);
			}
			if (strpos($field, '`') !== false){
				$field = str_replace('`', '', $field);
			}
			$field = ($as ? $as.'.' : '').'`' . $field . '`';
		}
		return $field;
	}

	/**
	 * 内部函数 字段值统一加引号 '
	 * @param type $str
	 * @param type $noarray
	 * @return string
	 */
	public static function __valueQ($str='', $noarray = false) {
		$strtype = gettype($str);
		if ($strtype =='string'){
			return '\'' . addslashes($str) . '\'';
		}

		if (in_array($strtype,['integer','double','float'])){
			return '\'' . $str . '\'';
		}
		if($str === NULL){
			return 'NULL';
		}

		if (is_array($str)) {
			if($noarray === false) {
				foreach ($str as &$v) {
					$v = self::__valueQ($v, true);
				}
				return $str;
			} else {
				return '\'\'';
			}
		}

		if (is_bool($str)){
			return $str ? '1' : '0';
		}
		return '\'\'';
	}
	
	/**
	 * 内部函数 缓存读写操作
	 * @param type $type 操作类型 get=读取 set=写入更新 del=删除
	 * @param type $cacheData 缓存数据
	 * @return type
	 */
	private function __cache($type='get', $cacheData=''){
		if(!$this->__isCache){
			return NULL;
		}
		if($type !='del' && is_array($this->__CacheKEY)){
			$this->__CacheKEY = reset($this->__CacheKEY);
		}
		
		if($this->__isCache && !$this->__CacheKEY && $this->__SQL){
			$this->__CacheKEY = 'DBD_'.md5($this->__SQL);
		}
		if(!$this->__CacheKEY){
			return NULL;
		}
		$time = time();
		if($type =='set'){
			return APP::Cache('set', $this->__CacheKEY, $cacheData, $this->__CacheTime);
		}elseif($type =='get'){
			//取缓存
			return APP::Cache('get', $this->__CacheKEY);
		}elseif($type =='del'){
			if(is_array($this->__CacheKEY)){
				foreach($this->__CacheKEY as $value){
					APP::Cache('del', $value);
				}
			}else{
				APP::Cache('del', $this->__CacheKEY);
			}
		}
		return NULL;
	}
}