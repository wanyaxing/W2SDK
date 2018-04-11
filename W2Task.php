<?php
/**
 * 异步类，本类中的方法是异步调用的哦。
 * 所有的protected权限下的静态方法都会被异步调用。
 * 开发者可以在自己项目中继承此类并添加更多的static方法。
 * 注意：在方法中，尽量避免使用全局变量，特殊类型的参数（只有可被serialize的变量才可使用作为参数）
 * 建议使用DBTool访问以下数据表配合使用
CREATE TABLE `w2task` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `taskClass` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '类名称',
  `taskMethod` varchar(140) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '类方法',
  `taskArgs` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '类参数',
  `taskArg0` varchar(140) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '类参数0',
  `taskArg1` varchar(140) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '类参数1',
  `taskTime` datetime NOT NULL COMMENT '预期执行时间',
  `taskResult` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '类结果',
  `successTime` datetime DEFAULT NULL COMMENT '成功时间',
  `taskStatus` tinyint(4) unsigned NOT NULL DEFAULT '1' COMMENT '1: 未执行 2: 心跳中 3：执行出错 5：执行中 8：有结果但失败 9：有结果且成功',
  `heartbeatCode` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '心跳唯一码（防止并发执行）',
  `heartbeatCount` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '心跳次数',
  `lastHeartbeatTime` datetime DEFAULT NULL COMMENT '最后一次心跳时间',
  `failCount` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '失败次数',
  `lastFailTime` datetime DEFAULT NULL COMMENT '最后一次失败时间',
  `isValid` tinyint(4) unsigned NOT NULL DEFAULT '1' COMMENT '是否可用1可用 0已删除',
  `createTime` datetime NOT NULL COMMENT '创建时间',
  `modifyTime` datetime NOT NULL COMMENT '修改时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='任务队列';
 * @package W2
 * @author axing
 * @since 1.0
 * @version 1.0
 */


class W2Task
{
    public static $TASK_DATA_RANDCODE          = 'D4F1qXFfnT6kzPaTIJMFYRxAwG2Oibud';
    public static $HEARTBEAT_INTERVAL = 30;//任务等待执行期间的心跳间隔
    public static $taskTime = null;

    /* 魔术方法，捕捉所有protected的方法 */
    public static function __callStatic($method, $args)
    {
        $reflection = new ReflectionMethod(get_called_class(), $method);
        if ($reflection->isProtected())
        {
            return static::taskSave(get_called_class(),$method,$args);
        }
        else
        {
            var_dump($reflection);
            throw new Exception("Error method Request", 1);
        }
    }
    // 将不存在的实例方法也转化成静态方法
    public function __call($method, $args)
    {
        return static::__callStatic($method, $args);
    }

    /*指定任务时间延迟N秒后执行*/
    public static function delay($second=0)
    {
        $static = new static();
        $static::$taskTime = W2Time::getTimeAdded(null,$second);
        return $static;
    }


    // 尝试将任务保存起来
    public static function taskSave($class,$method,$args)
    {
        try {
            $data = [
                'taskClass'=>$class,
                'taskMethod'=>$method,
                'taskArgs'=>serialize($args),
                'taskArg0'=>count($args)>=1?serialize($args[0]):null,
                'taskArg1'=>count($args)>=2?serialize($args[1]):null,
                'taskTime'=>W2Time::timetostr(static::$taskTime),
                'createTime'=>date('Y-m-d H:i:s'),
                'modifyTime'=>date('Y-m-d H:i:s'),
                ];
            $dbFac = DBModel::instance('w2task');
            $dbFac->insert($data);
            $taskID = $dbFac->getLastInsertId();
            if (static::$taskTime - time() < static::$HEARTBEAT_INTERVAL*2)
            {//小于心跳时间的任务需要直接发起异步任务，因为脚本轮询的时间通常是1分钟。
                static::taskPush($class,$method,$args,static::$taskTime,$taskID);
            }
        } catch (Exception $e) {
            static::taskPush($class,$method,$args,static::$taskTime);
        }
        static::$taskTime = null;//每次保存或执行任务后当前静态环境的taskTime清空，以便发起新的任务
    }

    // 尝试发起未完成的任务
    public static function taskUncompleteCheck()
    {
        // 取出未完成且预期时间在两分钟内且该任务的最后心跳时间已经过期（无心跳）的任务.
        $tasks = DBModel::instance('w2task')->where(['taskStatus <= 3','taskTime <= \''.W2Time::timetostr(W2Time::getTimeAdded(null,60 * 3)).'\'','(lastHeartbeatTime is null or lastHeartbeatTime < \''.W2Time::timetostr(W2Time::getTimeAdded(null,0 - static::$HEARTBEAT_INTERVAL)).'\')'])->select();
        // 发起执行这些任务
        foreach ($tasks as $task) {
            static::taskPush($task['taskClass'],$task['taskMethod'],unserialize($task['taskArgs']),$task['taskTime'],$task['id']);
        }
        return count($tasks);
    }


