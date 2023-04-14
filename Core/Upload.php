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
		$data = APP::setting('UPLOAD/BANexts');
		if($data){
			$data = explode(',',$data);
			$data = trims($data);
			return $data;
		}
		return [];
	}
	
	/**
	 * 获取系统最大允许上传值 KB
	 * @return int
	 */
	public function MAXsize(){
		global $C;
        $maxSize = APP::setting('UPLOAD/MAXsize');
		if($maxSize){
			return intval($maxSize)  * 1024 * 1024;
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
				'path' => '', //相对路径 基于 $Mod
				'name' => '',
				'dir' => '',
				'ext' => '',
				'size' => $file['size'],
				'isPic' => 0,
				'picWidth' => 0,
				'picHeight' => 0,
                'target' => '', //绝对路径
			];
			$this->FILE['ext'] = APP::File()->ext($file['name']);
			$this->FILE['name'] = $newName.'.'.$this->FILE['ext'];
			//目录结构：/年月/日/时/文件名前两位/
			//$this->FILE['dir'] = (date('Y').date('m')).'/'.date('d').'/'.date('H').'/'. substr($newName, 0,2);
            $this->FILE['dir'] = self::get_attach_dir($Mod, $newName);
			$this->FILE['path'] = $this->FILE['dir'].'/'.$this->FILE['name'];
			$saveDir = ATTACHDIR.$Mod.'/'.$this->FILE['dir'];
   
			$this->FilePath = $saveDir.'/'.$this->FILE['name'];
            $this->FILE['target'] = $this->FilePath;
   
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
    
    /**
     * 获取一个附件储存位置
     * @param $Mod string 模块名
     * @param $fileName string 文件名
     *
     * @return string
     */
    public static function get_attach_dir($Mod='', $fileName=''){
        
        //目录结构：/年月/日/时/文件名前两位/
        $saveDir = date('Y').'/'.date('m').'/'.date('d').'/'.date('H').'/'. substr($fileName, 0,2);
        Files::mkdir(ATTACHDIR.$Mod.'/'.$saveDir);
        return $saveDir;
    }
    
    /**
     * 保存base64图片
     * @param $fileName string 文件名 不包含后缀
     * @param $Mod string 模块名
     * @param $base64Str string base64原文
     * @return array 返回图片地址（不包含模块名）
     */
    public static function SaveBase64($fileName='', $Mod='', $base64Str=''){
        //匹配出图片的格式
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64Str, $result)){
            
            $dir = self::get_attach_dir($Mod, $fileName);
            
            $ext = $result[2];
            //组合文件路径
            $file = $fileName.'.'.$ext;
            $path = $dir.'/'.$file;
            $savePath = ATTACHDIR.$Mod.'/'.$path;
            $content = base64_decode(str_replace($result[1], '', $base64Str));
            //保存图片
            if (file_put_contents($savePath, $content)){
                $picInfo = APP::File()->picInfo($savePath);
                //返回图片地址路径
                return [
                    'path' => $path,
                    'name' => $file,
                    'dir' => $dir,
                    'ext' => $ext,
                    'size' => strlen($content),
                    'width' => $picInfo['width'],
                    'height' => $picInfo['height'],
                ];
            }

        }
        return [];
    }
	
}
