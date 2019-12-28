<?php

/**
 * 文件上传类
 * 必须初始化类
 * @date    2019-12-6 21:01:58
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file Upload.php (ksaOS / UTF-8)
 */
namespace ksaOS;

if(!defined('KSAOS')) {
	exit('Error.');
}

class Upload {
	const _name = 'ksaOS上传处理类';
	public $FILE = [];
	public $FilePath = '';
	public $TempPath = '';
	
	public function BANexts(){
		global $C;
		$data = $C['setting']['Upload_BANexts'];
		if($data){
			$data = explode(',',$data);
			$data = trims($data);
			return $data;
		}
		return [];
	}
	
	/**
	 * 获取系统最大允许上传值 KB
	 * @global type $C
	 * @return int
	 */
	public function MAXsize(){
		global $C;
		if($C['setting']['Upload_MAXsize']){
			return intval($C['setting']['Upload_MAXsize'])  * 1024 * 1024;
		}
		return 0;
	}
	
	public function Save($Mod='',$file=''){
		if(!$file || !APP::File()->dirName($Mod)){
			return -1;
		}
		if($file['error']){
			$return = $file['error'];
		}elseif($file['size'] <1){
			$return = -1;
		}else{
			$this->TmpPath = $file['tmp_name'];
			$newName = md5(microtime(true).$file['tmp_name']);
			$this->FILE = [
				'fileName' => APP::File()->name($file['name']),
				'path' => '',
				'name' => '',
				'dir' => '',
				'ext' => '',
				'size' => $file['size'],
				'isPic' => 0,
				'picWidth' => 0,
				'picHeight' => 0
			];
			$this->FILE['ext'] = APP::File()->ext($file['name']);
			$this->FILE['name'] = $newName.'.'.$this->FILE['ext'];
			//目录结构：/年月/日/时/文件名前两位/
			$this->FILE['dir'] = '/'.(DATE_YEAR.DATE_MONTH).'/'.DATE_DAY.'/'.DATE_HOUR.'/'. substr($newName, 0,2).'/';
			$this->FILE['path'] = $this->FILE['dir'].$this->FILE['name'];
			$saveDir = ROOT.'data/attach/'.$Mod.'/'.$this->FILE['dir'];
			$this->FilePath = $saveDir.$this->FILE['name'];
			
			
			$picInfo = APP::File()->picInfo($this->TmpPath);
			$this->FILE['isPic'] = $picInfo['width'] ? 1 : 0;
			$this->FILE['picWidth'] = $picInfo['width'];
			$this->FILE['picHeight'] = $picInfo['height'];
			$BANexts = $this->BANexts();
			$MaxSize = $this->MAXsize();
			if($BANexts && in_array($this->FILE['ext'],$BANexts)){
				$return = -2; //文件后缀被禁止
			}elseif($MaxSize>0 && $this->FILE['size'] >$MaxSize){
				$return = -3; //文件大小超出限制
			}else{
				APP::File()->mkdir($saveDir);
				$success = false;
				if(@copy($this->TmpPath, $this->FilePath)) {
					$success = true;
				}elseif(function_exists('move_uploaded_file') && @move_uploaded_file($this->TmpPath, $this->FilePath)) {
					$success = true;
				}elseif (@is_readable($this->TmpPath) && (@$fp_s = fopen($this->TmpPath, 'rb')) && (@$fp_t = fopen($this->FilePath, 'wb'))) {
					while (!feof($fp_s)) {
						$s = @fread($fp_s, 1024 * 512);
						@fwrite($fp_t, $s);
					}
					fclose($fp_s); fclose($fp_t);
					$success = true;
				}
				if($success)  {
					@chmod($this->FilePath, 0644); //设置文件属性为可读写但没有执行权限
					$return = $this->FILE;
				}
			}
		}
		@unlink($this->TmpPath);
		APP::hook(__CLASS__ , __FUNCTION__);
		return $return;
	}
	
}