    /*提交一个异步请求，此处使用的是curl请求头的方案，即发起一个网络连接，然后迅速断开，由目标在连接断开后继续执行，达到异步执行的效果。*/
    public static function taskPush($class,$method,$args,$taskTime=null,$taskID=null)
    {
        $data           = serialize(array($class,$method,$args,$taskTime,$taskID));
        $hexData        = W2String::asc2hex(W2String::rc4(TASK_DATA_RANDCODE,$data));
        $params['data'] = $hexData;

        $url = W2Web::getCurrentHost().'/task_run.php';
        $ch  = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        // curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');              // 发送head请求
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        $result = curl_exec($ch);

        return [$url,$params,$result];
    }

    /*解析数据，并执行对应的方法的心跳。详情参考task_run.php代码。*/
    public static function taskRun($hexData)
    {
        $data = W2String::rc4(TASK_DATA_RANDCODE,W2String::hex2asc($hexData));
        list($class,$method,$args,$taskTime,$taskID) = unserialize($data);

        $reflection = new ReflectionMethod($class, $method);
        if ($reflection->isProtected())
        {
            return call_user_func_array( [$class,'taskCheck'] , [$method,$args,$taskTime,$taskID]);
        }

        throw new Exception("Error no task run", 1);
    }


    /*维持任务心跳并在预期时间执行任务*/
    public static function taskCheck($method, $args, $taskTime=null, $taskID=null)
    {
        $taskTime = W2Time::strtotime($taskTime);

        if ($taskID>0)
        {
            //taskStatus 1: 未执行 2: 心跳中 3：执行出错 5：执行中 8：有结果但失败 9：有结果且成功
            $dbFac = DBModel::instance('w2task')->where(['id'=>$taskID]);
            $task = $dbFac->selectSingle();
            if (!is_null($task) && $task['lastHeartbeatTime'])
            {
                if (W2Time::getTimeBetweenDateTime(null,$task['lastHeartbeatTime'])<static::$HEARTBEAT_INTERVAL)
                {
                    throw new Exception("this task is in heartbeat, can not be newed. ", 1);
                }
            }
        }
        $offset = $taskTime - time();
        while ($offset>0) {
            if (isset($dbFac))
            {
                $dbFac->update(['taskStatus = 2','heartbeatCount = heartbeatCount + 1','lastHeartbeatTime = now()','modifyTime = now()']);
                DBTool::close();//每次执行sql更新之后，就关闭数据库连接，因为紧接着要sleep很久，释放数据库连接以免占用。
            }
            //心跳开始
            sleep($offset>static::$HEARTBEAT_INTERVAL?static::$HEARTBEAT_INTERVAL:$offset);

            $offset = $taskTime - time();
        }

        try {
            if (isset($dbFac))
            {
                $dbFac->update(['taskStatus = 5','modifyTime = now()']);
                DBTool::close();//每次执行sql更新之后，就关闭数据库连接，因为紧接着要sleep很久，释放数据库连接以免占用。
            }
            // 尝试执行目标任务
            $ret = call_user_func_array( 'static::'.$method , $args);
            if (isset($dbFac))
            {
                if ($ret)
                {
                    $dbFac->update(['taskStatus = 9','successTime = now()','modifyTime = now()']);
                    $dbFac->update(['taskResult'=>serialize($ret)]);
                }
                else
                {
                    $dbFac->update(['taskStatus = 8','failCount = failCount + 1','lastFailTime = now()','modifyTime = now()']);
                    $dbFac->update(['taskResult'=>serialize($ret)]);
                }
            }
        } catch (Exception $e) {
            $ret = false;
            if (isset($dbFac))
            {
                if ($ret)
                {
                    $dbFac->update(['taskStatus = 3','successTime = now()','modifyTime = now()']);
                    $dbFac->update(['taskResult'=>serialize($e)]);
                }
            }
        }
        if (isset($dbFac))
        {
            DBTool::close();
        }

        return $ret;
    }


    /*范例，protected的方法会异步执行哦。*/
    protected static function sayHello()
    {
        var_dump(func_get_args());
        echo 'say hello';
    }
}


if (defined('TASK_DATA_RANDCODE'))
{
    W2Task::$TASK_DATA_RANDCODE          = TASK_DATA_RANDCODE;
}
