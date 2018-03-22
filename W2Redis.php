<?php
/**
 * redis处理库文件
 * @package W2
 * @author axing
 * @since 1.0
 * @version 1.0
 */

class W2Redis {

    public static $CACHE_HOST  = null;  //服务器
    public static $CACHE_PORT  = null;  //端口
    public static $CACHE_INDEX = null;  //数据库索引，一般是0-20
    public static $CACHE_AUTH = null;   //密码，如果需要的话。

    public static $_ax_connect = null;                                        //缓存连接唯一实例

    public static $requestCacheKeys    = array();                           //记录本次请求中读取过的缓存key。



    /** 缓存工厂，获得缓存连接。 */
    public static function memFactory(){
        if (static::$_ax_connect===null && class_exists('Redis') ) {
            if (static::$CACHE_HOST==null && defined('W2CACHE_HOST'))
            {
                static::$CACHE_HOST    = W2CACHE_HOST;
                static::$CACHE_PORT    = W2CACHE_PORT;
                static::$CACHE_INDEX   = W2CACHE_INDEX;
                static::$CACHE_AUTH    = W2CACHE_AUTH;
            }
            if (static::$CACHE_HOST!=null)
            {
                static::$_ax_connect = new Redis();
                $_status = static::$_ax_connect->connect(static::$CACHE_HOST,static::$CACHE_PORT,1);
                if ($_status)
                {
                    if (!is_null(static::$CACHE_AUTH))
                    {
                        $authResult = static::$_ax_connect->auth(static::$CACHE_AUTH);
                        if ($authResult!='OK')
                        {
                            static::$_ax_connect = false;
                            throw new Exception("缓存服务器授权失败，请管理员检查授权设定是否正确。");
                        }
                    }
                    static::$_ax_connect->select(static::$CACHE_INDEX);
                }
                else
                {
                    static::$_ax_connect = false;
                }
            }
        }
        if (static::$_ax_connect===false)
        {
            return null;
        }
        return static::$_ax_connect;
    }



    /**
     * 最强搭档，更新缓存到指定Key，如果有必要
     * @param  string  $p_key                   缓存key
     * @param  [type] $buffer                  [description]
     * @return null
     */
    public static function setCache($p_key,$buffer,$p_expire=3600){
        $memcached = static::memFactory();
        if (isset($memcached, $p_key)) {
            static::$requestCacheKeys[]=$p_key;//记录本次请求中取过的key
            $_time = time();
            if ($p_expire>0)
            {
                $memcached -> SETEX($p_key.'_data',$p_expire,$buffer);
                $memcached -> SETEX($p_key.'_time',$p_expire,$_time);
            }
            $memcached -> del($p_key.'_timelock');//更新缓存时，删除缓存更新的锁
        }
    }

    /**
     * 最强方法，根据key值获得缓存内容
     * @param  string  $p_key                   缓存key
     * @param  integer $p_timeout               过期时间
     * @return string                           缓存内容或null或304 Not Modified
     */
    public static function getCache($p_key,$p_timeout=300)
    {
        $memcached = static::memFactory();
        if (isset($memcached, $p_key)) {
            if (static::isCacheCanBeUsed($p_key,$p_timeout))
            {
                static::$requestCacheKeys[]=$p_key;//记录本次请求中可用的取出的key
                //有可用的缓存，取出缓存给ta就是。
                $_data = $_time = $memcached->get($p_key.'_data');
                if ($_data!==false)
                {
                    return $_data;
                }
            }
        }
        return null;
    }


    /**
     * 存储实例，实则是将实例转化成字符串后存储缓存
     * @param  string  $p_key                   缓存key
     * @param  object $p_obj               目标实例
     * @return
     */
    public static function setObj($p_key,$p_obj,$p_expire=3600){
        static::setCache($p_key,serialize($p_obj),$p_expire);
    }

