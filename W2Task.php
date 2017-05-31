<?php
/**
 * 异步类，本类中的方法是异步调用的哦。
 * 所有的protected权限下的静态方法都会被异步调用。
 * 开发者可以在自己项目中继承此类并添加更多的static方法。
 * 注意：在方法中，尽量避免使用全局变量，特殊类型的参数（只有可被serialize的变量才可使用作为参数）
 * @package W2
 * @author axing
 * @since 1.0
 * @version 1.0
 */


class W2Task
{
    /* 魔术方法，捕捉所有protected的方法 */
    public static function __callStatic($method, $args)
    {
        $reflection = new ReflectionMethod(get_called_class(), $method);
        if ($reflection->isProtected())
        {
            return static::taskPush(get_called_class(),$method,$args);
        }
        else
        {
            var_dump($reflection);
            throw new Exception("Error method Request", 1);
        }
    }

    /*代理方法，可以直接执行指定的静态方法*/
    public static function callStaticMethod($method , $args)
    {
        return call_user_func_array( 'static::'.$method , $args);
    }

    /*提交一个异步请求，此处使用的是curl请求头的方案，即发起一个网络连接，然后迅速断开，由目标在连接断开后继续执行，达到异步执行的效果。*/
    public static function taskPush($class,$method,$args)
    {
        $data = serialize(array($class,$method,$args));
        $params['data'] = W2String::asc2hex(W2String::rc4(TASK_DATA_RANDCODE,$data));

        $url = W2Web::getCurrentHost().'/task_run.php';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        // curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');              // 发送head请求
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        $result = curl_exec($ch);

        return true;
    }
    /*从网络请求里捕捉参数，并执行对应的方法。详情参考task_run.php代码。*/
    public static function taskRun()
    {
        $params = $_REQUEST;
        if (isset($params['data']))
        {
            $data = W2String::rc4(TASK_DATA_RANDCODE,W2String::hex2asc($params['data']));
            list($class,$method,$args) = unserialize($data);

            $reflection = new ReflectionMethod($class, $method);
            if ($reflection->isProtected())
            {
                return call_user_func_array( [$class,'callStaticMethod'] , [$method,$args]);
            }
        }
        throw new Exception("Error no task run", 1);
    }

    /*范例，protected的方法会异步执行哦。*/
    protected static function sayHello()
    {
        var_dump(func_get_args());
        echo 'say hello';
    }
}

