<?php

/**
 * @title     全局通用函数库
 * @desc    包含前台、后台、API等等整个系统的公共函数库
 * @date    2019-10-10 14:01:54
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file common.php (KSAOS / UTF-8)
 */
namespace ksaOS;

if(!defined('KSAOS')) {
	exit('Error.');
}

if(defined('FUNCTION_CORE')){
	throw new \Exception('请勿重复载入公共函数库');
}
define('FUNCTION_CORE',true);

/**
 * 脚本文件路径统一引用函数
 * @param type $file = 文件路径（相对于系统根目录）
 */
function loadFile($file){
	return ROOT.'./'.$file.'.php';
}

/** 临时使用函数!!!!
 * CSS文件自动加载并转换为H5
 * @param type $file
 */
function CSSLOAD($file=''){
	global $C;
	$src = $file;
	$fileName = end(explode('/',$file));
	$newfileName = substr($fileName,0,-4).'_auto'.substr($fileName,-4);

	$src = str_replace($fileName,$newfileName,$file);
	if(!is_file(ROOT.$src) || filemtime(ROOT.$src) < filemtime(ROOT.$file)){
		$code = file_get_contents(ROOT.$file);
		$code = preg_replace_callback('/([0-9]+)px/', function($a){
		    $r = $a[0];
			if($a[1]){
				$x = intval($a[1]);
				if($x >1){
					$x = $x / 100;
					$r = $x.'rem';
				}
			}
			return $r;
		},$code);

		file_put_contents(ROOT.$src,$code);
	}
	$S = '?S='.time();
	return $C['staticUrl'].$src.$S;
}

/**
 * 缓存器操作(直接使用RAM缓存器)
 * @param type $skey
 * @param type $data
 * @param type $ttl
 * @param type $isUpdate
 * @return type
 */
function cache($skey, $data=NULL, $ttl=0, $isUpdate=0){
	if($data === NULL){
		return APP::Cache('get', $skey);
	}elseif($data ===''){
		return APP::Cache('del',$skey);
	}else{
		if($isUpdate){
			$dt = APP::Cache('get', $skey);
			if(is_array($dt)){
				foreach($data as $key => $value){
					$dt[$key] = $value;
				}
				$data = $dt;
			}
		}
		return APP::Cache('set', $skey, $data, $ttl);
	}
}

/**
 * 跨域配置
 * @param type $Origin 允许的域名，多个以逗号分割
 * @param type $Methods 允许的请求类型 GET, POST, PATCH, PUT, DELETE
 * @param type $Headers 允许的请求头参数 Authorization, Content-Type
 */
function ACA($Origin='*', $Methods='GET, POST, PATCH, PUT, DELETE',$Headers='*'){
	header('Access-Control-Allow-Origin: '.$Origin);
	header('Access-Control-Allow-Methods: '.$Methods);
	header('Access-Control-Allow-Headers: '.$Headers);
}

/**
 * Debug调试别名
 */
function debug($value=NULL,$dump=0){
	APP::debug()->debug($value, $dump);
}
function debugs(){
    APP::debug()->debug(debug_backtrace());
}

/**
 * 重写trims 使其支持数组
 * @param array $dt 需要trim的string或array
 * @param string $charlist 同trim第二个参数
 * @return array
 */
function trims($dt='', $charlist=null){
	if(is_array($dt)){
		$data = [];
		foreach($dt as $k => $v){
			$data[$k] = trims($v,$charlist);
		}
	}else{
		$dt = trim($dt, $charlist);
	}
	return $dt;
}

function cstrpos($str, $find=[], $start=0) {
    if($str && $find){
        foreach((array)$find as $v) {
            if(strpos($str, $v) !== false) {
                return $v;
            }
        }
    }
    return false;
}

/**
 * 重定向地址
 * @param type $url
 */
function clocation($url=NULL){
	if(is_null($url) || is_array($url)){
		if(is_array($url)){
			$get = $url;
		}else{
			$get = $_GET;
		}
		$url = $get['R'];
		foreach($get as $key => $value){
			if($key=='R' || $value == ''){
				unset($get[$key]);
			}
		}
		$url .= !empty($get) ? '?'.http_build_query($get) : '';
	}
	$url = $url ? $url : '/';
	header('Location: '.$url);
	exit;
}

