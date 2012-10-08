<?php
/**
 * Users Core Gadget
 *
 * @category   GadgetModel
 * @package    Users
 * @author     Pablo Fischer <pablo@pablo.com.mx>
 * @author     Ali Fazelzadeh <afz@php.net>
 * @copyright  2006-2012 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/lesser.html
 */
class Users_Model_Registration extends Jaws_Model
{
    /**
     * Creates a valid(registered) n user for an anonymous user
     *
     * @access  public
     * @param   string  $username   Username
     * @param   string  $email      User's email
     * @param   string  $nickname   User's display name
     * @param   string  $password   Password
     * @param   string  $p_check    Password check (to verify)
     * @param   string  $group      Default user group
     * @return  boolean Success/Failure
     */
    function CreateUser($username, $user_email, $nickname, $fname, $lname, $gender, $dob, $url,
                        $password, $group = null)
    {
        if (empty($username) || empty($nickname) || empty($user_email))
        {
            return _t('USERS_USERS_INCOMPLETE_FIELDS');
        }

        $random = false;
        if (trim($password) == '') {
            $random = true;
            include_once 'Text/Password.php';
            $password = Text_Password::create(8, 'pronounceable', 'alphanumeric');
        }

        $xss = $GLOBALS['app']->loadClass('XSS', 'Jaws_XSS');
        require_once JAWS_PATH . 'include/Jaws/User.php';
        $jUser = new Jaws_User;

        //We already have a $username in the DB?
        $info = $jUser->GetUser($username);
        if (Jaws_Error::IsError($info) || isset($info['username'])) {
            return _t('USERS_USERS_ALREADY_EXISTS', $xss->filter($username));
        }

        if ($GLOBALS['app']->Registry->Get('/config/anon_repetitive_email') == 'false') {
            if ($jUser->UserEmailExists($user_email)) {
                return _t('USERS_EMAIL_ALREADY_EXISTS', $xss->filter($user_email));
            }
        }

        $user_enabled = ($GLOBALS['app']->Registry->Get('/config/anon_activation') == 'auto')? 1 : 2;
        $user_id = $jUser->AddUser($username,
                                   $xss->filter($nickname),
                                   $user_email,
                                   $password,
                                   false,
                                   $user_enabled);
        if (Jaws_Error::IsError($user_id)) {
            return $user_id->getMessage();
        }

        $pInfo = array('fname'  => $fname,
                       'lname'  => $lname,
                       'gender' => $gender,
                       'dob'    => $dob,
                       'url'    => $url);

        $result = $jUser->UpdatePersonalInfo($user_id, $pInfo);
        if ($result === false) {
            //do nothing
        }

        if (!is_null($group) && is_numeric($group)) {
            $jUser->AddUserToGroup($user_id, $group);
        }

        require_once JAWS_PATH . 'include/Jaws/Mail.php';
        $mail = new Jaws_Mail;

        $site_url     = $GLOBALS['app']->getSiteURL('/');
        $site_name    = $GLOBALS['app']->Registry->Get('/config/site_name');
        $site_author  = $GLOBALS['app']->Registry->Get('/config/site_author');
        $activation   = $GLOBALS['app']->Registry->Get('/config/anon_activation');
        $notification = $GLOBALS['app']->Registry->Get('/gadgets/Users/register_notification');
        $delete_user  = false;
        $message      = '';

        if ($random === true || $activation != 'admin') {
            $tpl = new Jaws_Template('gadgets/Users/templates/');
            $tpl->Load('UserNotification.txt');
            $tpl->SetBlock('Notification');
            $tpl->SetVariable('say_hello', _t('USERS_REGISTER_HELLO', $xss->filter($nickname)));

            if ($random === true) {
                switch ($activation) {
                    case 'admin':
                        $tpl->SetVariable('message', _t('USERS_REGISTER_BY_ADMIN_RANDOM_MAIL_MSG'));
                        break;

                    case 'user':
                        $tpl->SetVariable('message', _t('USERS_REGISTER_BY_USER_RANDOM_MAIL_MSG'));
                        break;

                    default:
                        $tpl->SetVariable('message', _t('USERS_REGISTER_RANDOM_MAIL_MSG'));
                        
                }

                $tpl->SetBlock('Notification/Password');
                $tpl->SetVariable('lbl_password', _t('USERS_USERS_PASSWORD'));
                $tpl->SetVariable('password', $xss->filter($password));
                $tpl->ParseBlock('Notification/Password');
            } elseif ($activation == 'user') {
                $tpl->SetVariable('message', _t('USERS_REGISTER_ACTIVATION_MAIL_MSG'));
            } else {
                $tpl->SetVariable('message', _t('USERS_REGISTER_MAIL_MSG'));
            }

            $tpl->SetBlock('Notification/IP');
            $tpl->SetVariable('lbl_ip', _t('GLOBAL_IP'));
            $tpl->SetVariable('ip', $_SERVER['REMOTE_ADDR']);
            $tpl->ParseBlock('Notification/IP');

            $tpl->SetVariable('lbl_username', _t('USERS_USERS_USERNAME'));
            $tpl->SetVariable('username', $xss->filter($username));

            if ($activation == 'user') {
                $secretKey = md5(uniqid(rand(), true)) . time() . floor(microtime()*1000);
                $result = $jUser->UpdateVerificationKey($user_id, $secretKey);
                if ($result === true) {
                    $tpl->SetBlock('Notification/Activation');
                    $tpl->SetVariable('lbl_activation_link', _t('USERS_ACTIVATE_ACTIVATION_LINK'));
                    $tpl->SetVariable('activation_link',
                                      $GLOBALS['app']->Map->GetURLFor('Users', 'ActivateUser',
                                                                      array('key' => $secretKey), true, 'site_url'));
                    $tpl->ParseBlock('Notification/Activation');
                } else {
                    $delete_user = true;
                    $message = _t('GLOBAL_ERROR_QUERY_FAILED');
                }
            }

            $tpl->SetVariable('thanks',    _t('GLOBAL_THANKS'));
            $tpl->SetVariable('site-name', $site_name);
            $tpl->SetVariable('site-url',  $site_url);

            $tpl->ParseBlock('Notification');
            $body = $tpl->Get();

            if (!$delete_user) {
                $subject = _t('USERS_REGISTER_SUBJECT', $site_name);
                $mail->SetFrom();
                $mail->AddRecipient($user_email);
                $mail->SetSubject($subject);
                $mail->SetBody(Jaws_Gadget::ParseText($body, 'Users'));
                $mresult = $mail->send();
                if (Jaws_Error::IsError($mresult)) {
                    if ($activation == 'user') {
                        $delete_user = true;
                        $message = _t('USERS_REGISTER_ACTIVATION_SENDMAIL_FAILED', $xss->filter($user_email));
                    } elseif ($random === true) {
                        $delete_user = true;
                        $message = _t('USERS_REGISTER_RANDOM_SENDMAIL_FAILED', $xss->filter($user_email));
                    }
                }
            }
        }

        //Send an email to website owner
        $mail->ResetValues();
        if (!$delete_user && ($notification == 'true' || $activation == 'admin')) {
            $tpl = new Jaws_Template('gadgets/Users/templates/');
            $tpl->Load('AdminNotification.txt');
            $tpl->SetBlock('Notification');
            $tpl->SetVariable('say_hello', _t('USERS_REGISTER_HELLO', $site_author));
            $tpl->SetVariable('message', _t('USERS_REGISTER_ADMIN_MAIL_MSG'));
            $tpl->SetVariable('lbl_username', _t('USERS_USERS_USERNAME'));
            $tpl->SetVariable('username', $xss->filter($username));
            $tpl->SetVariable('lbl_nickname', _t('USERS_USERS_NICKNAME'));
            $tpl->SetVariable('nickname', $xss->filter($nickname));
            $tpl->SetVariable('lbl_email', _t('GLOBAL_EMAIL'));
            $tpl->SetVariable('email', $xss->filter($user_email));
            $tpl->SetVariable('lbl_ip', _t('GLOBAL_IP'));
            $tpl->SetVariable('ip', $_SERVER['REMOTE_ADDR']);
            if ($activation == 'admin') {
                $secretKey = md5(uniqid(rand(), true)) . time() . floor(microtime()*1000);
                $result = $jUser->UpdateVerificationKey($user_id, $secretKey);
                if ($result === true) {
                    $tpl->SetBlock('Notification/Activation');
                    $tpl->SetVariable('lbl_activation_link', _t('USERS_ACTIVATE_ACTIVATION_LINK'));
                    $tpl->SetVariable('activation_link', $GLOBALS['app']->Map->GetURLFor('Users', 'ActivateUser',
                                                                array('key' => $secretKey), true, 'site_url'));
                    $tpl->ParseBlock('Notification/Activation');
                }
            }
            $tpl->SetVariable('thanks', _t('GLOBAL_THANKS'));
            $tpl->SetVariable('site-name', $site_name);
            $tpl->SetVariable('site-url', $site_url);
            $tpl->ParseBlock('Notification');
            $body = $tpl->Get();

            if (!$delete_user) {
                $subject = _t('USERS_REGISTER_SUBJECT', $site_name);
                $mail->SetFrom();
                $mail->AddRecipient();
                $mail->SetSubject($subject);
                $mail->SetBody(Jaws_Gadget::ParseText($body, 'Users'));
                $mresult = $mail->send();
                if (Jaws_Error::IsError($mresult) && $activation == 'admin') {
                    // do nothing
                    //$delete_user = true;
                    //$message = _t('USERS_ACTIVATE_NOT_ACTIVATED_SENDMAIL', $xss->filter($user_email));
                }
            }
        }

        if ($delete_user) {
            $jUser->DeleteUser($user_id);
            return $message;
        }

        return true;
    }

