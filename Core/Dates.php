<?php

/**
 * 时间处理类
 *worker框架必须使用原生函数time()取时间戳
 * @date    2019-12-16 13:31:03
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file Dates.php (ksaOS / UTF-8)
 */
namespace ksaOS;

if(!defined('KSAOS')) {
	exit('Error.');
}
//直接初始化当前时区值
Dates::zone();
class Dates{
	const _name = 'ksaOS时间处理类';
	
	/**
	 * 设置/获取当前时区的数值
	 * @staticvar type $zone
	 * @return type
	 */
	public static function zone($var=NULL){
		static $zone = NULL;
		if($var !== NULL){
			$zone = $var;
		}
		if($zone === NULL){
			$zone = timezone_offset_get(new \DateTimeZone(date_default_timezone_get()), new \DateTime()) / 3600;
		}
		return $zone;
	}

	/**
	 * 获取今日零点时间戳
	 * @return timestamp
	 */
	public static function today(){
		return strtotime(date('Y-m-d',time()));
	}
	
	/**
	 * 获取指定时间指定值
	 * @param type $t 日期格式字符
	 * @param type $time 已格式化的日期字符串 0=当前时间
	 * @return type
	 */
	public static function get($t='Y-m-d H:i:s', $time=0){
		$time = self::timestamp($time);
		return date($t , $time);
	}
	
	/**
	 * 日期转格林威治日期
	 * @param type $date 需要转换的日期 不传入则默认为当前时间
	 * @param type $F 日期格式
	 * @return date
	 */
	public static function timeUTC($date=NULL, $F='Y-m-d H:i:s'){
		$date = self::timestamp($date);
		return $date >0 ? gmdate($F, $date) : '';
	}
	
	public static function UTCtime($date=NULL, $F='Y-m-d H:i:s'){
		$date = self::timestamp($date);
		return $date >0 ? self::times($date, $F) : '';
	}
	
	/**
	 * 将时间戳格式化为日期格式
	 * @param type $timestamp UTC时间戳（支持毫/微秒级 默认当前10位时间戳）
	 * @param type $format 日期格式
	 * @param type $isUTC 是否需要返回UTC时间
	 * @return date
	 */
	public static function times($timestamp=0,$format= 'Y-m-d H:i:s', $isUTC=0){
		if($timestamp ===0){
			$timestamp = time();
		}elseif(is_null($timestamp)){
		    return '';
        }

		$s = '';
		$strlen = strlen($timestamp);
		//时间戳存在毫/微秒级处理
		if(strpos($timestamp,'.') !== false){
			list($timestamp, $s) = explode('.',$timestamp);
		}elseif(is_numeric($timestamp) && $strlen>10){
			$n = pow(10, $strlen-10);
			$s = $timestamp % $n;
			$timestamp = (int)substr($timestamp,0,10);
		}
		//如果需要UTC时间 则减去时区偏差
		if(!$isUTC){
			$timestamp += 3600 * self::zone();
		}
		$date = gmdate($format, $timestamp);
		//如果日期格式中存在秒单位则跟随输出毫秒
		if(strpos(strtolower($format), 's')){
			$date .= $s ? '.'.$s : '';
		}
		return $date;
	}
	
	/**
	 * 人性化时间显示
	 * @param type $time 时间戳
	 * @param type $format 人性化日期后的日期格式
	 * @return type
	 */
	public static function TimesF($time=0, $format= 'Y-m-d H:i:s'){     
		$time = self::timestamp($time);
		$of = time() - $time;
		if ($of <60){
			$str = '刚刚';
		}elseif($of < 60 * 60){
			$min = floor($of/60);
			$str = $min.'分钟前';
		}elseif($of < 86400){
			$str = floor($of / 3600).'小时前 ';
		}elseif($of < 86400 * 3){
			$d = floor($of / 86400);
			if($d==1){
				$str = '昨天';
			}elseif($d>1){
				$str = '前天';
			}
			$str .= ' '.self::times($time=0,'H:i');
		}elseif($of < 86400 * 365){
			$str = self::times($time=0,'m-d H:i');
		}else{
			$str = self::times($time=0, $format);
		}
		$str = '<i title="'.self::times($time=0, $format).'">'.$str.'</i>';
		return $str;
	}
	