/**
 * 获取来路地址
 * @global type $C
 * @param type $default 没有来路时的默认地址
 * @return type
 */
function creferer($default = '/') {
	global $C;

	$C['referer'] = !empty($_GET['referer']) ? $_GET['referer'] : $_SERVER['HTTP_REFERER'];
	$C['referer'] = substr($C['referer'], -1) == '?' ? substr($C['referer'], 0, -1) : $C['referer'];

	if(strpos($C['referer'], 'user/login')) {
		$C['referer'] = $default;
	}
	$C['referer'] = $C['referer'] ? $C['referer'] : $default;
	$reurl = parse_url($C['referer']);

	if(!$reurl || (isset($reurl['scheme']) && !in_array(strtolower($reurl['scheme']), ['http', 'https']))) {
		$C['referer'] = '';
	}

	return $C['referer'];
}

/**
 * 将时间戳格式化为日期格式
 * @param Number $timestamp 10位时间戳
 * @param String $format 格式(Y-m-d H:i:s)
 * @return Date
 */
function times($timestamp=0,$format= 'Y-m-d H:i:s'){
	return APP::Date()->times($timestamp, $format);
}

/**
 * 分页别名
 */
function page($num=0, $mpurl='', $perpage=0, $maxpages=0, $isjump=0){
	return APP::page($num, $mpurl, $perpage, $maxpages, $isjump);
}


/** 
 * 将数组或数字格式化为纯数字
 * $nozero		存在	不输出=0的值
 * $array_unique	存在	去除重复值
 */
function ints($dt,$nozero=0,$array_unique=0){
	if(!is_array($dt)) return intval($dt);
	$d = [];
	foreach($dt as $k => $v){
		if(is_array($v)){
			$d[$k] = ints($v,$nozero);
		}elseif($v !=''){
			$v = intval($v);
			if($nozero){
				if($v >0){
					$d[$k] = $v;
				}
			}else{
				$d[$k] = $v;
			}
		}
	}
	if($d && is_array($d) && $array_unique){
		$d = array_unique($d);
	}
	return $d;
}

/**
 * 保留小数位 不作舍入处理
 * @param type $val 原始值
 * @param type $N 小数位位数(1|2|3..) 或者精度(0.001)
 * @return float
 */
function roundF($val='',$N=0){
	if(strpos($N,'0.') === 0){
		$N = strlen(substr($N,2));//小数位精度位数
	}
	if($N>0){
		$val += 0; //解决浮点数作为string传入后带来的判断问题
		if(is_float($val) || is_double($val)){
			$N = pow(10, $N);
			$val = floor($val * $N) / $N;
		}else{
			$val = floor($val);
		}
	}else{
		$val = floor($val);
	}
	return $val;
}

/**
 * htmlspecialchars二次封装 字符转义（用法同htmlspecialchars）
 * @param type $string 
 * @param type $flags
 * @return type
 */
function chtmlspecialchars($string='', $flags = ENT_QUOTES) {
	if(is_array($string)) {
		foreach($string as $key => $val) {
			$string[$key] = chtmlspecialchars($val, $flags);
		}
	} else {
		if($flags === null) {
			$string = str_replace(['&', '"',"'", '<', '>'], ['&amp;', '&quot;','&lsquo;', '&lt;', '&gt;'], $string);
			if(strpos($string, '&amp;#') !== false) {
				$string = preg_replace('/&amp;((#(\d{3,5}|x[a-fA-F0-9]{4}));)/', '&\\1', $string);
			}
		} else {
			$string = htmlspecialchars($string, $flags, 'UTF-8');
		}
	}
	return $string;
}

/**
 * 全局cookies操作
 * @global type $C
 * @param string $key cookie Name
 * @param string $value cookie Value (string类型，如需传布尔值也需要转为字串'false')  如传入空值则将销毁该cookie 不传为读取模式
 * @param type $timesout 超时时间 秒
 * @param type $httponly 是否只允许服务端访问（禁止js读取） false=否 true=是  某些浏览器可能不支持
 * @return type
 */
