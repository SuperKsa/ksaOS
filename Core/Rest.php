<?php
/**
 *
 * -------------------------------
 * Author:  CR180 <cr180@cr180.com>
 * Date:    2020/4/5 11:53
 * Update:  2020/4/5 11:53
 *
 */
namespace ksaOS;

if(!defined('KSAOS')) {
    exit('Error.');
}

class Rest{
    static function isAjax(){
        global $C;
        return $C['ajax'];
    }
    /**
     * 获取当前访问地址
     * @return mixed
     */
    static function url(){
        return $_SERVER['REQUEST_URI'];
    }

    /**
     * 获取当前用户浏览器标示信息
     * @return mixed
     */
    static function useragent(){
        !defined('USERAGENT') && define('USERAGENT', $_SERVER['HTTP_USER_AGENT']);
        return USERAGENT;
    }

    static function ip(){
        if(!defined('IP')) {
            $S = $_SERVER;
            if (!isset($S['REMOTE_ADDR'])) {
                return false;
            }
            $ip = $S['REMOTE_ADDR'];
            if (isset($S['HTTP_CLIENT_IP']) && preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', $S['HTTP_CLIENT_IP'])) {
                $ip = $S['HTTP_CLIENT_IP'];
            } elseif (isset($S['HTTP_X_FORWARDED_FOR']) && preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s', $S['HTTP_X_FORWARDED_FOR'], $matches)) {
                foreach ($matches[0] as $val) {
                    if (!preg_match('#^(10|172\.16|192\.168)\.#', $val)) {
                        $ip = $val;
                        break;
                    }
                }
            }
            if ($ip == '::1') {
                $ip = '127.0.0.1';
            }
            define('IP', $ip);
        }
        return IP;
    }

    static function ipProt(){
        !defined('IPPORT') && define('IPPORT', $_SERVER['REMOTE_PORT']);
        return IPPORT;
    }


    /**
     * 获取当前分页请求
     * @param int $limit 每页数量
     * @return array [int当前页码, int起始数量, int每页数量]
     */
    static function page($limit=20){
        $page = max(1, self::data('page','int'));
        $limit = max(0, intval($limit));
        $start = ($page - 1) * $limit;
        return [$page, $start, $limit];
    }

    /**
     * 获取URL路由部分值
     * @param null/string $n 参数顺序 不传则返回所有 1=第一个
     * @return mixed|null
     */
    static function m($n=null){
        $d = APP::$MOD;
        if(is_null($n)){
            return $d;
        }
        $n = intval($n);
        if($n >0){
            $n --;
            return $d && isset($d[$n]) ? $d[$n] : null;
        }
    }

