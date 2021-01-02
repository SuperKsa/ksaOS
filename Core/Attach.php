<?php

/**
 * 附件处理类
 * 暂无介绍
 * @date    2019-12-15 17:55:41
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file Attach.php (ksaOS / UTF-8)
 */
namespace ksaOS;

if(!defined('KSAOS')) {
	exit('Error.');
}

class Attach{
	const _name = 'ksaOS附件处理';
	/**
	 * 附件idtype校验
	 * @param string $idtype
	 * @return string
	 */
	public static function idtype($idtype){
        $idtype = trim($idtype);
        return $idtype;
	}
	
	/**
	 * 生成附件分表ID
	 * @param string $idtype
	 * @param int $id
	 * @return int
	 */
	public static function tableID($idtype, $id){
        $id = intval($id);
        $id = substr($id,-1); //取最后一位作为分表ID
	    return $id;
	}

    /**
     * 上传文件到临时目录中
     * @param $files
     * @return array
     */
	public static function tmpUpload($FileData){
        global $C;
        if($FileData && $C['uid']) {
            $idtype = 'temp';
            $Uploads = [];
            //一律作为多个文件处理
            if (is_array($FileData['name'])) {
                foreach ($FileData['name'] as $key => $value) {
                    $Uploads[] = [
                        'name' => $value,
                        'type' => $FileData['type'][$key],
                        'tmp_name' => $FileData['tmp_name'][$key],
                        'error' => $FileData['error'][$key],
                        'size' => $FileData['size'][$key],
                    ];
                }
            } else {
                $Uploads[] = $FileData;
            }

            $data = [];
            foreach ($Uploads as $key => $value) {
                $files = APP::Upload($idtype, $value);
                if ($files && is_array($files) && $files['size'] > 0) {
                    $fdt = [
                        'uid' => $C['uid'],
                        'name' => $files['fileName'],
                        'size' => $files['size'],
                        'isPic' => $files['isPic'] ? 1 : 0,
                        'ext' => $files['ext'],
                        'src' => $files['path'],
                        'width' => $files['picWidth'],
                        'height' => $files['picHeight'],
                        'dateline' => time()
                    ];
                    $fdt['aid'] = DB('attach_temp')->insert($fdt, true);
                    $fdt['src'] = APP::Attach()->Url($idtype, $fdt['src']);
                    $data[] = $fdt;
                }
            }
        }
        return $data;
    }

    /**
     * 返回临时图片DB数据
     * @param int $aid
     * @param int $uid
     * @return mixed
     */
	public static function tmpData($aid=0, $uid=0){
	    $where = ['int:aid'=>$aid];
	    if($uid){
            $where['int:uid'] = $uid;
        }
        return DB('attach_temp')->where($where)->fetch_first();
    }

    /**
     * 临时图片转正式图片
     * @param string $aid 临时图片aid （单个）
     * @param string $idtype 模块标识 （可选）
     * @param string $id 对应数据ID
     * @param int $uid 上传者uid
     * @return array|mixed 成功返回array
     */
	public static function tmp2off($aid='', $idtype='', $id='', $uid=0){
		$aid = intval($aid);
		$idtype = self::idtype($idtype);
		APP::hook(__CLASS__ , __FUNCTION__);
		if($idtype && $aid >0){
			$data = self::tmpData($aid, $uid);
			if($data){
				$tempFile = ROOT.self::Path('temp', $data['src']);
				$newFile = ROOT.self::Path($idtype, $data['src']);
				Files::mkdir(dirname($newFile));
				if(copy($tempFile, $newFile)){
					$tableID = self::tableID($idtype, $id);
					$data['idtype'] = $idtype;
					$data['id'] = $id;
					$data['aid'] = DB('attach')->insert([
					    'tableID' => $tableID,
						'idtype'=>$idtype,
						'id' => $id
					],true);
					if($data['aid'] > 0){
						DB('attach_'.$tableID)->insert($data);
					}
					self::del('temp', $aid, $id);
					return $data;
				}
			}
		}
		return [];
	}
	
	/**
	 * 删除附件
	 * @param int $aid 附件ID (支持多个)
	 * @param string $idtype 模块标识 （可选）
	 * @param int $id 模块ID （可选 支持多个 必须存在$idtype）
	 * @param int $isTmp 是否为临时附件 1=是[默认] 0=否
	 */
	public static function del($idtype, $aid=0, $id=0){
		$aid = ints($aid,1);
		$id = ints($id,1);
		$idtype = self::idtype($idtype);
        $isTmp = $idtype == 'temp';
		APP::hook(__CLASS__ , __FUNCTION__);
		if($aid || $id){
			$where = [];
			if($aid){
				$where = ['aid'=>$aid];
			}
			if($idtype){
				$where['idtype'] = $idtype;
				if($id >0){
					$where['id'] = $id;
				}
			}
			if(!$where){
				return false;
			}
            $delCount = 0;
			if($isTmp){
				foreach(DB('attach_temp')->where('aid',$aid)->fetch_all() as $value){
					if(self::__delFile($idtype, $value['src'], $value['syndate'])){
                        DB('attach_temp')->where('aid',$value['aid'])->delete();
                        $delCount ++;
                    }
				}
				return $delCount;
			}else{
				$tableDt = [];
				foreach(DB('attach')->where($where)->fetch_all() as $value){
					$tableDt[$value['tableID']][$value['aid']] = $value['aid'];
				}
				foreach($tableDt as $tbID => $aids){
					foreach(DB('attach_'.$tbID)->where('aid',$aids)->fetch_all() as $value){
						if(self::__delFile($idtype, $value['src'], $value['syndate'])){
                            DB('attach_'.$tbID)->where('aid', $value['aid'])->delete();
                            $delCount ++;
                        }
					}
				}
				if($delCount && $tableDt){
					DB('attach')->where($where)->delete();
				}
				return $delCount;
			}
		}
		return false;
	}
	
	/**
	 * 直接删除文件函数
	 * @param string $idtype
	 * @param string $src
	 * @param int $isSyn
	 * @return string
	 */
	public static function __delFile($idtype, $src, $isSyn=0){
		$path = self::Path($idtype, $src);
		if($path){
			//删除远程附件
			if($isSyn){

			//删除本地附件
			}elseif(is_file(ROOT.$path)){
				return @unlink(ROOT.$path);
			}
		}
	}
	
	/**
	 * 输出附件存放相对路径
	 * @param string $md
	 * @param string $file
	 * @return string
	 */
	public static function Path($md='',$file=''){
		$md = self::idtype($md);
		if($md && $file){
            return 'data/attach/'.$md.'/'.$file;
		}
	}

	/**
	 * 输出图片访问地址 用于访问
	 * @param string $md
	 * @param string $file
	 * @return string
	 */
	public static function Url($md='',$file=''){
		global $C;
		$md = self::idtype($md);
		if($md && $file){
		    if(strpos($file,'http:') ===0 || strpos($file,'https:') ===0){
		        return $file;
            }
			return $C['picurl'].self::Path($md, $file);
		}
		return '';
	}
}