    /**
     * 读取实例，实则是读取缓存的变身，只是读取出字符串后，编译成php对象
     * @param  string  $p_key                   缓存key
     * @param  integer $p_timeout               过期时间
     * @return object
     */
    public static function getObj($p_key,$p_timeout=300)
    {
        $_data = static::getCache($p_key,$p_timeout);
        if ($_data!==false && $_data!==null)
        {
            return unserialize($_data);
        }
        return null;
    }

    /**
     * 将key中储存的数字值增一。
     * 如果key不存在，以0为key的初始值，然后执行INCR操作。
     * 如果值包含错误的类型，或字符串类型的值不能表示为数字，那么返回一个错误。
     * @param  string $p_key
     * @param  int   $increment  增值，默认1
     * @return int   执行INCR命令之后key的值。
     */
    public static function incr($p_key,$increment=1,$p_expire=3600)
    {
        $memcached = static::memFactory();
        if (isset($memcached, $p_key)) {
            if ($p_expire>0)
            {
                $memcached -> EXPIRE( $p_key.'_incr', $p_expire );
            }
            return $memcached -> incrby($p_key.'_incr',$increment);
        }
        return false;
    }

    /** 在指定缓存池增加缓存key，所谓缓存池，其实是一个特殊数据的存储，其内容是N个缓存key，所以称之为池。其主要用于多个缓存共同触发更新。*/
    public static function addToCacheKeyPool($p_keyPool,$p_key,$p_expire=3600)
    {
        $memcached = static::memFactory();
        if (isset($memcached,$p_keyPool, $p_key)) {
            $memcached -> lpush( $p_keyPool.'_keypool', $p_key );
            if ($p_expire>0)
            {
                $memcached -> EXPIRE( $p_keyPool.'_keypool', $p_expire );
            }
        }
    }


    /** 重置指定缓存池里的所有key，如上所说，缓存池就是用来重置的。此处重置。一般是这样，我们将某个列表的缓存放入列表中各元素独立的缓存池里，一旦某个元素更新，就主动重置其对应的独立缓存池，就可以达到列表类缓存的实时更新了。 */
    public static function resetCacheKeyPool($p_keyPool)
    {
        $memcached = static::memFactory();
        if (isset($memcached, $p_keyPool)) {
            $keysList = $memcached -> lGetRange( $p_keyPool.'_keypool',0,-1);
            foreach ($keysList as $_key => $p_key) {
                static::resetCache($p_key);
            }
            $memcached -> del( $p_keyPool.'_keypool');
        }
        return null;
    }

    /** 重置缓存 所谓重置缓存，就是设_timelock为1，并不是真的清理缓存，只有当下次下个用户请求对应的缓存数据的时候，才会覆盖更新缓存。（注意，是覆盖更新，如果有并发读取的情况，旧的缓存仍然会被用到哦）*/
    public static function resetCache($p_key)
    {
        $memcached = static::memFactory();
        if (isset($memcached, $p_key)) {
            $memcached -> SETEX( $p_key.'_timelock',600, 1 );
        }
    }

    /**
     * 删除缓存（慎用）
     * @param  string  $p_key                   缓存key
     * @return null
     */
    public static function delCache($p_key){
        $memcached = static::memFactory();
        if (isset($memcached, $p_key)) {
            $_time = time();
            $memcached -> del($p_key.'_data');
            $memcached -> del($p_key.'_time');
            $memcached -> del($p_key.'_timelock');
        }
    }