function cookies($key='',$value=NULL,$timesout=0, $httponly = false) {
	global $C;
	//读取模式
	if($value === NULL){
		return isset($C['cookie'][$key]) ? $C['cookie'][$key] : '';

	//写入模式
	}elseif($key){
		//有效期如果未传则默认1天
		$timesout = $timesout ? $timesout : 86400;

		//如果未传入值 则代表销毁这个cookie
		if(!$value) {
			$value = '';
			$timesout = -1;
		}
		$C['cookie'][$key] = $value;

		$key = COOKIEPRE.$key;
		$_COOKIE[$key] = $value;
		$timesout = $timesout > 0 ? time() + $timesout : ($timesout < 0 ? time() - (86400 *15) : 0);
		return setcookie($key, $value, $timesout, (COOKIEPATH ? COOKIEPATH : './'), COOKIEDOMAIN, $C['HTTPS'] ? 1 : 0, $httponly);
	}
}

/**
 * addslashes二次封装处理 使其支持数组
 * @param type $str 需要处理的字符串或数组
 * @return str
 */
function caddslashes($str='') {
	if(is_array($str)) {
		$keys = array_keys($str);
		foreach($keys as $k) {
			$val = $str[$k];
			unset($str[$k]);
			$str[$k] = caddslashes($val);
		}
	} else {
		$str = addslashes($str);
	}
	return $str;
}


/**
 * 生成指定长度随机字符串
 * @param type $length 需要生成的长度
 * @param type $numeric 是否只生成数字 默认false=否
 * @return type
 */
function rands($length=0, $numeric=false) {
	$seed = base_convert(md5(microtime().$_SERVER['DOCUMENT_ROOT']), 16, $numeric ? 10 : 35);
	$seed = $numeric ? (str_replace('0', '', $seed).'012340567890') : ($seed.'zZ'.strtoupper($seed));
	if($numeric) {
		$hash = '';
	} else {
		$hash = chr(rand(1, 26) + rand(0, 1) * 32 + 64);
		$length--;
	}
	$max = strlen($seed) - 1;
	for($i = 0; $i < $length; $i++) {
		$hash .= $seed{mt_rand(0, $max)};
	}
	return $hash;
}


/**
 * 可逆加解密函数
 * @param type $Type 处理方式 (DECODE=解密 ENCODE=加密)
 * @param type $string 需要处理的字符
 * @param type $key 加解密时需要混淆的Salt
 * @param type $expiry 过期时间
 * @return string
 */
function base($Type = 'DECODE', $string='', $key = '', $expiry = 0) {
	if($Type == 'DECODE') {
		$string = urldecode($string);
	}
	$key .= ENCODEKEY;
	$ckey_length = 8;
	$key = md5($key);
	$keya = md5(substr($key, 0, 16));
	$keyb = md5(substr($key, 16, 16));
	$keyc = $ckey_length ? ($Type == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';

	$skey = $keya.md5($keya.$keyc);
	$klen = strlen($skey);

	$string = $Type == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
	$len = strlen($string);

	$result = '';
	$box = range(0, 255);

	$rndkey = [];
	for($i = 0; $i <= 255; $i++) {
		$rndkey[$i] = ord($skey[$i % $klen]);
	}

	for($j = $i = 0; $i < 256; $i++) {
		$j = ($j + $box[$i] + $rndkey[$i]) % 256;
		$tmp = $box[$i];
		$box[$i] = $box[$j];
		$box[$j] = $tmp;
	}

	for($a = $j = $i = 0; $i < $len; $i++) {
		$a = ($a + 1) % 256;
		$j = ($j + $box[$a]) % 256;
		$tmp = $box[$a];
		$box[$a] = $box[$j];
		$box[$j] = $tmp;
		$result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
	}

	if($Type == 'DECODE') {
		if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
			return substr($result, 26);
		} else {
			return '';
		}
	} else {
		return urlencode($keyc.str_replace('=', '', base64_encode($result)));
	}

}

/**
 * strip_tags二次封装
 * 字符安全过滤(HTML、PHP)
 * @param type $str 字符串
 * @param type $len 需要截取多少个字符
 * @return type
 */
function stripTags($str,$len=0){
	if(is_array($str)){
		foreach($str as $k => $v){
			$str[$k] = stripTags($v,$len);
		}
	}else{
		$str = str_replace(['&nbsp;','&#160;'],'',$str);
		$str = preg_replace('/<([a-z]|\/)[^>]+>/i','',$str);
		$str = chtmlspecialchars(strip_tags($str));
		if($len >0){
			$str = mb_substr($str,0,$len);
		}
	}
	return $str;
}

