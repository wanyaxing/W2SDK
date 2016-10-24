<?php
/**
 * 微信公众号相关
 * @package W2
 * @author axing
 * @since 1.0
 * @version 1.0
 */

class W2Weixin {
    public static $APPID           = null; //公众号ID
    public static $SECRET          = null; //公众号后台-开发-基本配置-AppSecret

    public static function getAccessToken()
    {
        $cacheKey = (defined('AXAPI_PROJECT_NAME')?AXAPI_PROJECT_NAME:'').$_SERVER['HTTP_HOST'].'_W2Weixin_access_token';//使用一个唯一的key值
        $access_token = W2Cache::getCache($cacheKey);
        if (is_null($access_token))
        {
            $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . static::$APPID . '&secret=' .  static::$SECRET . '';
            $result = W2Web::loadJsonByUrl($url);
            if (is_array($result) && isset($result['access_token']))
            {
                $access_token = $result['access_token'];
                W2Cache::setCache($cacheKey,$result['access_token'],$result['expires_in']);
            }
        }
        return $access_token;
    }

    /**
     * https://mp.weixin.qq.com/wiki/7/aaa137b55fb2e0456bf8dd9148dd613f.html#.E9.99.84.E5.BD.951-JS-SDK.E4.BD.BF.E7.94.A8.E6.9D.83.E9.99.90.E7.AD.BE.E5.90.8D.E7.AE.97.E6.B3.95
     * 附录1-JS-SDK使用权限签名算法
     * @return string jsapi_ticket
     */
    public static function getJsapiTicket()
    {
        $cacheKey = (defined('AXAPI_PROJECT_NAME')?AXAPI_PROJECT_NAME:'').$_SERVER['HTTP_HOST'].'_W2Weixin_jsapi_ticket';//使用一个唯一的key值
        $jsapi_ticket = W2Cache::getCache($cacheKey);
        if (is_null($jsapi_ticket))
        {
            $url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.static::getAccessToken().'&type=jsapi';
            $result = W2Web::loadJsonByUrl($url);
            if (is_array($result) && isset($result['ticket']))
            {
                $jsapi_ticket = $result['ticket'];
                W2Cache::setCache($cacheKey,$result['ticket'],$result['expires_in']);
            }
        }
        return $jsapi_ticket;
    }

    /**
     * https://mp.weixin.qq.com/wiki/17/c0f37d5704f0b64713d5d2c37b468d75.html
     *
     * 第一步：用户同意授权，获取code
     * @param  string $redirect_uri 授权后回调地址，需事先设定授权域名： 公众号后台-开发-接口权限，更改授权回调域名为当前域名。
     * @param  string $scope        应用授权作用域，snsapi_base （不弹出授权页面，直接跳转，只能获取用户openid），snsapi_userinfo （弹出授权页面，可通过openid拿到昵称、性别、所在地。并且，即使在未关注的情况下，只要用户授权，也能获取其信息）
     * @param  string $state        重定向后会带上state参数，开发者可以填写a-zA-Z0-9的参数值，最多128字节
     * @return string               回调指定路径的微信授权地址
     * 如果用户同意授权，页面将跳转至 redirect_uri/?code=CODE&state=STATE。
     * 若用户禁止授权，则重定向后不会带上code参数，仅会带上state参数redirect_uri?state=STATE
     */
    public static function getUrlForWxAuth($redirect_uri=null,$scope='snsapi_base',$state='haoxitech')
    {
        if (is_null($redirect_uri))
        {
            $redirect_uri = 'http://' .  $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ;
        }
        $redirect_uri = urlencode($redirect_uri);
        return 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=' . static::$APPID . '&redirect_uri=' . $redirect_uri . '&response_type=code&scope='.$scope.'&state='.$state.'#wechat_redirect';
    }

    /**
     * 第二步：通过code换取网页授权access_token
     * 首先请注意，这里通过code换取的是一个特殊的网页授权access_token,
     * 与基础支持中的access_token（该access_token用于调用其他接口）不同。
     * 公众号可通过下述接口来获取网页授权access_token。
     * 如果网页授权的作用域为snsapi_base，则本步骤中获取到网页授权access_token的同时，也获取到了openid，snsapi_base式的网页授权流程即到此为止。
     * @param  string       $code   填写第一步获取的code参数
     * @return array
{
"access_token":"ACCESS_TOKEN",
"expires_in":7200,
"refresh_token":"REFRESH_TOKEN",
"openid":"OPENID",
"scope":"SCOPE",
"unionid": "o6_bmasdasdsad6_2sgVt7hMZOPfL"
}
     */
    public static function getTokenOfCode($code)
    {
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=' . static::$APPID . '&secret=' .  static::$SECRET . '&code=' . $code . '&grant_type=authorization_code';
        return W2Web::loadJsonByUrl($url);
    }

