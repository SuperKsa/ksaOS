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

class Request{
    /**
     * 获取当前的请求类型
     * @param null/string $type 需要判断的请求类型 如传递则认为是判断
     * @return string
     */
    static function M($type=''){
        $M = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] : $_SERVER['REQUEST_METHOD'];
        $M = strtoupper($M);
        if(!$type || ($type && strtoupper($type) == $M)){
            return $M;
        }
    }

    /**
     * filter递归处理
     * @param $fun
     * @param null $value
     * @param null $rule
     * @return array|mixed|null
     */
    static function filterArr($fun, $value=null, $rule=null){
        $funName = __FUNCTION__;
        if(is_array($value)){
            foreach($value as $k => $val){
                $value[$k] = self::$funName($fun, $val, $rule);
            }
        }else{
            return call_user_func_array($fun, [$value, $rule]);
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
        if($rule && is_string($rule) && $value){

            //只要出现下划线 一律认为是正则
            if(strrpos($rule,'/') >0){
                $value = self::filterArr([Filter::class,'reg'],$value, $rule);
            //从Filter类库中找对应函数处理
            }elseif(is_callable([Filter::class, $rule])){
                $value = self::filterArr([Filter::class,$rule],$value);
            //当前是否有可用函数
            }elseif(strpos($rule,'/') === false && is_callable($rule)){
                $value = self::filterArr($rule,$value);
            }
        }
        //过滤器最后return的值不能是null（因为null表示值未传递、未提交）
        $value = is_null($value) ? '' : $value;
        //过滤器最后return的值不能是布尔值false（）
        $value = $value === false ? '' : $value;
        return $value;
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
                    $value = trim($value);//字段名去除左右空白
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
                $value = $value === '' ? null : $value;
                $val = isset($data[$key]) ? self::filter($data[$key], $value) : null;
                $val = is_null($val) ? $deft : $val;
                //最后如果值为null 表示未传递 不返回
                if(!is_null($val)) {
                    $dt[$key] = $val;
                }
            }
        //返回所有变量
        }else{
            $dt = [];
            foreach($data as $key => $value){
                $value = self::filter($value, $rule);
                $value = is_null($value) ? $deft : $value;
                $dt[$key] = $value;
            }
        }
        return $dt;
    }

    /**
     * 获取指定请求的数据(原始值)
     * @param string $M 请求方式
     * @return array
     */
    static function orgData($M=''){
        $M = strtoupper($M);
        if(!$M || !in_array($M,['GET','POST'])){
            parse_str(file_get_contents('php://input'), $inputdata);
        }
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
            default:
                $data = $_GET;
                break;
        }
        if(!$M){
            $data = array_merge($data, $inputdata);
        }
        return $data;
    }


    /**
     * 检查指定变量是否存在
     * @param null $field 变量名
     * @param string $M 请求方式 必须
     * @return bool
     */
    static function has($field=null, $M=''){
        if($M && self::M() == strtoupper($M)){
            $data = self::orgData($M);
            return isset($data[$field]);
        }
    }

    /**
     * 获取参数（所有请求） 参数用法参考 self::_dt()
     */
    function data($field=null, $rule=null, $deft=null){
        return self::_dt(self::orgData(), $field, $rule, $deft);
    }

    /**
     * 获取GET参数 参数用法参考 self::_dt()
     */
    function get($field=null, $rule=null, $deft=null){
        return self::_dt(self::orgData('GET'), $field, $rule, $deft);
    }
    /**
     * 获取POST参数 参数用法参考 self::_dt()
     */
    function post($field=null, $rule=null, $deft=null){
        return self::_dt(self::orgData('POST'), $field, $rule, $deft);
    }
}