    /**
     * Checks if user/email are valid, if they are generates a recovery secret
     * key and sends it to the user
     *
     * @access  public
     * @param   string  $user_email Email
     * @return  boolean Success/Failure
     */
    function SendRecoveryKey($user_email)
    {
        require_once JAWS_PATH . 'include/Jaws/User.php';
        $userModel = new Jaws_User;
        $uInfos = $userModel->GetUserInfoByEmail($user_email);
        if (Jaws_Error::IsError($uInfos)) {
            return $uInfos;
        }

        if (empty($uInfos)) {
            return new Jaws_Error(_t('USERS_USER_NOT_EXIST'));                
        }

        foreach($uInfos as $info) {
            $secretKey = md5(uniqid(rand(), true)) . time() . floor(microtime()*1000);
            $result = $userModel->UpdateVerificationKey($info['id'], $secretKey);
            if ($result === true) {
                $xss = $GLOBALS['app']->loadClass('XSS', 'Jaws_XSS');

                $site_url    = $GLOBALS['app']->getSiteURL('/');
                $site_name   = $GLOBALS['app']->Registry->Get('/config/site_name');

                $tpl = new Jaws_Template('gadgets/Users/templates/');
                $tpl->Load('RecoverPassword.txt');
                $tpl->SetBlock('RecoverPassword');
                $tpl->SetVariable('lbl_username', _t('USERS_USERS_USERNAME'));
                $tpl->SetVariable('username', $xss->filter($info['username']));
                $tpl->SetVariable('nickname', $xss->filter($info['nickname']));
                $tpl->SetVariable('message', _t('USERS_FORGOT_MAIL_MESSAGE'));
                $tpl->SetVariable('lbl_url', _t('GLOBAL_URL'));
                $tpl->SetVariable('url',
                                  $GLOBALS['app']->Map->GetURLFor('Users', 'ChangePassword',
                                                                  array('key' => $secretKey), true, 'site_url'));
                $tpl->SetVariable('lbl_ip', _t('GLOBAL_IP'));
                $tpl->SetVariable('ip', $_SERVER['REMOTE_ADDR']);
                $tpl->SetVariable('thanks', _t('GLOBAL_THANKS'));
                $tpl->SetVariable('site-name', $site_name);
                $tpl->SetVariable('site-url', $site_url);
                $tpl->ParseBlock('RecoverPassword');

                $message = $tpl->Get();            
                $subject = _t('USERS_FORGOT_REMEMBER', $site_name);

                require_once JAWS_PATH . 'include/Jaws/Mail.php';
                $mail = new Jaws_Mail;
                $mail->SetFrom();
                $mail->AddRecipient($user_email);
                $mail->SetSubject($subject);
                $mail->SetBody(Jaws_Gadget::ParseText($message, 'Users'));
                $mresult = $mail->send();
                if (Jaws_Error::IsError($mresult)) {
                    return new Jaws_Error(_t('USERS_FORGOT_ERROR_SENDING_MAIL'));
                } else {
                    return true;
                }
            } else {
                return new Jaws_Error(_t('USERS_FORGOT_ERROR_SENDING_MAIL'));
            }
        }
    }