    /**
     * 第四步：拉取用户信息(需scope为 snsapi_userinfo)
     * 如果网页授权作用域为snsapi_userinfo，则此时开发者可以通过access_token和openid拉取用户信息了。
     * @param  string $access_token 网页授权接口调用凭证,注意：此access_token与基础支持的access_token不同
     * @param  string $openid       用户的唯一标识
     * @param  string $lang         返回国家地区语言版本，zh_CN 简体，zh_TW 繁体，en 英语
     * @return array                用户数据
{
   "openid":" OPENID",
   " nickname": NICKNAME,
   "sex":"1",
   "province":"PROVINCE"
   "city":"CITY",
   "country":"COUNTRY",
    "headimgurl":    "http://wx.qlogo.cn/mmopen/g3MonUZtNHkdmzicIlibx6iaFqAc56vxLSUfpb6n5WKSYVY0ChQKkiaJSgQ1dZuTOgvLLrhJbERQQ4eMsv84eavHiaiceqxibJxCfHe/46",
    "privilege":[
    "PRIVILEGE1"
    "PRIVILEGE2"
    ],
    "unionid": "o6_bmasdasdsad6_2sgVt7hMZOPfL"
}
     */
    public static function getUserInfoOfAccessToken($access_token,$openid,$lang='zh_CN')
    {
        $url = 'https://api.weixin.qq.com/sns/userinfo?access_token='.$access_token.'&openid='.$openid.'&lang='.$lang;
        return W2Web::loadJsonByUrl($url);
    }

    /**
     * 获取用户信息
     * 合并了第二步和第四步
     * （如不支持，则只返回token信息）
     * @param  string $code 填写第一步获取的code参数
     * @return array        第二步 或 第四步 的结果
     */
    public static function getUserInfoOfCode($code)
    {
        $token = static::getTokenOfCode($code);
        if (is_array($token) && isset($token['access_token']))
        {
            if ( isset($token['scope']) && strpos($token['scope'], 'snsapi_userinfo')!==false )
            {
                return static::getUserInfoOfAccessToken($token['access_token'],$token['openid']);
            }
        }
        return $token;
    }

