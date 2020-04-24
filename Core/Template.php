<?php
/**
 * @title     前后端UI分离模板处理类 
 * @desc    cr180内部核心框架
 * @date    2019-10-10 20:58:14
 * @author  cr180 <cr180@cr180.com>
 * @version V1.0
 * @file Template.php (KSAOS底层 / UTF-8)
 */
namespace ksaOS;
if(!defined('KSAOS')) {
	exit('Error.');
}

class template {
	const _name = 'ksaOS模板处理类';
	const cacheDIR = 'data/cache/';
	private $replacecode = ['search' => [], 'replace' => []];
	private $file = '';

	static function show($tpl='',$dir='', $DirName=''){
	    if(!$dir || !$tpl){
            $sys = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);

            if(!$tpl){
                $fname = Files::name($sys[0]['file'], false);
                $tpl = $fname.'_'.$sys[1]['function'];
            }

            if(!$dir) {
                $F = $sys[0]['file'];
                if (stripos($F,'ksaos/app.php') && strtolower($tpl) == 'common/msg' && strtolower($sys[1]['function']) == 'msg' && strtolower($sys[1]['class']) == 'ksaos\app') {
                    $F = $sys[1]['file'];
                    if(strpos($F, ROOT.'ksaOS/Core/') === 0){
                        $F = PATHS.'model/.';
                    }
                }
                $dir = self::AutoTplDir($F, $tpl);
            }
        }
        return self::replace($tpl, $dir, $DirName);
    }

    /**
     * 模板文件名统一加tpl_前缀
     * @param string $tpl
     * @return array|string
     */
    public static function tplAdd($tpl=''){
	    if($tpl){
            $tpl = explode('/',$tpl);
            $tpl[] = 'tpl_'.array_pop($tpl);
            $tpl = implode('/',$tpl).'.php';
        }
        return $tpl;
    }
    /**
     * 根据自动读取的模板路径自动生成最终路径
     * @param string $dir 自动读取的路径地址(必须包含后缀名)
     * @return string
     */
    public static function AutoTplDir($P='', $tplFile=''){
	    if($P) {
            $dir = Files::dir($P);
            $dir = Files::path($dir);

            //去掉左边的绝对路径
            $dir = ltrim($dir, ROOT);
            //去掉左边的缓存路径
            $dir = ltrim($dir, self::cacheDIR.'template/');
            //去掉右边的模板缓存目录名
            $dir = substr($dir, -5) == TPLDIR ? substr($dir, 0, -5) : $dir;

            if($tplFile){
                $tplFile = self::tplAdd($tplFile);
                if(!is_file(ROOT.$dir.TPLDIR.$tplFile)){
                    $arr = explode('/', rtrim($dir,'/'));
                    $Ds = [];
                    $a = '';
                    foreach($arr as $k => $v){
                        $a .= $v.'/';
                        $Ds[] = $a;
                    }
                    $Ds = array_reverse($Ds);
                    foreach($Ds as $k => $v){
                        if(is_file(ROOT.$v.TPLDIR.$tplFile)){
                            return $v;
                        }
                    }
                }
            }


        }
	    return $dir;
    }

    /**
     * 内部引用函数 模板文件内部{template xx}
     * @param  string  $tpl
     *
     * @return string
     */
    static function subshow($tpl=''){
        $sys = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
        $dir = self::AutoTplDir($sys[0]['file'], $tpl);
        return self::replace($tpl, $dir);
    }

    /**
     * 模板引擎处理函数 自动实例化
     * 注意：渲染失败直接报错
     * @param string $tplfile 模板文件名（传入无需带tpl_前缀 但文件名必须要带前缀）
     * @param string $tplDir 自定义模板缓存目录 不传入时默认为:APP/模板目录名/
     * @param string $DirName 自定义模板目录名称 不传入默认为config配置参数TPLDIR
     * @return string 渲染成功返回渲染后的文件地址（绝对路径）
     */
    public static function replace($tplfile='',$tplDir='',$DirName='') {
        $new = new self();
        return $new->__replace($tplfile, $tplDir, $DirName);
    }


	private function __replace($tplfile='',$tplDir='',$DirName='', $returnCode=false) {

		if(!$tplfile){
			return null;
		}
        //模板目录名称处理
        $DirName = $DirName ? $DirName.'/' :TPLDIR;

        if(isset($_GET['ajax']) && in_array($tplfile,['header','footer'])){
            $tplfile = $tplfile.'_ajax';
        }
        //模板文件名统一前缀为tpl_
        $tplfile = self::tplAdd($tplfile);

		$tplDir = ($tplDir ? $tplDir : 'APP/').$DirName;


		$cachedir = Files::dir(self::cacheDIR.'template/'.$tplDir.$tplfile);
		//创建缓存目录
		Files::mkdir(ROOT.$cachedir, 0777);
		$tplName = Files::name($tplfile);

		$cachefile = $cachedir.$tplName;

		$tplfile = $tplDir.$tplfile;
        if(!is_file($tplfile)){
			throw new \Exception('模板文件不存在：'.str_replace(ROOT,'',$tplfile));
		}

		if(is_file($cachefile)){
			$file_time = filemtime($tplfile);
			//提取缓存文件最后修改时间做比对 如果缓存的修改时间与当前模板修改时间相同，则不做更新处理
			$cache_template = @file_get_contents($cachefile);
			if($cache_template) {
				preg_match_all("/<\?php\s\/\/ThisUpdateEndTime\:(\d+)/",$cache_template,$temp);
				if($temp['1']['0'] && $temp['1']['0'] == $file_time){
					return $cachefile;
				}
			}
			unset($cache_template, $temp);
			//End
		}

		$this->file = $tplfile;
		
		$Code = file_get_contents($tplfile);
		
		if($Code){
			
			$Code = preg_replace("/^[\n\r\t\s]*?<\?php exit.*?\?>/i",'',$Code);//消除开始的防盗语句
			$Code = trim($Code);
			$Code = preg_replace("/<\?php\s([\s\S]*?)\?>/i",'&lt;?php $1?&gt;',$Code);//消除代码中可能存在的PHP语法
			$Code = preg_replace("/\{tplname\}([\s\S]*?)\{\/tplname\}/is", '', $Code); //消除模板名称
			$Code = preg_replace("/\{note\}([\s\S]*?)\{\/note\}/is", '', $Code); //消除注释
			//
			//$Code = preg_replace("/([\n\r]+)\t+/s", "\\1", $Code);//压缩换行缩进符
			$Code = preg_replace("/\<\!\-\-\{(.+?)\}\-\-\>/s", "{\\1}", $Code);//将模板语法<!--{***}-->转为标准格式{***}
			$Code = preg_replace("/\{exit}/i", "<?php exit;?>", $Code);//将{exit}转为PHP结束符
			$Code = preg_replace_callback("/\{code\}([\s\S]*?)\{\/code\}/is", function($a){return $this->Codetag($a['1']);}, $Code); //处理无需编译的纯代码部分
			
			$Code = preg_replace_callback("/\{php\}([\s\S]*?)\{\/php\}/is", function($a){return $this->PHPtag($a['1']);}, $Code); //将{php}...{/php}段落语句转化为PHP代码片段
			$Code = preg_replace_callback("/\{eval\s+(.+?)\s*\}/is", function($a){return $this->PHPtag($a['1']);}, $Code); //将eval单行语句转化为PHP代码
			$Code = str_replace("{LF}", "<?=\"\\n\"?>", $Code);//换行符转化
			
			//日期格式处理{date(xxx)}
			$Code = preg_replace_callback("/\{date\((.+?)\);?\}/i", function($a){return $this->dates($a['1']);}, $Code);
			$Code = preg_replace_callback("/\{debug\((.+?)\);?\}/i", function($a){return $this->debugs($a['1']);}, $Code);
			
			$Code = preg_replace_callback("/\{template\s+([a-z0-9_:\/\.\'\"\$]+)\}/is", function($a){return $this->tpltag($a['1']);}, $Code);//转换加载语句{template xx}
		
			$Code = preg_replace("/\{(\\\$[a-zA-Z0-9_\-\>\[\]\'\"\$\.\x7f-\xff]+)\}/s", "<?=\\1?>", $Code);//变量名预转换PHP
			
			
			//多维变量相互转换整理为PHP
			$var_regexp = "((\\\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(\-\>)?[a-zA-Z0-9_\x7f-\xff]*)(\[[a-zA-Z0-9_\-\.\"\'\[\]\$\x7f-\xff]+\])*)";
			$Code = preg_replace_callback("/$var_regexp/s", function($a){return $this->Quote('<?='.$a['1'].'?>');}, $Code);
			$Code = preg_replace_callback("/(\<\?\=)?\<\?\=$var_regexp\?\>(\?\>)?/s", function($a){return $this->Quote('<?='.$a['2'].'?>');}, $Code);
			//End
			
			
			$Code = preg_replace_callback("/\{echo\s+(.+?)\}/is", function($a){return $this->Tags('<? echo '.$a['1'].'; ?>');}, $Code); //转换echo语句 {echo xx}
			//if语句处理开始
			$Code = preg_replace_callback("/\{if\s+(.+?)\}/is",  function($a){return $this->Tags('<? if('.$a['1'].') { ?>');}, $Code);
			$Code = preg_replace_callback("/\{elseif\s+(.+?)\}/is", function($a){return $this->Tags('<? } elseif('.$a['1'].') { ?>');}, $Code);
			
			$Code = preg_replace("/\{else\}/i", "<? } else { ?>", $Code);
			$Code = preg_replace("/\{\/if\}/i", "<? } ?>", $Code);
			//if End
			
			//Loop语句处理开始
			$Code = preg_replace_callback("/\{loop\s+(\S+)\s+(\S+)\}/is", function($a){return $this->Tags('<? foreach('.$a['1'].' as '.$a['2'].') { ?>');}, $Code);
			$Code = preg_replace_callback("/\{loop\s+(\S+)\s+(\S+)\s+(\S+)\}/is", function($a){return $this->Tags('<? foreach('.$a['1'].' as '.$a['2'].' => '.$a['3'].') { ?>');}, $Code);
			$Code = preg_replace("/\{\/loop\}/i", "<? } ?>", $Code);
			//Loop End
			
			//常量处理
			$Code = preg_replace("/\{([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\}/s", "<?=\\1?>", $Code);
			
			//Block模块语句处理开始
			$Code = preg_replace_callback("/\{block\s+([a-zA-Z0-9_\[\]]+)\}(.+?)\{\/block\}/is", function($a){return $this->Blocks($a['1'], $a['2']);}, $Code);
			//Block End
			$Code = '<?php //ThisUpdateEndTime:'.$file_time."\r\n".'namespace ksaOS; if(!defined(\'KSAOS\')){exit(\'Error.\');}'."\r\n".'?>'.$Code;//页头增加模板文件最后修改时间
			
			//最后整理
			$Code = preg_replace("/\<\?(\s{1})/is", "<?php\\1", $Code);
			$Code = preg_replace("/\<\?\=(.+?)\?\>/is", "<?php echo \\1;?>", $Code);
			if(!empty($this->replacecode)) {
				$Code = str_replace($this->replacecode['search'], $this->replacecode['replace'], $Code);
			}
			$Code = preg_replace("/ \?\>[\n\r]*\<\? /s", " ", $Code);
			$Code = trim($Code);
		}
		
		//模板代码写入缓存文件
		$fp = file_put_contents($cachefile, $Code);
		if($fp === false) {
			throw new \Exception('模板文件缓存失败', dirname($cachefile));
		}
		APP::hook(__CLASS__ , __FUNCTION__);
		return $cachefile;
	}
	
	private function Codetag($str){
		$i = count($this->replacecode['search']);
		$this->replacecode['search'][$i] = $search = '<!--CODE_TAG_'.$i.'-->';
		$this->replacecode['replace'][$i] = $str;
		return $search;
	}
	
	private function tpltag($str){
		$i = count($this->replacecode['search']);
		$this->replacecode['search'][$i] = $search = '<!--TEMPLATE_TAG_'.$i.'-->';
		$this->replacecode['replace'][$i] = '<?php @include template::subshow(\''.$str.'\'); ?>';
		return $search;
	}

	private function PHPtag($php) {
		$php = str_replace('\"', '"', $php);
		$i = count($this->replacecode['search']);
		$this->replacecode['search'][$i] = $search = '<!--EVAL_TAG_'.$i.'-->';
		$this->replacecode['replace'][$i] = '<?php '.$php.'?>';
		return $search;
	}


	private function Quote($var) {
		return str_replace('\"', '"', preg_replace("/\[([a-zA-Z0-9_\-\.\x7f-\xff]+)\]/s", "['\\1']", $var));
	}


	private function Tags($expr, $statement = '') {
		$expr = str_replace('\"', '"', preg_replace("/\<\?\=(\\\$.+?)\?\>/s", "\\1", $expr));
		$statement = str_replace("\\\"", "\"", $statement);
		return $expr.$statement;
	}

	private function Blocks($var, $s) {
		$s = str_replace('\\"', '"', $s);
		$s = preg_replace("/<\?=\\\$(.+?)\?>/", "{\$\\1}", $s);
		preg_match_all("/<\?=(.+?)\?>/", $s, $constary);
		$constadd = '';
		$constary[1] = array_unique($constary[1]);
		foreach($constary[1] as $const) {
			$constadd .= '$__'.$const.' = '.$const.';';
		}
		$s = preg_replace("/<\?=(.+?)\?>/", "{\$__\\1}", $s);
		$s = str_replace('?>', "\n\$$var .= <<<EOF\n", $s);
		$s = str_replace('<?', "\nEOF;\n", $s);
		return "<?\n$constadd\$$var = <<<EOF\n".$s."\nEOF;\n?>";
	}
	
	private function dates($str) {
		$str = stripslashes($str);
		$i = count($this->replacecode['search']);
		$this->replacecode['search'][$i] = $search = '<!--DATE_TAG_'.$i.'-->';
		$this->replacecode['replace'][$i] = '<?php echo times('.$str.');?>';
		return $search;
	}
	private function debugs($str) {
		$str = stripslashes($str);
		$i = count($this->replacecode['search']);
		$this->replacecode['search'][$i] = $search = '<!--DATE_TAG_'.$i.'-->';
		$this->replacecode['replace'][$i] = '<?php echo debug('.$str.');?>';
		return $search;
	}
}
