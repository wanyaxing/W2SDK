<?php
/**
 * 环信处理函数库文件
 * http://docs.easemob.com/im/100serverintegration/10intro
 * @package W2
 * @author axing
 * @since 1.0
 * @version 1.0
 */

class W2Easemob {

	public static $CLIENT_ID         = null;
	public static $CLIENT_SECRET     = null;
	public static $ORG_NAME          = null;
	public static $APP_NAME          = null;

	public static $BASE_URL          = null;

	public static $token 			 = null;




	/** +++++++++++++++++++++++拓展方法+++++++++++++++++++++++++ */
	public static function pushMessage($content,$customtype=null,$customvalue = null ,$p_deviceTokens=null)
    {


    	$ret = array();

		$p_deviceTokens = is_array($p_deviceTokens)?$p_deviceTokens:explode(',',$p_deviceTokens);
		if (count($p_deviceTokens)==0 || (count($p_deviceTokens)==1 && $p_deviceTokens[0]==null))
		{
			throw new Exception('请传入正确的环信用户账号');
			return false;
		}

		$custom = null;
		if (isset($customtype,$customvalue))
		{
			$custom = array('t'=>intval($customtype), 'v'=>$customvalue);
		}

		$maxCount = 20;//每次最大传输设备量
		for ($i=0; $i < count($p_deviceTokens) ; $i+= $maxCount)
		{
			$ret[] = array(
						'action'=>'sendTextToUsers'
						,'token'=>$p_deviceTokens
						,'ret'=>static::sendTextToUsers(array_slice($p_deviceTokens,$i, $maxCount),$content,$custom)
						);
		}



    	return array(ERROR_CODE::$OK,$ret);
    }

	/** +++++++++++++++++++++++基础REST接口+++++++++++++++++++++++++ */
	/**
	*获取token
	*重要，基本所有请求都用到token
	*/
	public static function getToken()
	{
		if (static::$token==null)
		{
			$options 	= array(
								"grant_type"    => "client_credentials"
								,"client_id"     => static::$CLIENT_ID
								,"client_secret" => static::$CLIENT_SECRET
								);
			$action         =  'token';
			$tokenResult =  static::easemobCurl($action,$options,'POST',array());
			if (!is_array($tokenResult) || !isset($tokenResult["access_token"]))
			{
				throw new Exception('获得环信token失败，请联系管理员。');
			}
			static::$token 		 =  'Authorization:Bearer '. $tokenResult["access_token"];
		}
		return static::$token;
	}

	/**
	  授权注册
	*/
	public static function createUser($username,$password){
		return static::post('users'
								,array(
									'username'=>$username
									,'password'=>$password
								));
	}

	/*
		重置用户密码
	*/
	public static function resetPassword($username,$newpassword){
		return static::put('users/'.$username.'/password'
								,array(
									'newpassword'=>$newpassword
								));
	}



	/*
		获取单个用户
	*/
	public static function getUser($username){
		return static::get('users/'.$username,'');
	}


	/*
		获取批量用户----不分页
	*/
	public static function getUsers($limit=0){

		if(!empty($limit)){
			$url=$this->url.'users?limit='.$limit;
		}else{
			$url=$this->url.'users';
		}
		return static::get('users'.(!empty($limit)?'?limit='.$limit:''),'');
	}
	/*
		获取批量用户---分页
	*/
	public static function getUsersForPage($limit=0,$cursor=''){
		return static::get('users?limit='.$limit.'&cursor='.$cursor,'');
	}

	/*
		删除单个用户
	*/
	public static function deleteUser($username){
		return static::delete('users/'.$username,'');
	}


	/*
		删除批量用户
		limit:建议在100-500之间，、
		注：具体删除哪些并没有指定, 可以在返回值中查看。
	*/
	public static function deleteUsers($limit){
		return static::delete('users?limit='.$limit,'');
	}


	/*
		修改用户昵称
	*/
	public static function editNickname($username,$nickname){
		return static::put('users/'.$username
								,array(
									'nickname'=>$nickname
								));
	}

	/*
		添加好友-
	*/
	public static function addFriend($username,$friend_name){
		return static::post('users/'.$username.'/contacts/users/'.$friend_name,'');
	}


	//--------------------------------------------------------发送消息
	/*
		发送文本消息
	*/
	public static function sendTextToUsers($target,$content,$ext=null,$from='admin'){
		return static::post('messages',array(
											'target_type'=>'users'
											,'target'=>$target
											,'from'=>$from
											,'ext'=>$ext
											,'msg'=>array(
													'type'=>'txt'
													,'msg'=>$content
												)
									));
	}
	/*
		发送透传消息
	*/
	public static function sendCmdToUsers($target,$action,$ext=null,$from='admin'){
		return static::post('messages',array(
											'target_type'=>'users'
											,'target'=>$target
											,'from'=>$from
											,'ext'=>$ext
											,'msg'=>array(
													'type'=>'cmd'
													,'action'=>$action
												)
									));
	}
	//... 很多接口，自己看文档吧