    /**
     * 是否有可用的缓存
     * @param  string  $p_key                   缓存key
     * @param  integer $p_timeout               过期时间
     * @param  bool    $lockIfTimeout              如果过期是否加更新锁（加更新锁后，120秒内其他人仍用旧缓存）
     * @return boolean                          是，否
     */
    public static function isCacheCanBeUsed($p_key,$p_timeout=300,$lockIfTimeout=true)
    {
        $memcached = static::memFactory();
        if (isset($memcached, $p_key)) {
            $_time = $memcached->get($p_key.'_time');
            if ($_time!==false){
                $_timelock = $memcached->get($p_key.'_timelock');

                $now = time();

                //判断已有缓存是否可用。有锁锁状态过期 或 无锁缓存过期，则不可用已有缓存。
                if (
                       ($_timelock!==false && $now > $_timelock)               //有锁，且时间锁已过期（一般是时间锁被重置为1了）
                    || ($_timelock===false && $now - $_time > $p_timeout)           //没有锁，且缓存超时
                    // || (isset($_GET['reloadcache'])&& $_GET['reloadcache']=="true")    //强制reload缓存
                    )
                {//不管
                        if ($lockIfTimeout)
                        {
                            $memcached -> SETEX( $p_key.'_timelock',600, time()+120);//设定新的缓存锁，此位用户负责生成新的缓存，如果缓存失败，则两分钟后有人会重新触发。
                        }
                        AX_DEBUG('缓存失效：'.$p_key);
                        return false;//return false的意思是说，你得请重新请求数据。
                }

                return true;//这个key有旧缓存可用，而且没过期哦，你去取吧。
            }
        }
        return false;//没有可用的缓存，请重新生成吧
    }

    /** 重置所有缓存，慎用。 */
    public static function emptyCache()
    {
        // W2Log::debug($p_keyPool);
        $memcached = static::memFactory();
        if (isset($memcached))
        {
            // $memcached -> FLUSHALL();
            return $memcached -> FLUSHDB();//清空当前数据库
        }
        return false;
    }
    /**
     * 重置 INFO 命令中的某些统计数据，包括：
     *  Keyspace hits (键空间命中次数)
     *  Keyspace misses (键空间不命中次数)
     *  Number of commands processed (执行命令的次数)
     *  Number of connections received (连接服务器的次数)
     *  Number of expired keys (过期key的数量)
     * @return 总是返回 OK 。
     */
    public static function resetStat()
    {
        $memcached = static::memFactory();
        if (isset($memcached)) {
            return $memcached->resetStat();
        }
        return null;
    }

    /** 缓存服务器状态 */
    public static function info()
    {
        $memcached = static::memFactory();
        if (isset($memcached)) {
            return $memcached->INFO();
        }
        return null;
    }


    /*---------单进程缓存池逻辑处理----------------*/
    /**
     *  记录本次请求过程中用过的缓存key
     * @param  integer $p_expire 过期时间
     * @return string $etag 为本次请求进程生成特征码
     */
    public static function etagOfRequest($p_expire=500)
    {
        $etag = null;
        $uniqueList = array_unique(static::$requestCacheKeys);
        if (count($uniqueList) > 0)
        {
            sort($uniqueList, SORT_STRING);
            $buffer = serialize($uniqueList);
            $etag = md5($buffer);
            $p_key = 'etag_' . $etag;
            static::setCache($p_key,$buffer,$p_expire);
        }
        return $etag;
    }

    // 判断特征码对应缓存列表是否有效，任一缓存key失效，则该特征码不可用
    public static function isEtagCanBeUsed($etag,$p_expire=500)
    {
        $p_key = 'etag_' . $etag;
        $requestCacheKeys = static::getObj($p_key);
        if (is_array($requestCacheKeys))
        {
            foreach ($requestCacheKeys as $cacheKey) {
                if (!static::isCacheCanBeUsed($cacheKey,$p_expire,false))
                {
                    AX_DEBUG('etag缓存失效：'.$cacheKey);
                    static::delCache($p_key);
                    //删除的key不算当前进程使用key
                    $index = array_search($p_key,static::$requestCacheKeys);
                    if ($index>=0)
                    {
                        array_splice(static::$requestCacheKeys, $index, 1);
                    }
                    return false;
                }
            }
            return true;
        }
        return false;
    }


    // public static function get

}