	/**
	 * 获取当前UTC时间戳
	 * @param type $n 位数
	 * @return timestamp UTC时间戳 不足$n位则补0
	 */
	public static function mtime($n=13){
		list($use,$time) = explode(" ",microtime());
		list($tmp, $use) = explode('.',$use);
		$time .= $use;
		$strlen = strlen($time);
		//大于N位 截取
		if($strlen >$n){
			$time = substr($time, 0, $n);
		//不足N位 补0
		}else{
			$time = str_pad($time, $n, 0, STR_PAD_RIGHT);
		}
		return $time;
	}
	
	/**
	 * 获取指定日期时间戳
	 * @param type $time 指定日期或者时间戳(默认当前时间) 如该值小于20则处理为第二个参数值
	 * @param type $F 时间戳位数
	 * @return timestamp UTC时间戳 不足$F位则补0
	 */
	public static function timestamp($time=NULL, $F=10){
		//如果日期不存在或者为第二个参数值
		if(!$time || $time>0 && $time <20){
			$F = $time >0 ? $time : $F;
			$time = self::mtime($F);
		//如果传入的是日期格式
		}elseif(!is_numeric($time) && is_string($time)){
			$time = str_replace(['年','月','日'], ['/','/',''],$time);
			$ms = '';
			if(strpos($time,'.') !== false){
				list($time,$ms) = explode('.',$time);
			}
			$time = strtotime($time.' UTC').$ms;
		}
		if($time >0){
			$strlen = strlen($time);
			//大于N位 截取
			if($strlen >$F){
				$time = substr($time, 0, $F);
			//不足N位 补0
			}elseif($strlen < $F){
				$time = str_pad($time,$F,0,STR_PAD_RIGHT);
			}
		}else{
			$time = 0;
		}
		return $time;
	}
	
	/**
	 * 获取指定日期零点时间
	 * @param timestamp/date $time 时间戳或日期格式(不传默认当前时间)
	 * @return timestamp 10位时间戳
	 */
	public static function Zero($time=0){
		$time = self::timestamp($time);
		return strtotime(date('Y-m-d',$time));
	}
	
	/**
	 * 获取指定日期星期中的第几天
	 * @param timestamp/date $time 时间戳或日期格式(不传默认当前时间)
	 * @return int 周日=7
	 */
	public static function W($time=0, $zo=1){
		$time = self::timestamp($time);
		$time = date('N',$time);
		return $time;
	}

	/**
	 * 获取指定日期 年 起始和结束时间戳
	 * @param timestamp/date $time 时间戳或日期格式(不传默认当前时间)
	 * @param int $isEnd 1=结束时间戳 0=开始时间戳[默认]
	 * @return timestamp
	 */
	public static function Year($time=0, $isEnd=0){
		$time = self::timestamp($time);
		if($isEnd){
			return strtotime(date('Y-12-31',$time))+86400-1;
		}else{
			return strtotime(date('Y-01-01',$time));
		}
		
	}
	
	/**
	 * 获取指定日期 月 起始和结束时间戳
	 * @param timestamp/date $time 时间戳或日期格式(不传默认当前时间)
	 * @param int $isEnd 1=结束时间戳 0=开始时间戳[默认]
	 * @return timestamp
	 */
	public static function Month($time=0, $isEnd=0){
		$time = self::timestamp($time);
		if($isEnd){
			return strtotime(date('Y-m-'.date('t'),$time))+86400-1;
		}else{
			return strtotime(date('Y-m-01',$time));
		}
	}
	
	/**
	 * 获取指定日期 周 起始和结束时间戳
	 * @param timestamp/date $time 时间戳或日期格式(不传默认当前时间)
	 * @param int $isEnd 1=结束时间戳 0=开始时间戳[默认]
	 * @return timestamp
	 */
	public static function Week($time=0, $isEnd=0){
		$time = self::timestamp($time);
		$w = date('N', $time); //今天是周几
		if($isEnd){
			$time += (7-$w) * 86400;
			return strtotime(date('Y-m-d',$time))+86400-1;
		}else{
			$w --;
			$time -= $w * 86400;
			return strtotime(date('Y-m-d',$time));
		}
	}
	
	/**
	 * 获取指定日期 是一年中的第几天
	 * @param timestamp/date $time
	 * @return int
	 */
	public static function days($time=0){
		$time = self::timestamp($time);
		$time = date('z',$time);
		return $time;
	}
	
	/**
	 * 获取指定日期 月总天数
	 * @param timestamp/date $time
	 * @return int
	 */
	public static function mdays($time=0){
		$time = self::timestamp($time);
		$time = date('t',$time);
		return $time;
	}
}