    /**
     * 校验请求类型是否正确
     * @param null $type 需要判断的请求类型 GET/POST/PUT/DELETE/OPTIONS
     * @param null $key 需要检查的键名
     * @return string
     */
    static function has($type=null, $key=null){
        $M = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] : $_SERVER['REQUEST_METHOD'];
        $M = strtoupper($M);
        if(!$type || ($type && strtoupper($type) == $M)){
            return $key ? self::orgData($M)[$key] : $M;
        }
    }

    /**
     * 获取参数（所有请求） 参数用法参考 self::_dt()
     */
    static function data($field=null, $rule=null, $deft=null){
        return self::_dt(self::orgData(), $field, $rule, $deft);
    }

    /**
     * 获取GET参数 参数用法参考 self::_dt()
     */
    static function get($field=null, $rule=null, $deft=null){
        return self::_dt(self::orgData('GET'), $field, $rule, $deft);
    }
    /**
     * 获取POST参数 参数用法参考 self::_dt()
     */
    static function post($field=null, $rule=null, $deft=null){
        return self::_dt(self::orgData('POST'), $field, $rule, $deft);
    }

    static function file($field=null){
        return self::_dt(self::orgData('FILES'), $field);
    }

    /**
     * 获取HTTP请求中的header值
     * 参数用法参考 self::_dt()
     * 如果需要返回全部数据时，注意键名全部为大写，横杠(-) = _
     * @param null $field
     * @param null $rule
     * @param null $deft
     * @return array|string|null
     */
    static function header($field=null, $rule=null, $deft=null){
        if($field){
            if(is_array($field)){
                $newField = [];
                foreach($field as $key => $value){
                    $key = str_replace('-', '_', $key);
                    $newField[strtoupper($key)] = $value;
                }
                $dt = self::_dt(self::orgData('HEADER'), $newField, $rule, $deft);
                $data = [];
                if(is_array($dt)){
                    foreach($field as $k => $v){
                        $sk = strtoupper($k);
                        if(isset($dt[$sk])){
                            $data[$k] = $dt[$sk];
                        }
                    }
                }
                return $data;
            }else{
                $field = str_replace('-', '_', strtoupper($field));
            }
        }
        return self::_dt(self::orgData('HEADER'), $field, $rule, $deft);
    }

    /**
     * 获取请求中的原始数据
     * @return false|string
     */
    static function input(){
        return file_get_contents('php://input');
    }

    static function inputJson($field=null, $rule=null, $deft=null){
        $input = self::input();
        $input = $input ? json_decode(self::input(), true) : [];
        return self::_dt($input, $field, $rule, $deft);
    }

    /**
     * filter递归处理
     * @param $fun
     * @param null $value
     * @param null $rule
     * @return array|mixed|null
     */
    static function filterArr($fun, $value=null, $param=[]){
        $funName = __FUNCTION__;
        if(is_array($value)){
            foreach($value as $k => $val){
                $value[$k] = self::$funName($fun, $val, $param);
            }
        }else{
            return call_user_func_array($fun, array_merge([$value],$param));
        }
        $value = $value ? $value : false;
        return $value;
    }

    /**
     * 过滤器
     * @param string/array $value
     * @param null $rule
     * @return mixed|string
     */
    static function filter($value=null, $rule=null){
        //规则必须存在 并且值可用
        if($rule && is_string($rule) && !is_null($value)){
            $param = [];
            if(strrpos($rule,'/') === false){
                //如果规则参数的处理
                [$rule, $param] = explode(':',$rule);
                $rule = trim($rule);
                $param = $param ? explode(',',$param) : [];
            }
            //只要第一个字符是斜杠 一律认为是正则
            if(strpos($rule,'/') === 0){
                $value = self::filterArr([Filter::class,'reg'], $value, [$rule]);
            //从Filter类库中找对应函数处理
            }elseif(is_callable([Filter::class, $rule])){
                $value = self::filterArr([Filter::class,$rule], $value, $param);
            //当前是否有可用函数
            }elseif(strpos($rule,'/') === false && is_callable($rule)){
                $value = self::filterArr($rule,$value, $param);
            }
        }
        if(is_array($value)){
            foreach($value as $k => $v){
                if($v === false){
                    unset($value[$k]);
                }
            }
            $value = !$value ? false : $value;
        }
        //过滤器最后return的值不能是null（因为null表示值未传递、未提交）
        $value = is_null($value) ? '' : $value;
        //过滤器最后return的值不能是布尔值false（）
        $value = $value === false ? '' : $value;
        return $value;
    }

    /**
     * 字段别名的处理
     * 'uname > name' : uname字段将会变成name
     * @param $field
     * @return mixed|string
     */
    private static function _reKey($field=''){
        $newField = $field;
        //key支持别名（用尖括号隔开 右侧是where需要的字段）
        if(strpos($field, '>') >0){
            [$field, $whereField] = explode('>', $field);
            $field = trim($field);
            $newField = trim($whereField);
        }
        return [$field, $newField];
    }

    /**
     * 获取变量值 基础函数
     * 获取各种用户端提交的数据
     * @param array $data 数据源
     * @param null/string/array $field 需要的字段名(为空表示所有变量) 多个字段以空格分割 或 传入数组
     * @param null/false/string $rule 过滤规则 null=默认实体化值 false=原始数据 过滤规则=Filter函数名、公共函数名、正则
     * @param null $deft 默认值（值不存在 或 为null时）
     * @return array|string|null null=变量不存在（未传递、未提交）
     */
    static function _dt($data=[], $field=null, $rule=null, $deft=null){

        //如果规则为字符串 且 绝对为空 则认为是null
        $rule = $rule === '' ? null : $rule;
        //输出原始数据 规则===false不存在 字段不存在
        if($rule === false && !$field){
            return $data;
        }
        //过滤规则null 则默认转为实体 htmlspecialchars
        $rule = $rule !== false && is_null($rule) ? 'htmlspecialchars' : $rule;

        //字段名为字符串时转为数组 多个字段以/分割
        if($field && is_string($field)){
            $field = trim($field);
            //多个字段组合为键名=字段 键值=过滤规则
            if(strpos($field,'/') !== false){
                $tmp = [];
                foreach(explode('/',$field) as $value){
                    $tmp[$value] = $rule;
                }
                $field = $tmp;
            }
        }

        //只有一个字段时的处理
        if($field && is_string($field)){
            $dt = $field && isset($data[$field]) ? self::filter($data[$field], $rule) : null;
            $dt = is_null($dt) ? $deft : $dt;
        //多个字段组合为键名=字段 键值=过滤规则
        }elseif(is_array($field)){
            $dt = [];
            foreach($field as $key => $value){
                list($key, $newKey) = self::_reKey($key);
                $value = $value === '' ? null : $value;
                $val = isset($data[$key]) ? self::filter($data[$key], $value) : null;
                $val = is_null($val) ? $deft : $val;
                //最后如果值为null 表示未传递 不返回
                if(!is_null($val)) {
                    $dt[$newKey] = $val;
                }
            }
        //返回所有变量
        }else{
            $dt = [];
            foreach($data as $key => $value){
                list($key, $newKey) = self::_reKey($key);
                $value = self::filter($value, $rule);
                $value = is_null($value) ? $deft : $value;
                $dt[$newKey] = $value;
            }
        }
        return $dt;
    }

    /**
     * 获取指定请求的数据(原始值)
     * @param string $M 请求方式
     * @return array
     */
    static function orgData($M=NULL){
        if($M !== null){
            $M = strtoupper($M);
        }
        
        if($M === NULL || !in_array($M,['GET'])){
            $input = file_get_contents('php://input');
            try{
                $inputdata = json_decode($input, true);
            }catch (\Exception $e){
                try{
                    parse_str($input, $inputdata);
                }catch (\Exception $e2){
                
                }
            }
        }
        
        $inputdata = (array)$inputdata;

        switch ($M){
            case 'POST':
                $data = $_POST;
                break;
            case 'PUT':
                $data = $inputdata;
                break;
            case 'PATCH':
                $data = $inputdata;
                break;
            case 'DELETE':
                $data = $inputdata;
                break;
            case 'HEADER':
                $data = [];
                foreach ($_SERVER as $key => $value) {
                    if(strpos($key, 'HTTP_') === 0){
                        $data[substr($key, 5)] = $value;
                    }
                }
                break;
            case 'FILES' :
                $data = [];
                if($_FILES){
                    foreach($_FILES as $key => $value){
                        $data[$key] = $data[$key] ? $data[$key] : [];
                        if(is_array($value['name'])){
                            foreach($value as $k => $val){
                                foreach($val as $k1 => $v1){
                                    $data[$key][$k1][$k] = $v1;
                                }
                            }
                        }else{
                            $data[$key] = $value;
                        }
                    }
                }
                break;
            default:
                $data = $_GET;
                break;
        }
        if($M === NULL){
            $data = array_merges($data, self::orgData('FILES'));
            $data = array_merges($data, $inputdata);
            
        }
        return (array)$data;
    }

    /**
     * 获取前台提交数据并生成where条件
     * where([
     *      '@test' => '123', //@表示使用原始值 不通过前台获取
     *      'test1' => 'int:10',
     *      'test2' => ['>=', 'int:10']
     * ]);
     * key支持别名（用尖括号隔开 右侧是where需要的字段）：
     * ' key > newkey'
     * @return array
     */
    static function where($rule = array()){
        $dt = self::data();
        $param = func_get_args();
        $where = [];
        foreach($rule as $field => $value){
            //键名前带@符号表示直接参与where，无需前台数据
            if(substr($field, 0, 1) == '@'){
                $where[] = [substr($field, 1), $value];
            }else{
                $factor = '';
                if(is_array($value)){
                    $factor = $value[0];
                    $filter = $value[1];
                }else{
                    $filter = $value;
                }
                $filter = $filter ? $filter : 'text';

                list($field, $whereField) = self::_reKey($field);
                if(isset($dt[$field])){
                    $v = $dt[$field];
                    if(gettype($filter) =='object'){
                        $v = call_user_func($filter, $v, $dt);
                    }else{
                        $v = self::filter($v, $filter);
                    }
                    if($v !== '' && $v !== NULL){
                        $where[] = $factor ? [$whereField, $factor, $v] : [$whereField, $v];
                    }
                }
            }
        }
        return $where;
    }
}
