<?php
/**
 * 邮件函数库文件，依赖PHPMailer（http://phpmailer.worxware.com）
 * @package W2
 * @author 琐琐
 * @since 1.1
 * @version 1.1
 */

class W2Mail {

    const REQUIRE_PATH = '../PHPMailer/class.phpmailer.php';

    public static $MAIL_ACCOUNT          = null;
    public static $MAIL_PASSWORD         = null;
    public static $MAIL_SENDERNAME       = null;
    public static $MAIL_HOST             = null;
    public static $MAIL_PORT             = null;


    public static $SENDCLOUD_API_USER    = null;
    public static $SENDCLOUD_API_KEY     = null;

    /**
     * 检查依赖项是否存在
     * @return true|false
     */
    private static function checkRequire(){
        $_libPath = __dir__.'/'.W2Mail::REQUIRE_PATH;
        if(class_exists('PHPMailer')){
            return true;
        } else if(file_exists($_libPath)){
            include($_libPath);
            return true;
        } else {
            return false;
        }
    }

    /**
     * 使用mail帐户发送邮件
     * @param string $pToMail 收件人地址
     * @param string $pSubject 主题
     * @param string $pContent 正文
     * @param array  $pAttachment 附件
     * @return array 结果
     */
    public static function sendMail($pToMail, $pSubject, $pContent, $pAttachment=null) {

        if(!W2Mail::checkRequire()){
            throw new Exception('require PHPMailer', 1);
        } else {
            $mail = new PHPMailer(true);
            $mail->IsSMTP();

            $mail->CharSet    = 'UTF-8';
            $mail->Host       = static::$MAIL_HOST;      // sets GMAIL as the SMTP server
            $mail->SMTPDebug  = 0;                     // enables SMTP debug information (for testing)
                                                       // 1 = errors and messages
                                                       // 2 = messages only
            $mail->SMTPAuth   = true;                  // enable SMTP authentication
            $mail->SMTPSecure = "ssl";                 // sets the prefix to the servier
            $mail->Port       = static::$MAIL_PORT;                   // set the SMTP port for the GMAIL server
            $mail->Username   = static::$MAIL_ACCOUNT;          // GMAIL username
            $mail->Password   = static::$MAIL_PASSWORD;            // GMAIL password
            $mail->AddAddress($pToMail);
            $mail->SetFrom(static::$MAIL_ACCOUNT, static::$MAIL_SENDERNAME);
            $mail->AddReplyTo(static::$MAIL_ACCOUNT, static::$MAIL_SENDERNAME);

            $mail->Subject = $pSubject;
            $mail->MsgHTML($pContent);

            if (!is_null($pAttachment))
            {
                if (!is_array($pAttachment))
                {
                    $pAttachment = array($pAttachment);
                }
                foreach ($pAttachment as $_a)
                {
                    if (file_exists($_a))
                    {
                        $mail->AddAttachment($_a);
                    }
                }
            }
            $mail->Send();
        }
        return true;
    }

    public static function sendCloudWithTemple($pToMail, $pSubject, $pSub, $pAttachment=null, $pFromMail, $pFromname = null)
    {
        $url = 'http://sendcloud.sohu.com/webapi/mail.send_template.json';

        $substitution_vars = array();
        $substitution_vars['to'] = W2String::getStringsArray($pToMail);
        $substitution_vars['sub'] = $pSub;//"sub":{"%name%": ["Ben", "Joe"],"%money%":[288, 497]}

        $params = array();
        $params['api_user']             = static::$SENDCLOUD_API_USER;//string   是   API_USER
        $params['api_key']              = static::$SENDCLOUD_API_KEY;//string   是   API_KEY
        $params['from']                 = $pFromMail;//string   是   发件人地址. from 和发信域名, 会影响是否显示代发
        $params['substitution_vars']    = $substitution_vars;//string   *   模板替换变量. 在 use_maillist=false 时使用, 如: {"to": ["ben@ifaxin.com", "joe@ifaxin.com"],"sub":{"%name%": ["Ben", "Joe"],"%money%":[288, 497]}}
        // $params['to']                   = '';//string   *   收件人的地址列表. 在 use_maillist=true 时使用
        $params['subject']              = $pSubject;//string   否   邮件标题
        $params['template_invoke_name'] = $pTemplate;//string   是   邮件模板调用名称
        $params['fromname']             = $pFromname;//string   否   发件人名称. 显示如: ifaxin客服支持 <support@ifaxin.com>
        // $params['replyto']              = '';//string   否   默认的回复邮件地址. 如果 replyto 没有或者为空, 则默认的回复邮件地址为 from
        // $params['label']                = '';//int  否   本次发送所使用的标签ID. 此标签需要事先创建
        // $params['headers']              = '';//string   否   邮件头部信息. JSON 格式, 比如:{"header1": "value1", "header2": "value2"}
        $params['files']                = $pAttachment;//string   否   邮件附件. 发送附件时, 必须使用 multipart/form-data 进行 post 提交 (表单提交)
        // $params['resp_email_id']        = '';//string (true, false) 否   是否返回 emailId. 有多个收件人时, 会返回 emailId 的列表
        // $params['use_maillist']         = '';//string (true, false) 否   参数 to 是否支持地址列表, 默认为 false. 比如: to=users@maillist.sendcloud.org
        // $params['gzip_compress']        = '';//string (true, false) 否   邮件内容是否使用gzip压缩. 默认不使用 gzip 压缩正文

        $result = W2Web::loadStringByUrl($url,'post',$params);

        return $result;
    }
}



//静态类的静态变量的初始化不能使用宏，只能用这样的笨办法了。
if (W2Mail::$SENDCLOUD_API_USER == null && defined('W2MAIL_SENDCLOUD_API_USER'))
{
    W2Mail::$SENDCLOUD_API_USER      = W2MAIL_SENDCLOUD_API_USER;
    W2Mail::$SENDCLOUD_API_KEY       = W2MAIL_SENDCLOUD_API_KEY;
}

//静态类的静态变量的初始化不能使用宏，只能用这样的笨办法了。
if (W2Mail::$MAIL_ACCOUNT==null && defined('W2MAIL_ACCOUNT'))
{
    W2Mail::$MAIL_ACCOUNT            = W2MAIL_ACCOUNT;
    W2Mail::$MAIL_PASSWORD           = W2MAIL_PASSWORD;
    W2Mail::$MAIL_SENDERNAME         = W2MAIL_SENDERNAME;
    W2Mail::$MAIL_HOST               = W2MAIL_HOST;
    W2Mail::$MAIL_PORT               = W2MAIL_PORT;
}
