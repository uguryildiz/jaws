<?php
/**
 * Users Core Gadget
 *
 * @category   Gadget
 * @package    Users
 * @author     Jonathan Hernandez <ion@suavizado.com>
 * @author     Ali Fazelzadeh <afz@php.net>
 * @copyright  2004-2012 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/lesser.html
 */
class Users_Actions_Registration extends UsersHTML
{
    /**
     * Tells the user the registation process is done
     *
     * @access  public
     * @return  string  XHTML of template
     */
    function Registered()
    {
        // Load the template
        $tpl = new Jaws_Template('gadgets/Users/templates/');
        $tpl->Load('Registered.html');
        $tpl->SetBlock('registered');
        $tpl->SetVariable('title', _t('USERS_REGISTER_REGISTERED'));

        switch ($GLOBALS['app']->Registry->Get('/config/anon_activation')) {
            case 'admin':
                $message = _t('USERS_ACTIVATE_ACTIVATION_BY_ADMIN_MSG');
                break;
            case 'user':
                $message = _t('USERS_ACTIVATE_ACTIVATION_BY_USER_MSG');
                break;
            default:
                $message = _t('USERS_REGISTER_REGISTERED_MSG', $this->GetURLFor('LoginBox'));
        }

        $tpl->SetVariable('registered_msg', $message);
        $tpl->ParseBlock('registered');
        return $tpl->Get();
    }

    /**
     * Register the user
     *
     * @access  public
     */
    function DoRegister()
    {
        if ($GLOBALS['app']->Registry->Get('/config/anon_register') !== 'true') {
            return parent::_404();
        }

        $result  = '';
        $request =& Jaws_Request::getInstance();
        $post = $request->get(array('username', 'email', 'nickname', 'password', 'password_check',
                                    'fname', 'lname', 'gender', 'dob_year', 'dob_month', 'dob_day','url'),
                              'post');

        $mPolicy = $GLOBALS['app']->LoadGadget('Policy', 'Model');
        $resCheck = $mPolicy->CheckCaptcha();
        if (Jaws_Error::IsError($resCheck)) {
            $result = $resCheck->getMessage();
        }

        if (empty($result)) {
            if ($post['password'] !== $post['password_check']) {
                $result = _t('USERS_USERS_PASSWORDS_DONT_MATCH');
            } else {
                $dob  = null;
                if (!empty($post['dob_year']) && !empty($post['dob_year']) && !empty($post['dob_year'])) {
                    $date = $GLOBALS['app']->loadDate();
                    $dob  = $date->ToBaseDate($post['dob_year'], $post['dob_month'], $post['dob_day']);
                    $dob  = date('Y-m-d H:i:s', $dob['timestamp']);
                }

                $uModel = $GLOBALS['app']->LoadGadget('Users', 'Model', 'Registration');
                $result = $uModel->CreateUser($post['username'],
                                              $post['email'],
                                              $post['nickname'],
                                              $post['fname'],
                                              $post['lname'],
                                              $post['gender'],
                                              $dob,
                                              $post['url'],
                                              $post['password'],
                                              $GLOBALS['app']->Registry->Get('/config/anon_group'));
                if ($result === true) {
                    Jaws_Header::Location($this->GetURLFor('Registered'));
                }
            }
        }

        $GLOBALS['app']->Session->PushSimpleResponse($result, 'Users.Register');

        // unset unnecessary registration data
        unset($post['password'],
              $post['password_check'],
              $post['random_password']);
        $GLOBALS['app']->Session->PushSimpleResponse($post, 'Users.Register.Data');

        Jaws_Header::Location($this->GetURLFor('Registration'));
    }