	/*   ＋＋＋＋＋＋＋＋＋＋＋＋＋＋＋＋＋基础工具＋＋＋＋＋＋＋＋＋＋＋＋＋＋＋＋＋         */

	public static function post($action, $params=null)
	{
		return static::easemobCurl($action,$params,'POST');
	}

	public static function get($action, $params=null)
	{
		return static::easemobCurl($action,$params,'GET');
	}

	public static function put($action, $params=null)
	{
		return static::easemobCurl($action,$params,'PUT');
	}

	public static function delete($action, $params=null)
	{
		return static::easemobCurl($action,$params,'DELETE');
	}


	public static function easemobCurl($action, $params=null, $type='POST' , $header = null)
	{
		$url 		= static::$BASE_URL . $action;
		if (is_array($params))
		{
			$body		= json_encode($params);
		}
		else
		{
			$body 		= $params;
		}
		if ($header===null)
		{
			$header 	= array(static::getToken());
		}

		//1.创建一个curl资源
		$ch = curl_init();
		//2.设置URL和相应的选项
		curl_setopt($ch,CURLOPT_URL,$url);//设置url
		//1)设置请求头
		//array_push($header, 'Accept:application/json');
		//array_push($header,'Content-Type:application/json');
		//array_push($header, 'http:multipart/form-data');
		//设置为false,只会获得响应的正文(true的话会连响应头一并获取到)
		curl_setopt($ch,CURLOPT_HEADER,0);
		curl_setopt ( $ch, CURLOPT_TIMEOUT,5); // 设置超时限制防止死循环
		//设置发起连接前的等待时间，如果设置为0，则无限等待。
		curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,5);
		//将curl_exec()获取的信息以文件流的形式返回，而不是直接输出。
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		//2)设备请求体
		if (count($body)>0) {
			//$b=json_encode($body,true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);//全部数据使用HTTP协议中的"POST"操作来发送。
		}
		//设置请求头
		if(count($header)>0){
			curl_setopt($ch,CURLOPT_HTTPHEADER,$header);
		}
		//上传文件相关设置
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);// 对认证证书来源的检查
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);// 从证书中检查SSL加密算

		//3)设置提交方式
		switch($type){
			case "GET":
				curl_setopt($ch,CURLOPT_HTTPGET,true);
				break;
			case "POST":
				curl_setopt($ch,CURLOPT_POST,true);
				break;
			case "PUT"://使用一个自定义的请求信息来代替"GET"或"HEAD"作为HTTP请									                     求。这对于执行"DELETE" 或者其他更隐蔽的HTT
				curl_setopt($ch,CURLOPT_CUSTOMREQUEST,"PUT");
				break;
			case "DELETE":
				curl_setopt($ch,CURLOPT_CUSTOMREQUEST,"DELETE");
				break;
		}


		//4)在HTTP请求中包含一个"User-Agent: "头的字符串。-----必设

		curl_setopt($ch, CURLOPT_USERAGENT, 'SSTS Browser/1.0');
		curl_setopt($ch, CURLOPT_ENCODING, 'gzip');

		curl_setopt ( $ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0; Trident/4.0)' ); // 模拟用户使用的浏览器
		//5)


		//3.抓取URL并把它传递给浏览器
		$res=curl_exec($ch);
		// var_export($url);
		// var_export($header);
		// var_export($body);
		// var_export($res);

		$result=json_decode($res,true);
		//4.关闭curl资源，并且释放系统资源
		curl_close($ch);
		if(empty($result))
			return $res;
		else
			return $result;

	}
}

//静态类的静态变量的初始化不能使用宏，只能用这样的笨办法了。
if (W2Easemob::$CLIENT_ID==null && defined('W2EASEMOB_CLIENT_ID'))
{
	W2Easemob::$CLIENT_ID        = W2EASEMOB_CLIENT_ID;
	W2Easemob::$CLIENT_SECRET    = W2EASEMOB_CLIENT_SECRET;
	W2Easemob::$ORG_NAME         = W2EASEMOB_ORG_NAME;
	W2Easemob::$APP_NAME         = W2EASEMOB_APP_NAME;

	W2Easemob::$BASE_URL         = 'https://a1.easemob.com/'. W2EASEMOB_ORG_NAME . '/' . W2EASEMOB_APP_NAME . '/';;
}
