<?php
/**
 * 文件处理函数库文件
 * @package W2
 * @author 琐琐
 * @since 1.0
 * @version 1.0
 */

class W2File {
    /**
     * 读取文件内容,返回字符串
     * @param string $p_filePath 文件路径
     * @return sting 文件内容
     */
    public static function loadContentByFile($p_filePath){
        if(!file_exists($p_filePath) || filesize($p_filePath)==0){
            return null;
        }
        $fp = fopen($p_filePath, 'r');
        $c = fread($fp, filesize($p_filePath));
        fclose($fp);
        return $c;
    }

    /**
     * 读取文件内容中的数组
     * @param string $p_filePath 文件路径
     * @return sting 数组
     */
    public static function loadArrayByFile($p_filePath){
        return json_decode(W2File::loadContentByFile($p_filePath),true);
    }

    /**
     * 读取文件内容中的对象
     * @param string $p_filePath 文件路径
     * @return sting 对象
     */
    public static function loadObjectByFile($p_filePath){
        $o = json_decode(W2File::loadContentByFile($p_filePath),false);
        return is_array($o)?(object)$o:$o;
    }

    /**
     * 将对象或文本写入文件
     * @param string $p_filePath 文件路径
     * @param mixed $p_content 要写入的内容, 内容为对象或者数组时, 会自动转换为json格式写入
     * @param string $p_mode 文件打开方式,默认为w
     */
    public static function writeFile($p_filePath, $p_content, $p_mode='w'){
        $fp = fopen($p_filePath, $p_mode);
        fwrite($fp, (is_array($p_content)||is_object($p_content))?json_encode($p_content):$p_content);
        fclose($fp);
    }
    /**
     * 判断目标文件夹是否存在，如不存在则尝试以0777创建
     * @param string $dir 文件路径
     */
    public static function directory($dir){
        // echo $dir;
        return   is_dir ( $dir )  or  (W2File::directory(dirname( $dir ))  and  mkdir ( $dir ) );
    }

    public static function listDir($path){
        $files = [];
        if (is_dir($path))
        {
            $dh = opendir($path);
            while ($file = readdir($dh)) {
                if (substr($file,0,1)!='.') {
                    $fullpath = $path."/".$file;
                    if (!is_dir($fullpath)) {
                        $files[] = $fullpath;
                    } else {
                        $files = array_merge($files,static::listDir($fullpath));
                    }
                }
            }
        }
        else
        {
            $files[] = $path;
        }
        return $files;
    }

    /**
     * 删除目标文件夹（及其所有子文件）
     * @param  [type] $dir [description]
     * @return [type]      [description]
     */
    public static function deldir($dir) {
        //先删除目录下的文件：
        if (is_dir($dir))
        {
            $dh = opendir($dir);
            while ($file = readdir($dh)) {
                if ($file != "." && $file != "..") {
                    $fullpath = $dir."/".$file;
                    if (!is_dir($fullpath)) {
                        unlink($fullpath);
                    } else {
                        static::deldir($fullpath);
                    }
                }
            }

            closedir($dh);
            //删除当前文件夹：
            if (rmdir($dir))
            {
                return true;
            }
        }
        return false;
    }

    /** 无视大小写，获得真实路径 */
    public static function realpath($filePath,$fileDir = '')
    {
        if (file_exists($fileDir.$filePath))
        {
            return realpath($fileDir.$filePath);
        }
        $guessPath = preg_replace_callback('/([A-Za-z])/', function($matches){
                                                return '['.strtolower($matches[1]).strtoupper($matches[1]).']';
                                            }, $filePath);
        foreach (glob(AXAPI_JOB_PATH.$guessPath) as $_file) {
            return $_file;
        }
        return null;
    }

    /**
     *  输出文件供下载
     *  推荐实用Apache扩展X-Sendfile来进行文件下载处理。
     * @param  string $filePath 文件路径
     * @param  sting $fileName 文件名（下载后保存的文件名）
     * @return null           exit 并输出文件
     */
    public static function xSendFile($filePath,$fileName=null)
    {
        if (!file_exists($filePath))
        {
            throw new Exception('no file found, nothing to download.', 1);
        }
        if (is_null($fileName))
        {
            $fileName = basename($filePath);
        }
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        $ua = $_SERVER["HTTP_USER_AGENT"];
        if (preg_match('/MSIE/', $ua))
        {
            header('Content-Disposition: attachment; filename="' . rawurlencode($fileName) . '"');
        }
        elseif (preg_match("/Firefox/", $ua))
        {
            header('Content-Disposition: attachment; filename*="utf8\'\'' . $fileName . '"');
        }
        else
        {
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
        }

        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));

        if (strpos($_SERVER["SERVER_SOFTWARE"], 'Apache') !== false)
        {
            header('X-Sendfile: ' . $filePath);
        }
        elseif (strpos($_SERVER["SERVER_SOFTWARE"], 'nginx') !== false)
        {
            // 使用 nginx 服务器时，则把 文件下载交给 nginx 处理，这样效率高些
            header('X-Accel-Redirect: '. '/protected/' . $filename);
        }
        else
        {
            set_time_limit(300);  // 避免下载超时
            ob_end_clean();  // 避免大文件导致超过 memory_limit 限制
            readfile($filePath);
        }
        exit;
    }

}

/**
 * unit test
 */
/*
if(array_key_exists('argv', $GLOBALS) && realpath($argv[0]) == __file__){
    $f1 = '/Users/Wan/Project/_file-upload/aa';
    writeFile($f1, array(1,2,3,4));
    var_dump(loadContentByFile($f1));
    var_dump(loadArrayByFile($f1));
    var_dump(loadObjectByFile($f1));

    $f2 = '/Users/Wan/Project/_file-upload/bb';
    writeFile($f2, array('a'=>1,'b'=>2,'c'=>3,'d'=>4));
    var_dump(loadContentByFile($f2));
    var_dump(loadArrayByFile($f2));
    var_dump(loadObjectByFile($f2));
}
*/

?>