    /**
     * Prepares a single form to get registered
     *
     * @access  public
     * @return  string  XHTML of template
     */
    function Registration()
    {
        if ($GLOBALS['app']->Session->Logged()) {
            Jaws_Header::Location('');
        }

        if ($GLOBALS['app']->Registry->Get('/config/anon_register') !== 'true') {
            return parent::_404();
        }

        // Load the template
        $tpl = new Jaws_Template('gadgets/Users/templates/');
        $tpl->Load('Register.html');
        $tpl->SetBlock('register');
        $tpl->SetVariable('title', _t('USERS_REGISTER'));
        $tpl->SetVariable('base_script', BASE_SCRIPT);

        $tpl->SetVariable('lbl_account_info',  _t('USERS_ACCOUNT_INFO'));
        $tpl->SetVariable('lbl_username',      _t('USERS_USERS_USERNAME'));
        $tpl->SetVariable('validusernames',    _t('USERS_REGISTER_VALID_USERNAMES'));
        $tpl->SetVariable('lbl_email',         _t('GLOBAL_EMAIL'));
        $tpl->SetVariable('lbl_url',           _t('GLOBAL_URL'));
        $tpl->SetVariable('lbl_nickname',         _t('USERS_USERS_NICKNAME'));
        $tpl->SetVariable('lbl_password',      _t('USERS_USERS_PASSWORD'));
        $tpl->SetVariable('sendpassword',      _t('USERS_USERS_SEND_AUTO_PASSWORD'));
        $tpl->SetVariable('lbl_checkpassword', _t('USERS_USERS_PASSWORD_VERIFY'));

        $tpl->SetVariable('lbl_personal_info', _t('USERS_PERSONAL_INFO'));
        $tpl->SetVariable('lbl_fname',         _t('USERS_USERS_FIRSTNAME'));
        $tpl->SetVariable('lbl_lname',         _t('USERS_USERS_LASTNAME'));
        $tpl->SetVariable('lbl_gender',        _t('USERS_USERS_GENDER'));
        $tpl->SetVariable('gender_male',       _t('USERS_USERS_MALE'));
        $tpl->SetVariable('gender_female',     _t('USERS_USERS_FEMALE'));
        $tpl->SetVariable('lbl_dob',           _t('USERS_USERS_BIRTHDAY'));
        $tpl->SetVariable('dob_sample',        _t('USERS_USERS_BIRTHDAY_SAMPLE'));

        if ($post_data = $GLOBALS['app']->Session->PopSimpleResponse('Users.Register.Data')) {
            $tpl->SetVariable('username',  $post_data['username']);
            $tpl->SetVariable('email',     $post_data['email']);
            $tpl->SetVariable('url',       $post_data['url']);
            $tpl->SetVariable('nickname',  $post_data['nickname']);
            $tpl->SetVariable('fname',     $post_data['fname']);
            $tpl->SetVariable('lname',     $post_data['lname']);
            $tpl->SetVariable('dob_year',  $post_data['dob_year']);
            $tpl->SetVariable('dob_month', $post_data['dob_month']);
            $tpl->SetVariable('dob_day',   $post_data['dob_day']);
            $tpl->SetVariable("selected_gender_{$post_data['gender']}", 'selected="selected"');
        } else {
            $tpl->SetVariable('url', 'http://');
            $tpl->SetVariable("selected_gender_0", 'selected="selected"');
        }

        $tpl->SetVariable('register', _t('USERS_REGISTER'));
        $mPolicy = $GLOBALS['app']->LoadGadget('Policy', 'Model');
        if ($mPolicy->LoadCaptcha($captcha, $entry, $label, $description)) {
            $tpl->SetBlock('register/captcha');
            $tpl->SetVariable('lbl_captcha', $label);
            $tpl->SetVariable('captcha', $captcha);
            if (!empty($entry)) {
                $tpl->SetVariable('captchavalue', $entry);
            }
            $tpl->SetVariable('captcha_msg', $description);
            $tpl->ParseBlock('register/captcha');
        }

        if ($response = $GLOBALS['app']->Session->PopSimpleResponse('Users.Register')) {
            $tpl->SetBlock('register/response');
            $tpl->SetVariable('msg', $response);
            $tpl->ParseBlock('register/response');
        }

        $tpl->ParseBlock('register');
        return $tpl->Get();
    }

    /**
     * Activate the user
     *
     * @access  public
     */
    function ActivateUser()
    {
        if ($GLOBALS['app']->Session->Logged() && !$GLOBALS['app']->Session->IsSuperAdmin()) {
            Jaws_Header::Location('');
        }

        if ($GLOBALS['app']->Registry->Get('/config/anon_register') !== 'true') {
            return parent::_404();
        }

        $request =& Jaws_Request::getInstance();
        $key     = $request->get('key', 'get');

        $model  = $GLOBALS['app']->LoadGadget('Users', 'Model', 'Registration');
        $result = $model->ActivateUser($key);
        if (Jaws_Error::IsError($result)) {
            return $result->getMessage();
        }

        if ($GLOBALS['app']->Registry->Get('/config/anon_activation') == 'user') {
            return _t('USERS_ACTIVATE_ACTIVATED_BY_USER_MSG', $this->GetURLFor('LoginBox'));
        } else {
            return _t('USERS_ACTIVATE_ACTIVATED_BY_ADMIN_MSG');
        }
    }

}