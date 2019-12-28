<?php

/**
 * 分页处理
 * 暂无介绍
 * @date    2019-12-14 19:56:16
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file Page.php (ksaOS / UTF-8)
 */
namespace ksaOS;

if(!defined('KSAOS')) {
	exit('Error.');
}

class Page{
	const _name = 'ksaOS分页处理类';
	/**
	 * 
	 * @param int $count 数据总数
	 * @param url $url 当前URL (传入NULL则返回数组 ，否则自动追加&page=*)
	 * @param int $perpage 每页显示数量(默认10)
	 * @param int $max 最大显示页码（默认5）
	 * @param int $isjump 是否启用快速跳转(1=是[默认] 0=否）
	 * @return array/html
	 */
	public static function init($count, $url=NULL, $perpage=10, $max=5, $isjump=1){
		APP::hook(__CLASS__ , __FUNCTION__);
		$page = max(1,intval($_GET['page']));
		$count = intval($count);
		$perpage = intval($perpage);
		$max = intval($max);
		$P = ceil($count / $perpage);
		$data = [];
		if($P<2){
			return $url === NULL ? [] : '';
		}
		if($url === NULL){
			$data = [
				'count' => $count,
				'max' => $P,
				'curr' => $page,
				'perpage' => $perpage
			];
		}else{
			$url = preg_replace('/[\&\?]page=[0-9]+/i','',$url);
			$S = strpos($url, '?') === false ? '?' : '&';
			$data[] = '<a href="'.$url.'" class="page-one">首页</a>';
			if($page >1){
				$data[] = '<a href="'.$url.$S.'page='.($page-1).'" page="'.($page-1).'" class="page-prev">上一页</a>';
			}else{
				$data[] = '<a href="javascript:;" class="page-prev page-disable">上一页</a>';
			}
			if($page > $P){
				return ;
			}

			$cP = max(1,$page - ceil($max /2));
			$n = 0;
			for($i=$cP; $i<=$P; $i++){
				if($n < $max){
					if($page == $i){
						$data[] = '<span class="page-curr" page="'.$i.'">'.$i.'</span>';
					}else{
						$data[] = '<a href="'.$url.$S.'page='.$i.'" page="'.$i.'">'.$i.'</a>';
					}
				}
				$n ++;
			}
			if($page < $P){
				$data[] = '<a href="'.$url.$S.'page='.($page+1).'" page="'.($page+1).'" class="page-prev">下一页</a>';
				$data[] = '<a href="'.$url.$S.'page='.$P.'" page="'.$P.'" class="page-end">末页</a>';
			}else{
				$data[] = '<a href="javascript:;" class="page-prev page-disable">下一页</a>';
				$data[] = '<a href="javascript:;" class="page-end page-disable">末页</a>';
			}
			if($isjump){
				$data[] = '<span class="page-jump">转<input type="text" value="'.$page.'">页<i class="btn" href="'.$url.'">确认</i></span>';
			}
			$data = '<div class="page">'.implode('',$data).'</div>';
		}
		return $data;
	}
}