/**
 * stripslashes二次封装使其支持数组
 * 删除字符串或数组中的反斜杠
 */
function cstripslashes($string='') {
	if(empty($string)) return $string;
	if(is_array($string)) {
		foreach($string as $key => $val) {
			$string[$key] = cstripslashes($val);
		}
	} else {
		$string = stripslashes($string);
	}
	return $string;
}


/**
 * 根据储存单位计算字节
 * @param type $v 单位：1K 1M 1G 1T
 * @return type
 */
function bytes($val=0) {

	$val = trim($val);
	$g = strtolower($val{strlen($val)-1});
	$val = floatval($val);
	switch($g) {
		case 't': $val *= 1024;
		case 'g': $val *= 1024;
		case 'm': $val *= 1024;
		case 'k': $val *= 1024;
	}
	return $val;
}



/**
 * 输出json数据
 * @param array $dt 数据层
 * @param string $g 全局层 ['msg'=>'操作成功','success'=>1,'locationUrl'=>'跳转地址']
 */
function JSON($dt,$g=[]){
	APP::json($dt, $g);
}

/**
 * 直接通过AJAX返回，调用前台静态KSAUI组件进行交互
 * @param  string  $funName 方法名称（区分大小写）
 * @param  array  $param 传递参数 必须是数组，且按照KSAUI函数的参数顺序传递
 */
function KSAUI($funName, $param =[]){
    JSON([
        'KSAUI'=>[
            'type'=> $funName,
            'param'=> $param
        ]
    ]);
    exit;
}


/**
 * JSON编码强制中文不转码
 * @param array $value 需要编码的数据
 * @return false|string
 */
function jsonEn($value=[]){
	return json_encode($value,JSON_UNESCAPED_UNICODE);
}

/**
 * 获取当前用户IP地址
 * @return false|mixed|string
 */
function IP() {
	global $C;
	if(isset($C['IP']) && $C['IP']){
		return $C['IP'];
	}
	$S = $_SERVER;
	if(!isset($S['REMOTE_ADDR'])){
		return false;
	}
	$ip = $S['REMOTE_ADDR'];
	if (isset($S['HTTP_CLIENT_IP']) && preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', $S['HTTP_CLIENT_IP'])) {
		$ip = $S['HTTP_CLIENT_IP'];
	} elseif(isset($S['HTTP_X_FORWARDED_FOR']) && preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s', $S['HTTP_X_FORWARDED_FOR'], $matches)) {
		foreach ($matches[0] as $val) {
			if (!preg_match('#^(10|172\.16|192\.168)\.#', $val)) {
				$ip = $val;
				break;
			}
		}
	}
	if($ip =='::1'){
		$ip = '127.0.0.1';
	}
	$C['IP'] = $ip;
	$C['PROT'] = $_SERVER['REMOTE_PORT'];
	define('IP', $C['IP']);
	define('IPPORT', $C['PROT']);
    define('USERAGENT', $_SERVER['HTTP_USER_AGENT']);
	return $ip;
}

/**
 * CSS rem转px
 * @param string $str CSS CODE
 * @param int $N 1rem={$N}px
 * @return string
 */
function CSS_rem2px($str='',$N=100){
    $new = preg_replace_callback('/([0-9\.]+)rem/i', function($a)use($N){
        $a[1] = floatval($a[1]);
        return floatval($a[1] * $N).'px';
    },$str);
    return $new;
}

/**
 * CSS px转rem
 * @param string $str CSS CODE
 * @param int $N 1rem={$N}px
 * @return string
 */
function CSS_px2rem($str='',$N=100){
    $new = preg_replace_callback('/([0-9]+)px/i', function($a)use($N){
        $a[1] = floatval($a[1]);
        return floatval($a[1] / $N).'rem';
    },$str);
    return $new;
}

/**
 * 提取一个循环列表中指定字段 值集合
 * 用法：
 *      $uids = loopFieldValue($list, 'uid');
 *      list($uids, $goodsIDs) = loopFieldValue($list, ['uid','goodsID']);
 *
 * @param array $data 循环列表数据
 * @param array $keys 需要提取的字段 key 或 [key1,key2]
 * @return array 根据$keys参数 顺序返回
 */
