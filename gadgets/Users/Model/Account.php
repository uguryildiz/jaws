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
class Users_Model_Account extends Jaws_Gadget_Model
{
    /**
     * Updates user profile
     *
     * @access  public
     * @param   int      $uid       User ID
     * @param   string   $username  Username
     * @param   string   $nickname  User's display name
     * @param   string   $email     User's email
     * @param   string   $password  Password
     * @return  mixed    True on success or Jaws_Error on failure
     */
    function UpdateAccount($uid, $username, $nickname, $email, $password)
    {
        require_once JAWS_PATH . 'include/Jaws/User.php';
        $jUser  = new Jaws_User;
        
        $result = $jUser->UpdateUser($uid,
                                     $username,
                                     $nickname,
                                     $email,
                                     $password);
        return $result;
    }

    /**
     * Changes a password from a given key
     *
     * @access  public
     * @param   string   $key   Recovery key
     * @return  mixed    True on success or Jaws_Error on failure
     */
    function ChangePassword($key)
    {
        require_once JAWS_PATH . 'include/Jaws/User.php';
        $jUser = new Jaws_User;
        $user = $jUser->GetUserByPasswordVerifyKey($key);
        if (Jaws_Error::IsError($user) || empty($user)) {
            return false;
        }

        // generate new password
        include_once 'Text/Password.php';
        $password = Text_Password::create(8, 'pronounceable', 'alphanumeric');

        $res = $jUser->UpdateUser(
            $user['id'],
            $user['username'],
            $user['nickname'],
            $user['email'],
            $password
        );
        if (Jaws_Error::IsError($res)) {
            return $res;
        }

        $site_url  = $GLOBALS['app']->getSiteURL('/');
        $site_name = $this->gadget->GetRegistry('site_name', 'Settings');

        $tpl = new Jaws_Template('gadgets/Users/templates/');
        $tpl->Load('NewPassword.txt');
        $tpl->SetBlock('NewPassword');
        $tpl->SetVariable('username', $user['username']);
        $tpl->SetVariable('nickname', $user['nickname']);
        $tpl->SetVariable('password', $password);
        $tpl->SetVariable('message',  _t('USERS_FORGOT_PASSWORD_CHANGED_MESSAGE', $user['username']));
        $tpl->SetVariable('lbl_password', _t('USERS_USERS_PASSWORD'));
        $tpl->SetVariable('lbl_username', _t('USERS_USERS_USERNAME'));
        $tpl->SetVariable('thanks', _t('GLOBAL_THANKS'));
        $tpl->SetVariable('site-name', $site_name);
        $tpl->SetVariable('site-url',  $site_url);
        $tpl->ParseBlock('NewPassword');

        $message = $tpl->Get();            
        $subject = _t('USERS_FORGOT_PASSWORD_CHANGED_SUBJECT');

        require_once JAWS_PATH . 'include/Jaws/Mail.php';
        $mail = new Jaws_Mail;
        $mail->SetFrom();
        $mail->AddRecipient($user['email']);
        $mail->SetSubject($subject);
        $mail->SetBody($this->gadget->ParseText($message, 'Users'));
        $mresult = $mail->send();
        if (Jaws_Error::IsError($mresult)) {
            return new Jaws_Error(_t('USERS_FORGOT_ERROR_SENDING_MAIL'));
        }

        return true;
    }

}