    /**
     * 使用接口创建自定义菜单后，开发者还可使用接口查询自定义菜单的结构。
     * @return 对应创建接口，正确的Json返回结果:
     */
    public static function getMenu()
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/menu/get?access_token='.static::getAccessToken();
        return W2Web::loadJsonByUrl($url);
    }

    /**
     * https://mp.weixin.qq.com/wiki/13/43de8269be54a0a6f64413e4dfa94f39.html
     * 自定义菜单能够帮助公众号丰富界面，让用户更好更快地理解公众号的功能。
     * 1、自定义菜单最多包括3个一级菜单，每个一级菜单最多包含5个二级菜单。
     * 2、一级菜单最多4个汉字，二级菜单最多7个汉字，多出来的部分将会以“...”代替。
     * 3、创建自定义菜单后，由于微信客户端缓存，需要24小时微信客户端才会展现出来。测试时可以尝试取消关注公众账号后再次关注，则可以看到创建后的效果。
     * @param [type] $menu [description]
     */
    public static function setMenu($menu)
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/menu/create?access_token='.static::getAccessToken();
        return W2Web::loadJsonByUrl($url,'post',json_encode($menu,JSON_UNESCAPED_UNICODE));
    }

    /**
     * https://mp.weixin.qq.com/wiki/17/304c1885ea66dbedf7dc170d84999a9d.html#.E5.8F.91.E9.80.81.E6.A8.A1.E6.9D.BF.E6.B6.88.E6.81.AF
     * @param  string $templateid 模板ID，需要事先在 公众号后台-功能-模板消息中，添加模板获得对应id
     * @param  string $openid     用户的token
     * @param  string $url        打开后的网址
     * @param  array $data       话术字典，通常对应模板中的填充字段
     * @return array
     */
    public static function sendTemplateMessage($templateid,$openid,$jump,$data)
    {
        if (!is_null($openid))
        {
            $url = 'https://api.weixin.qq.com/cgi-bin/message/template/send?access_token='.static::getAccessToken();
            $message = array(
                         'touser'      => $openid
                        ,'template_id' => $templateid
                        ,'url'         => $jump
                        ,'data'        => $data
                        );
            return W2Web::loadJsonByUrl($url,'post',json_encode($message,JSON_UNESCAPED_UNICODE));
        }
        return null;
    }

    /**
     * 为指定url生成对应的微信js认证数据
     * @param  string $url       当前网址
     * @param  string $timestamp 时间戳
     * @param  string $nonceStr  随机字符串
     * @return array            [description]
     */
    public static function getSignatureDataForJS($url,$timestamp=null,$nonceStr=null)
    {
        if ($timestamp==null){$timestamp = time();}
        if ($nonceStr==null){$nonceStr = W2String::buildRandCharacters(13);}
        $data = array(
                 'url'          => $url
                ,'timestamp'    => $timestamp
                ,'noncestr'     => $nonceStr
                ,'jsapi_ticket' => static::getJsapiTicket()
            );
        return array(
                'appId'=>static::$APPID
                ,'timestamp'=>$timestamp
                ,'nonceStr'=>$nonceStr
                ,'signature'=>sha1(W2Array::sortAndBuildQuery($data))
            );
    }

    /**
     * http://mp.weixin.qq.com/wiki/1/8a5ce6257f1d3b2afb20f83e72b72ce9.html
     * 在关注者与公众号产生消息交互后，公众号可获得关注者的OpenID（加密后的微信号，每个用户对每个公众号的OpenID是唯一的。对于不同公众号，同一用户的openid不同）。
     * 公众号可通过本接口来根据OpenID获取用户基本信息，包括昵称、头像、性别、所在城市、语言和关注时间。
     * @param  string $openid
     * @return array
     */
    public static function getUserInfoOfOpenID($openid)
    {
        if (!is_null($openid))
        {
            $url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token='.static::getAccessToken().'&openid='.$openid.'&lang=zh_CN';
            return W2Web::loadJsonByUrl($url);
        }
        return null;
    }

    /**
     *
     * http://mp.weixin.qq.com/wiki/18/167e7d94df85d8389df6c94a7a8f78ba.html
     * 每次创建二维码ticket需要提供一个开发者自行设定的参数（scene_id），分别介绍临时二维码和永久二维码的创建二维码ticket过程。
     * @param  integer $expire_seconds 该二维码有效时间，以秒为单位。 最大不超过2592000（即30天），此字段如果不填，则默认有效期为30秒。
     * @param  string  $action_name    二维码类型，QR_SCENE为临时,QR_LIMIT_SCENE为永久,QR_LIMIT_STR_SCENE为永久的字符串参数值
     * @param  int     $scene_id       场景值ID，临时二维码时为32位非0整型，永久二维码时最大值为100000（目前参数只支持1--100000）
     * @param  string  $scene_str      场景值ID（字符串形式的ID），字符串类型，长度限制为1到64，仅永久二维码支持此字段
     * @return array
     */
    public static function getTicketForQrcode($scene_id=null,$scene_str=null,$action_name='QR_SCENE',$expire_seconds=2592000)
    {
        $params = array(
                'expire_seconds'=>$expire_seconds
                ,'action_name'=>$action_name
                ,'action_info'=>array( 'scene'=>array() )
            );
        if (!is_null($scene_id))
        {
            $params['action_info']['scene']['scene_id'] = $scene_id;
        }
        if (!is_null($scene_str))
        {
            $params['action_info']['scene']['scene_str'] = $scene_str;
        }

        $url = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token='.static::getAccessToken();

        return W2Web::loadJsonByUrl($url,'post',json_encode($params,JSON_UNESCAPED_UNICODE));
    }

    /**
     * http://mp.weixin.qq.com/wiki/15/8386c11b7bc4cdd1499c572bfe2e95b3.html
     * 开发者可以分类型获取永久素材的列表。
     * @param  string  $type   素材的类型，图片（image）、视频（video）、语音 （voice）、图文（news）
     * @param  integer $offset 从全部素材的该偏移位置开始返回，0表示从第一个素材 返回
     * @param  integer $count  返回素材的数量，取值在1到20之间
     * @return array
     */
    public static function batchgetMaterial($type=null,$offset=0,$count=20)
    {
        $params = array(
                'type'=>$type
                ,'offset'=>$offset
                ,'count'=>$count
            );

        $url = 'https://api.weixin.qq.com/cgi-bin/material/batchget_material?access_token='.static::getAccessToken();

        return W2Web::loadJsonByUrl($url,'post',json_encode($params,JSON_UNESCAPED_UNICODE));
    }

    /**
     * http://mp.weixin.qq.com/wiki/12/3c12fac7c14cb4d0e0d4fe2fbc87b638.html
     * 在新增了永久素材后，开发者可以根据media_id来获取永久素材，需要时也可保存到本地。
     * @param  int $media_id 要获取的素材的media_id
     * @return array 如果请求的素材为图文消息或视频消息，则响应相关数据，其他类型的素材消息，则响应的直接为素材的内容，开发者可以自行保存为文件。
     */
    public static function getMaterial($media_id=null)
    {
        $params = array(
                'media_id'=>$media_id
            );

        $url = 'https://api.weixin.qq.com/cgi-bin/material/get_material?access_token='.static::getAccessToken();

        return W2Web::loadStringByUrl($url,'post',json_encode($params,JSON_UNESCAPED_UNICODE));
    }

    /**
     * http://mp.weixin.qq.com/wiki/18/749901f4e123170fb8a4d447ae6040ba.html#.E8.8E.B7.E5.8F.96.E5.9C.A8.E7.BA.BF.E5.AE.A2.E6.9C.8D.E6.8E.A5.E5.BE.85.E4.BF.A1.E6.81.AF
     * 获取在线客服接待信息
     * @return array
     */
    public static function getOnlineKfList()
    {
        $url = 'https://api.weixin.qq.com/cgi-bin/customservice/getonlinekflist?access_token='.static::getAccessToken();

        return W2Web::loadJsonByUrl($url,'get',array());
    }


}

//静态类的静态变量的初始化不能使用宏，只能用这样的笨办法了。
if (W2Weixin::$APPID==null && defined('W2WEIXIN_APPID'))
{
    W2Weixin::$APPID          = W2WEIXIN_APPID;
    W2Weixin::$SECRET         = W2WEIXIN_SECRET;
}