function loopFieldValue($data=[],$keys=[]){
    $dt = [];
    foreach($data as $value){
        if(is_array($keys)){
            foreach($keys as $k => $v){
                if(isset($value[$v])){
                    $dt[$k][$value[$v]] = $value[$v];
                }
            }
        }elseif(isset($value[$keys])){
            $dt[$value[$keys]] = $value[$keys];
        }
    }
    return $dt;
}

/**
 * 递归合并多维
 * @param array $a
 * @param array $b
 * @return array
 */
function array_merges(&$a=[],$b=[]){
    foreach($b as $key => $value){
        if(is_array($value)){
            if(!isset($a[$key])){
                $a[$key] = $value;
            }elseif(is_array($a[$key])){
                array_merges($a[$key], $value);
            }
        }else{
            $a[$key] = $value;
        }
    }
    return $a;
}

/**
 * 清理数组内指定值的行
 *
 * @param array $arr 传入数组
 * @param string $t 清理规则 null=绝对等于null
 * @return array
 */
function array_clears($arr=[], $t='null'){
    $arr = array_filter($arr, function($v)use($t){
        switch ($t){
            case 'null':
                return !is_null($v);
            break;
        }
    });
    return $arr;
}

/**
 * 数组递归求最大值
 * @param array $arr
 * @param string $field
 * @return float|int
 */
function array_valueMax($arr=[], $field=''){
    $num = 0;
    foreach($arr as $value){
        $v = $field ? $value[$field] : $value;
        if(is_array($v)){
            $v = array_valueMax($v, $field);
        }
        $v = floatval($v);
        if($v > $num){
            $num = $v;
        }

    }
    return $num;
}

/**
 * 数组递归求最小值
 * @param array $arr
 * @param string $field
 * @return float|int
 */
function array_valueMin($arr=[], $field=''){
    $num = 0;
    foreach($arr as $value){
        $v = $field ? $value[$field] : $value;
        if(is_array($v)){
            $v = array_valueMin($v, $field);
        }
        $v = floatval($v);
        if($v < $num){
            $num = $v;
        }
    }
    return $num;
}

/**
 * 回调处理数组中的各种字段
 * 场景：
 * 一个数组里有很多字段需要全部做相同计算
array_callbacks($value, ['total', 'total_cost', 'total_sale', 'total_sale_vip', 'shipping_fee', 'start_fee'], function($v){
return $v ? $v / 100 : 0;
});
 * @param array $arr 需要处理的数据
 * @param array $fields 需要处理的对应字段数组[field1, field2, field3, ...]
 * @param string $callfun 回调函数
 */
function array_callbacks(&$arr=[], $fields=[], $callfun=''){
    foreach($fields as $value){
        if(isset($arr[$value])){
            $arr[$value] = call_user_func($callfun, $arr[$value], $value);
        }
    }
}

/**
 * 数字转万字符
 * @param int $number 需要转换的数字
 * @param string $unit 自定义单位 默认W 可以传入 万
 * @param int $precision 舍入保留小数位 默认2
 */
function number_wan($number=0, $unit='W', $precision=2){
    if($number >= 10000){
        $number = bcdiv($number , 10000, $precision);
        $number .= $unit;
    }
    return $number;
}

/**
 * 高精度计算函数
 * @param int $val 值1
 * @param string $tp 计算方法 +=加 -=减 *=乘 /=除
 * @param int $val2  值2
 * @param int $decimal 小数点位数
 */
function calcs($val=0, $tp='+', $val2=0, $decimal=0){
    $X = 0;
    if($tp == '+'){
        $X = bcadd($val, $val2, $decimal);
    }elseif($tp == '-'){
        $X = bcsub($val, $val2, $decimal);
    }elseif($tp == '*'){
        $X = bcmul($val, $val2, $decimal);
    }elseif($tp == '/'){
        $X = bcdiv($val, $val2, $decimal);
    }
    $X += 0;
    return $X;
}

/**
 * 将数组中的值递归转为string类型
 * @param array $data
 * @return array|mixed|string
 */
function array_value_string($data=[]){
    if(is_array($data)){
        foreach($data as $key => $value){
            if(is_array($value)){
                $value = array_value_string($value);
            }else{
                $value = (string)$value;
            }
            $data[$key] = $value;
        }
    }elseif(is_numeric($data)){
        $data = (string)$data;
    }
    return $data;
}