    /**
     * Changes a enabled from a given key
     *
     * @access  public
     * @param   string   $key   Recovery key
     * @return  boolean
     */
    function ActivateUser($key)
    {
        require_once JAWS_PATH . 'include/Jaws/User.php';
        
        $jUser = new Jaws_User;
        if ($id = $jUser->GetIDByVerificationKey($key)) {
            $info = $jUser->GetUser((int)$id);

            $res = $jUser->UpdateVerificationKey($id);
            if (Jaws_Error::IsError($res)) {
                return $res;
            }

            $res = $jUser->UpdateUser($id,
                                       $info['username'],
                                       $info['nickname'],
                                       $info['email'],
                                       null, // password
                                       null, // superadmin
                                       1);
            if (Jaws_Error::IsError($res)) {
                return $res;
            }

            $site_url  = $GLOBALS['app']->getSiteURL('/');
            $site_name = $GLOBALS['app']->Registry->Get('/config/site_name');

            $tpl = new Jaws_Template('gadgets/Users/templates/');
            $tpl->Load('UserNotification.txt');
            $tpl->SetBlock('Notification');
            $tpl->SetVariable('say_hello', _t('USERS_REGISTER_HELLO', $info['nickname']));
            $tpl->SetVariable('message', _t('USERS_ACTIVATE_ACTIVATED_MAIL_MSG'));
            if ($GLOBALS['app']->Registry->Get('/config/anon_activation') == 'user') {
                $tpl->SetBlock('Notification/IP');
                $tpl->SetVariable('lbl_ip', _t('GLOBAL_IP'));
                $tpl->SetVariable('ip', $_SERVER['REMOTE_ADDR']);
                $tpl->ParseBlock('Notification/IP');
            }

            $tpl->SetVariable('lbl_username', _t('USERS_USERS_USERNAME'));
            $tpl->SetVariable('username', $info['username']);

            $tpl->SetVariable('thanks', _t('GLOBAL_THANKS'));
            $tpl->SetVariable('site-name', $site_name);
            $tpl->SetVariable('site-url', $site_url);
            $tpl->ParseBlock('Notification');

            $body = $tpl->Get();
            $subject = _t('USERS_REGISTER_SUBJECT', $site_name);

            require_once JAWS_PATH . 'include/Jaws/Mail.php';
            $mail = new Jaws_Mail;
            $mail->SetFrom();
            $mail->AddRecipient($info['email']);
            $mail->SetSubject($subject);
            $mail->SetBody(Jaws_Gadget::ParseText($body, 'Users'));
            $mresult = $mail->send();
            if (Jaws_Error::IsError($mresult)) {
                // do nothing
            }
            return true;
        } else {
            return new Jaws_Error(_t('USERS_ACTIVATION_KEY_NOT_VALID'));
        }
    }

}