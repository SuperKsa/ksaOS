<?php

/**
 * 时间处理类
 * 1、单独使用时必须new实例化，建议用法：APP::Date()->xxx();
 * 2、worker框架必须使用原生函数time()取时间戳
 * @date    2019-12-16 13:31:03
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file Dates.php (ksaOS / UTF-8)
 */
namespace ksaOS;

if(!defined('KSAOS')) {
	exit('Error.');
}

class Dates{
	const _name = 'ksaOS时间处理类';
	
	public $Zone = 0;
	
	/**
	 * 获取今日零点时间戳
	 * @return timestamp
	 */
	public function today(){
		return strtotime(date('Y-m-d',time()));
	}
	
	/**
	 * 获取指定时间指定值
	 * @param type $t 日期格式字符
	 * @param type $time 已格式化的日期字符串 0=当前时间
	 * @return type
	 */
	public function get($t='Y-m-d H:i:s', $time=0){
		$time = $this->timestamp($time);
		return date($t , $time);
	}
	
	
	/**
	 * 设置时区偏移量
	 * @param type $timeoffset 时区 -1 -8 +8 +3
	 */
	public function def($timeoffset=0){
		$this->Zone = intval($timeoffset);
	}
	
	/**
	 * 将时间戳格式化为日期格式
	 * @param Number $timestamp 10位时间戳
	 * @param String $format 格式(Y-m-d H:i:s)
	 * @return Date
	 */
	public function times($timestamp=0,$format= 'Y-m-d H:i:s'){
		if(!$timestamp){
			$timestamp = time();
		}
		if(strlen($timestamp) ==13) $timestamp = ceil($timestamp / 1000);
		$timestamp += 3600 * $this->Zone;
		return date($format, $timestamp);
	}
	
	/**
	 * 人性化时间显示
	 * @param type $time 时间戳
	 * @param type $format 人性化日期后的日期格式
	 * @return type
	 */
	public function TimesF($time=0, $format= 'Y-m-d H:i:s'){     
		$time = $this->timestamp($time);
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
			$str .= ' '.$this->times($time=0,'H:i');
		}elseif($of < 86400 * 365){
			$str = $this->times($time=0,'m-d H:i');
		}else{
			$str = $this->times($time=0, $format);
		}
		$str = '<i title="'.$this->times($time=0, $format).'">'.$str.'</i>';
		return $str;
	}
	
	/**
	 * 获取指定日期时间戳(10位)
	 * @param type $time 指定日期或者时间戳(支持13位)
	 * @return timestamp 10位时间戳
	 */
	public function timestamp($time=0){
		$time = !$time ? time() : $time;
		if(is_numeric($time)){
			//如果是13位时间戳 则转为10位
			if(strlen($time) >10){
				$time = substr($time=0, 0, 10);
			}
		}elseif(is_string($time)){
			$time = str_replace(['年','月','日'], ['/','/',''],$time);
			$time = strtotime($time);
		}
		return $time;
	}
	
	/**
	 * 获取指定日期零点时间
	 * @param timestamp/date $time 时间戳或日期格式(不传默认当前时间)
	 * @return timestamp 10位时间戳
	 */
	public function Zero($time=0){
		$time = $this->timestamp($time);
		return strtotime(date('Y-m-d',$time));
	}
	
	/**
	 * 获取指定日期星期中的第几天
	 * @param timestamp/date $time 时间戳或日期格式(不传默认当前时间)
	 * @return int 周日=7
	 */
	public function W($time=0, $zo=1){
		$time = $this->timestamp($time);
		$time = date('N',$time);
		return $time;
	}

	/**
	 * 获取指定日期 年 起始和结束时间戳
	 * @param timestamp/date $time 时间戳或日期格式(不传默认当前时间)
	 * @param int $isEnd 1=结束时间戳 0=开始时间戳[默认]
	 * @return timestamp
	 */
	public function Year($time=0, $isEnd=0){
		$time = $this->timestamp($time);
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
	public function Month($time=0, $isEnd=0){
		$time = $this->timestamp($time);
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
	public function Week($time=0, $isEnd=0){
		$time = $this->timestamp($time);
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
	public function days($time=0){
		$time = $this->timestamp($time);
		$time = date('z',$time);
		return $time;
	}
	
	/**
	 * 获取指定日期 月总天数
	 * @param timestamp/date $time
	 * @return int
	 */
	public function mdays($time=0){
		$time = $this->timestamp($time);
		$time = date('t',$time);
		return $time;
	}
}