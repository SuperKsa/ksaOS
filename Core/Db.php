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
 * @return DB 返回对象
 * DB('setting')->get();
 */
function DB($table , $as=''){
	$ExtensionFile = PATHS.'table/table_'.$table.'.php';
	if(is_file($ExtensionFile)){
		$new = 'ksaOS\DB_'.$table;
		if(!class_exists($new,false)){
			include_once $ExtensionFile;
		}
		
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
	private $orders = [];
	private $limits = ['start'=>NULL, 'limit'=>NULL];
	private $pages = 0;
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
	 * 提供给子类一个对象
	 * @return $this
	 */
	protected function OBJ(){
		return $this;
	}

	/**
	 * 构造器 初始化数据表 所有用到的数据表必须使用此函数，否则无法执行分布式部署
	 * 联合查询例子：DB('user','a')->table('user_mobile','b','b.uid=a.uid','LEFT')->table('user_sms','c',['c.uid=a.uid','c.uid=b.uid'],'LEFT')->where(['a.uid'=>1])
	 * @param string $table 表名 （不带前缀）
	 * @param string $as 联合查询 AS别名
	 * @param string $on 联合查询 ON条件 (支持单个和数组模式：a.field=b.field 或 ['a.field=b.field', 'c.field=b.field'])
	 * @param string $join 联合查询 联合方式（LEFT RIGHT INNER FULL）
	 * @return $this
	 */
	public function table($table, $as='', $on='', $join=''){
		$join = $join ? strtoupper($join) : '';
		$on = $on ? trims($on) : '';
		if(($this->table && $as && $on && $this->tableJoin[$this->table]) || (!$this->table && $as)){
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
	 * 支持第一个参数以数组方式传递，此时不能传第二个参数
	 * 参数支持闭包函数返回值 （PHP7特性 一般用于子查询）
	 * @return $this
	 */
	public function select(){
		$args = func_get_args();
		if(!isset($args[2]) && is_array($args[0])){
			$args = $args[0];
		}
		foreach($args as $val){
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


    /**
     * 普通传参：
            1. 1参数 ('a=b AND c=d') 解：直接作为where条件输入
            2. 2参数 (a,b) 解： a=b
            3. 3参数 (a,'!=',b) 解： a!=b

        数组传参：（参数1=查询参数 , 参数2=连接符and/or不指定默认and）
            1.参数
            ([a=>b,c=>d])   解：a=b AND c=d
            2.每组为数组 每组用法与1、2相同：
            ([ [a,b] , [a,'!=',b] ]) 解：a=b AND a != b
     *
     */

	public function where(){
	    $con = 'AND';
        $WS = [];
        $args = func_get_args();
        //三个参数(1、2参数为字符串) (a,'!=',b) 解： a!=b
        if(isset($args[2]) && is_string($args[0])){
            $WS[] = $this->__field($args[0], $args[2], $args[1]);
        //两个参数(第一个参数为字符串) (a,b) 解： a=b
        }elseif(isset($args[1]) && is_string($args[0])){
            $WS[] = $this->__field($args[0], $args[1]);

        //只有一个参数时 必须为数组
        }elseif($args[0]){
            if(is_array($args[0])){
                foreach($args[0] as $key => $value){
                    //如果键名是数字 ([ [a,b] , [a,'!=',b] ]) 解：a=b AND a != b
                    if(is_int($key)){
                        //值是数组 则表示一组 2-3参数
                        if(is_array($value)){
                            $WS[] = isset($value[2]) ? $this->__field($value[0],$value[2],$value[1]) : $this->__field($value[0],$value[1]);
                        }
                    //([a=>b,c=>d])   解：a=b AND c=d
                    }else{
                        $WS[] = $this->__field($key, $value);
                    }
                }
            //('a=b AND c=d') 解：直接作为where条件输入
            }elseif(is_string($args[0])){
                $WS[] = $args[0];
            }
            if($args[1] && strtolower($args[1]) =='or'){
                $con = 'OR';
            }
        }

        //过滤空值
        foreach($WS as $key => $value){
            if(!$value){
                unset($WS[$key]);
            }
        }
        if($WS){
            $this->__where[] = [$con,$WS];
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
					$this->orders[] = [$k, $v];
				}
			}
		}elseif($args[0] && !is_array($args[0]) && (!isset($args[1]) || $args[1] && !is_array($args[1]))){
			$args[1] = strtoupper(trim($args[1]));
			if(!in_array($args[1],['ASC','DESC'])){
				$args[1] = $def;
			}
			$this->orders[] = [$args[0], $args[1]];
		}

		return $this;
	}

    /**
     * 随机排序
     * 没有任何参数
     */
	public function rand(){
	    $this->orders = [['RAND()', '']];
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
     * 此处不做安全过滤 最后再进行
	 * limit(1)		SQL解析：limit 1
	 * limit(0,10)		SQL解析：limit 0,10
	 * @return $this
	 */
	public function limit($start = NULL, $limit=NULL){
	    //如果只有start 没有limit 则将start转给limit
	    if($start && $limit === NULL){
            $limit = $start;
            $start = 0;
        }
        $this->limits['start'] = $start;
        $this->limits['limit'] = $limit;
		return $this;
	}

    /**
     * 构造器 分页
     * 此处不做安全过滤 最后再进行
     * @param null $page 查询的页码
     * @param null $limit 每页查询的数量
     * @return $this
     */
    public function page($page = NULL, $limit = NULL){
        if($limit !== NULL){
            $this->limits['limit'] = $limit;
        }

        if($page !== NULL || $this->limits['limit'] >= 0){
            $page = max(1, intval($page));
            $this->pages = $page;
            $this->limits['start'] = ($page - 1) * $this->limits['limit'];
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
		if($this->orders){
			foreach($this->orders as $k => $v){
				$this->orders[$k] = $v[0].' '.$v[1];
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
                $tbd =  $this->tableJoin[$value];
                if(in_array($tbd[1], ['LEFT','RIGHT','INNER','FULL'])){
                    $tableJoins .= ' '.$tbd[1].' JOIN ';
                }else{
                    $tableJoins .= $tableJoins ? ', ' : '';
                }
                $tableJoins .= $this->tableName($value);
                $tableJoins .= ' '.$tbd[0];
                if(in_array($tbd[1], ['LEFT','RIGHT','INNER','FULL'])){
                    $tableJoins .= ($tbd[2] ? ' ON ' . $tbd[2] : '');
                }elseif($tbd[2]){
                    $tmp = [['',$tbd[2]]];
                    foreach($this->__where as $v){
                        $tmp[] = $v;
                    }
                    $this->__where = $tmp;
                }
			}
			$sql[] = $tableJoins;
		}else{
			$sql[] = $this->tableName($this->table);
		}
		if(in_array($idef, ['update','insert','replace'])){
			$sql[] = 'SET {%idef%}';
		}

		//update select delete三种模式才能使用where
		if(in_array($idef, ['select', 'update', 'delete']) && $this->__where){
		    $where = [];
		    foreach($this->__where as $value){
                $v = (is_array($value[1]) ? implode(' '.$value[0].' ',$value[1]) : $value[1]);
		        if($value[0] =='OR'){
                    $where []= ' ('.$v.')';
                }else{
		            $where []= $v;
                }
            }
			$sql[] = 'WHERE '.implode(' AND ', $where);
		}
		if($idef =='select'){
			$sql[] = $this->orders ? 'ORDER BY '.implode(', ',$this->orders) : '';
			$sql[] = $this->group ? 'GROUP BY '.implode(', ',$this->group) : '';
		}


		if(in_array($idef, ['select', 'delete']) && $this->limits){
		    $limit = [];

		    if($this->limits['start'] !== NULL){
                $this->limits['start'] = intval($this->limits['start']);
		        if($this->limits['start'] >= 0){
		            $limit[] = $this->limits['start'];
                }
            }
            if($this->limits['limit'] !== NULL){
                $this->limits['limit'] = intval($this->limits['limit']);
                if($this->limits['limit'] >= 0){
                    $limit[] = $this->limits['limit'];
                }
            }
            if($limit){
                $sql[] = 'LIMIT '.implode(',',$limit);
            }
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
     * 输出指定数据表字段列表信息
     * @param string $table 指定的数据表(注意做好安全过滤)
     * @return array
     */
    public function showTable($table=''){
        $data = [];
        $table = self::$DB->table($table);
        if($table){
            $dbName = self::$DB->config['server'][self::$DB->curID]['name'];
            if($dbName){
                $data = self::$DB->fetch_all('SELECT * FROM information_schema.columns WHERE `table_schema`="'.$dbName.'" AND `table_name`="'.$table.'"');
            }
        }
        return $data;
    }

	public function query($sql=''){
        return self::$DB->fetch_all($sql);
    }

    /**
     * 分页SQL查询 查询之前必须使用 this->page() 函数初始化分页处理
     * @param string $keyfield
     * @return array 固定返回  page=当前页码 maxpage=最大页数 limit=每页数量 count=总数据量 list=列表
     */
    public function fetch_page($keyfield=''){
        $select = $this->selects;
        $order = $this->orders;
        $limits = $this->limits;
        $this->selects = [];
        $this->orders = [];
        $this->limits = [];
        $count = $this->fetch_count();

        $this->selects = $select;
        $this->orders = $order;
        $this->limits = $limits;
        $data = $count > 0 ? $this->fetch_all($keyfield) : [];
        $maxPage = ceil($count / $this->limits['limit']);
        $this->pages = $this->pages > $maxPage ? $maxPage : $this->pages;
        return ['page'=>$this->pages, 'maxpage'=>$maxPage, 'limit'=>$this->limits['limit'], 'count' => $count, 'list'=>$data];
    }

	/**
	 * SQL查询 返回所有查询到的数据
	 * @param string $keyfield 返回的数据键名字段名（默认为空 自然排序0-9）
	 * @param bool $silent 静默模式 false=否 true=是 默认为false
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
	 * @param bool $silent 是否为静默查询 默认false=否
	 * @return array
	 */
	public function fetch_first($silent=false){
		if(!$this->table){
			return [];
		}
		if(!$this->limits['start'] && !$this->limits['limit']){
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
	 * @param bool $silent 静默模式 false=否 true=是 默认为false
	 * @return bool|number
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

            //如果值不存在 但值是数组、字符串 则调整值为NULL
            if(!$val && is_array($val)){
                $val = NULL;
            }
            //送到修饰符处理步骤 进一步对val进行严格处理
            list($field, $val, $tp) = $this->__modify($key, $val,1);
            $val = is_null($val) ? 'NULL' : '\''.$val.'\'';
            $data[$key] = '`'.$field.'`='.$val;


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
     * 数据累计更新
     * @param array/field $data 需更新的字段数组([key => 1, key=>-1]) 或 字段名key
     * @return bool
     */
	public function updateHeap($data=[]){
        if(!$this->table || empty($data) || empty($this->__where)){
            return false;
        }
        $sql = $this->sql('update');
        if(is_array($data)){
            foreach($data as $key => $val){
                $val += 0;
                if($val !== 0){
                    $data[$key] = '`'.$key.'`=`'.$key.'` '.($val>0 ? '+' : '').$val;
                }
            }
        }else if(is_string($data)){
            $data = ['`'.$data.'`=`'.$data.'` +1'];
        }

        if(!$data){
            return false;
        }
        $set = implode(' , ',$data);
        $sql = str_replace('{%idef%}',$set,$sql);
        $this->tableLink();
        $res = self::$DB->update($sql);
        $this->__cache('del');//删缓存
        return $res;
    }
	
	/**
	 * DB操作 - 更新数据 update
	 * 必须存在where()
	 * @param array $data 需要更新的数据数组，key=字段名 value=记录值(字段名前带@则值=原始值)
	 * @param bool $step 数值累加减模式（阅读量等操作 默认false，开启后$data数据结构：['key'=>+1 , 'key2'=>-1]）
	 * @return boolean
	 */
	public function update($data=[], $step = false) {
		if(!$this->table || empty($data) || empty($this->__where)){
			return false;
		}
		$sql = $this->sql('update');

		foreach($data as $key => $val){
            //如果字段名前带@ 则 值=原始值
            if(substr($key, 0, 1) == '@'){
                $data[$key] = '`'.substr($key, 1).'`='.$val;
                continue;
            }
            
            $thsStep = $step;
			$s = '=';
			if($step){
                $k = substr($val, 0,1);
                if(in_array($k, ['+', '-'])){
                    $val = abs($val);
                    $s = $s.' `'.$key.'` '.($k =='-' ? '-' : '+');
                }else{
                    $thsStep = false;
                }
			}
            //如果值不存在 但值是数组、字符串 则调整值为NULL
            if(!$val && is_array($val)){
                $val = NULL;
            }
			//送到修饰符处理步骤 进一步对val进行严格处理
            list($field, $val, $tp) = $this->__modify($key, $val,1);
            $val = $thsStep ? $val : (is_null($val) ? 'NULL' : '\''.$val.'\'');
            
			$data[$key] = '`'.$field.'`'.$s.$val;
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
	 * @param string $sql 要执行的sql
	 * @param bool $silent 静默模式，不返回错误
	 * @param bool $unbuffered 是否取缓存数据 (true=是 false=否默认false）
	 * @return array|bool|null
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
     * 字段值修饰符过滤器
     * 修饰符(Modifier)：
     *	    int=纯数字
     *	    abc=纯字母
     *	    intabc=纯数字或字母
     *	    intabcs=纯数字、字母、下划线、横杠
     * @param string $key 带修饰符的字段 Modifier:field
     * @param string $val 字段值
     * @param int $type 处理方式（0=where查询 1=update|insert入库）
     * @return array [key , value, Modifier]
     */
	public function __modify($key='',$val='', $type=0){
		$tp = 'string';
		$n = strpos($key,':');
		//如果字段名存在过滤修饰符
		if($n>0){
			$tp = substr($key, 0, $n);
			$key = substr($key,$n+1);
		}
		$tp = strtolower($tp);
        if($val){
            $val = str_replace(['\\\\', '\\\'', '\\"', '\'\''], '', $val);
            $val = caddslashes($val);
        }


		switch($tp){
			case 'int': //纯数字
				$val = preg_replace('/[^0-9]/','',$val); break;
			case 'abc': //纯字母
				$val = preg_replace('/[^a-z]/i','',$val); break;
			case 'abcs': //纯字母、下划线、横杠
				$val = preg_replace('/[^a-z-_]/i','',$val); break;
			case 'intabc': //纯数字或字母
				$val = preg_replace('/[^a-z0-9]/i','',$val); break;
			case 'intabcs': //纯数字、字母、下划线、横杠
				$val = preg_replace('/[^a-z0-9-_]/i','',$val); break;
            case 'json': //JSON支持
                //入库直接将数组转为JSON
                if($type){
                    $val = is_null($val) ? 'null' : (is_array($val) ? json_encode($val, JSON_UNESCAPED_UNICODE) : $val);
                //查询 封装处理 查询格式必须为'json:field'=>[jsonKEY,jsonVALUE]
                }else{
                    $val = 'JSON_CONTAINS('.$key.',JSON_OBJECT(\''.$val[0].'\', \''.$val[1].'\'))';
                    $key = 'json';
                }
                break;
            default:
				break;
		}
		return [$key, $val, $tp];
	}

	/**
	 * 内部函数 where 字段二次格式化处理 统一格式化为 `a` = 'xx'
	 * @param string $field 字段名
	 * @param string|array|callable|int $val 值 （支持数组 function）
	 * @param string $glue 运算符 (支持 = - + | & ^ >  < <> <= >= != null like likes in  notin)
	 * @return string
	 * @throws DbException
	 */
	public function __field($field='', $val='', $glue = '=') {
		$glue = $glue === false ? '=' : strtolower($glue);

		list($field, $val, $tp) = self::__modify($field,$val);

		//查询条件标准组合函数 外部使用
		$field = self::__fieldQ($field);
		$isObj = 0;

		if(is_object($val)){
			$v = $val();
			$val = $v;
			$isObj = 1;
			unset($v);
		}elseif(is_null($val) && $glue == '='){
			$glue = 'null';
		}

		if (!$isObj && in_array($glue,['!=','=','in','notin'])) {

			if(is_array($val)){
                $c = count($val);
				if($c ==1){
					$val = reset($val);
					if($glue =='in'){
                        $glue = '=';
                    }elseif($glue =='notin'){
                        $glue = '!=';
                    }
				}elseif($c >1){
                    if($glue =='='){
                        $glue = 'in';
                    }elseif($glue =='!='){
                        $glue = 'notin';
                    }
				}
			} elseif ($glue == 'in') {
				$glue = '=';
			}
		}

		switch (true) {
			case (in_array($glue, ['=','>','<','<>','<=','>=','!='])):
				return $field.$glue.self::__valueQ($val);
			case ($glue == '+='):
				return $field.'=' .$field.'+'. abs($val);
			case ($glue == '-='):
				return $field.'=' .$field.'-'. abs($val);
			case ($glue == '-'):
				return $field.'=' . $field.$glue . self::__valueQ($val);
			case ($glue == '+'):
				return $field.'=' . $field.$glue . self::__valueQ($val);
			case ($glue == 'null'):
				return $field.' IS NULL';
			case ($glue == 'notnull'):
				return $field.' IS NOT NULL';
			case (in_array($glue, ['like','keyword','notlike'])): //likes值支持数组多个值 and方式连接
				$s = $glue =='notlike' ? ' NOT LIKE ' : ' LIKE ';
				$u = $tp =='or' ? ' OR ' : ' AND ';
				if(is_array($val)){
					foreach($val as $k => $v){
						if($glue =='keyword'){
							$v = '%'.$v.'%';
						}
						$v = $field.$s.self::__valueQ($v);
						$val[$k] = $v;
					}
					$r = '('.implode($u,$val).')';
				}else{
					if($glue =='keyword'){
						$val = '%'.$val.'%';
					}
					$r = $field.$s.self::__valueQ($val);
				}
				return $r;
			case (in_array($glue, ['in','notin'])):
				$s = $glue =='notin' ? ' NOT IN ' : ' IN ';
				if($isObj){
					return $field.$s.'('.$val.')';
				}else{
					$val = $val ? "'".implode("','", $val)."'" : '\'\'';
					return $field.$s.'('.$val.')';
				}
			default:
				throw new \Exception('错误的SQL条件组合: '.$glue);
		}
	}
	
	/**
	 * 内部函数 字段名统一增加引号`
	 * @param string $field
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
				list($as,$field2) = explode('.',$field);
                //判断是否为json查询
                if(strpos($as, '->') !== false && strpos($as, '$') !== false){
                    return $field;
                }else{
                    $field = $field2;
                }
			}
			if (strpos($field, '`') !== false){
				$field = str_replace('`', '', $field);
			}
			$field = ($as ? '`'.$as.'`.' : '').'`' . $field . '`';
		}
		return $field;
	}

	/**
	 * 内部函数 字段值统一加引号 '
	 * @param string $str
	 * @return string
	 */
	public static function __valueQ($str='') {
		if(is_string($str) || is_numeric($str)){
			return '\''.$str. '\'';
		}elseif($str === NULL){
			return 'NULL';
		}elseif (is_array($str)) {
			return $str ? json_encode($str,JSON_UNESCAPED_UNICODE) : 'NULL';
		}elseif (is_bool($str)){
			return $str ? '1' : '0';
		}
		return '\'\'';
	}
	
	/**
	 * 内部函数 缓存读写操作
	 * @param string $type 操作类型 get=读取 set=写入更新 del=删除
	 * @param string|array|int $cacheData 缓存数据
	 * @return string|array|int
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
