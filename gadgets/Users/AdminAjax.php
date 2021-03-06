<?php
/**
 * Users AJAX API
 *
 * @category   Ajax
 * @package    Users
 * @author     Pablo Fischer <pablo@pablo.com.mx>
 * @author     Ali Fazelzadeh <afz@php.net>
 * @copyright  2005-2012 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/lesser.html
 */
class Users_AdminAjax extends Jaws_Gadget_HTML
{
    /**
     * User model
     *
     * @var     object
     * @access  private
     */
    var $_UserModel;

    /**
     * Constructor
     *
     * @access  public
     * @param   object $gadget Jaws_Gadget object
     * @return  void
     */
    function Users_AdminAjax($gadget)
    {
        parent::Jaws_Gadget_HTML($gadget);
        $this->_Model = $this->gadget->load('Model')->loadModel('AdminModel');
        require_once JAWS_PATH . 'include/Jaws/User.php';
        $this->_UserModel = new Jaws_User();
    }

    /**
     * Gets users's profile
     *
     * @access  public
     * @param   int     $uid            User ID
     * @param   bool    $account        Include account information
     * @param   bool    $personal       Include personal information
     * @param   bool    $preferences    Include user preferences information
     * @param   bool    $extra          Include user extra information
     * @return  array   User information
     */
    function GetUser($uid, $account = true, $personal = false, $preferences = false, $extra = false)
    {
        $profile = $this->_UserModel->GetUser((int)$uid, $account, $personal, $preferences, $extra);
        if (Jaws_Error::IsError($profile)) {
            return array();
        }

        $objDate = $GLOBALS['app']->loadDate();
        if ($account) {
            if (!empty($profile['expiry_date'])) {
                $profile['expiry_date'] = $objDate->Format($profile['expiry_date'], 'Y-m-d H:i:s');
            } else {
                $profile['expiry_date'] = '';
            }
        }

        if ($personal) {
            if (empty($profile['avatar'])) {
                $profile['avatar'] = $GLOBALS['app']->getSiteURL('/gadgets/Users/images/avatar.png');
            } else {
                $profile['avatar'] = $GLOBALS['app']->getDataURL(). 'avatar/'. $profile['avatar'];
            }

            if (!empty($profile['dob'])) {
                $profile['dob'] = $objDate->Format($profile['dob'], 'Y-m-d');
            } else {
                $profile['dob'] = '';
            }
        }

        return $profile;
    }

    /**
     * Gets list of users according to the given criteria
     *
     * @access  public
     * @param   string  $group      User group
     * @param   bool    $superadmin Is superadmin
     * @param   int     $status     User status
     * @param   string  $term       Term to search
     * @param   string  $orderBy    Order type of result list
     * @param   int     $offset     Data offset
     * @return  array   Users list
     */
    function GetUsers($group, $superadmin, $status, $term, $orderBy, $offset)
    {
        $superadmin = ($superadmin == -1)? null : (bool)$superadmin;
        if (!$GLOBALS['app']->Session->IsSuperAdmin()) {
            $superadmin = false;
        }

        $group  = ($group  == -1)? false : (int)$group;
        $status = ($status == -1)? null  : (int)$status;
        if (!is_numeric($offset)) {
            $offset = null;
        }

        $usrHTML = $GLOBALS['app']->LoadGadget('Users', 'AdminHTML', 'Users');
        return $usrHTML->GetUsers($group, $superadmin, $status, $term, $orderBy, $offset);
    }

    /**
     * Gets list of online users
     *
     * @access  public
     * @return  array   Online users list
     */
    function GetOnlineUsers()
    {
        $usrHTML = $GLOBALS['app']->LoadGadget('Users', 'AdminHTML', 'OnlineUsers');
        return $usrHTML->GetOnlineUsers();
    }

    /**
     * Gets number of users
     *
     * @access  public
     * @param   string  $group      User group
     * @param   bool    $superadmin Is superadmin
     * @param   int     $status     User status
     * @param   string  $term       Search term(searched in username, nickname and email)
     * @return  int     Number of users
     */
    function GetUsersCount($group, $superadmin, $status, $term)
    {
        $superadmin = ($superadmin == -1)? null : (bool)$superadmin;
        if (!$GLOBALS['app']->Session->IsSuperAdmin()) {
            $superadmin = false;
        }

        $group  = ($group  == -1)? false : (int)$group;
        $status = ($status == -1)? null  : (int)$status;

        return $this->_UserModel->GetUsersCount($group, $superadmin, $status, $term);
    }

    /**
     * Adds a new user
     *
     * @access  public
     * @param   string  $username   Username
     * @param   string  $password   Password
     * @param   string  $nickname   User's display name
     * @param   string  $email      User's email
     * @param   int     $superadmin User's type (superadmin or normal)
     * @param   int     $concurrent_logins  Concurrent logins limitation
     * @param   string  $expiry_date        Expiry date
     * @param   int     $status     User's status (null: all users, 0: disabled, 1: activated, 2: not verified)
     * @return  array   Response array (notice or error)
     */
    function AddUser($username, $password, $nickname, $email, $superadmin, $concurrent_logins,
                     $expiry_date, $status)
    {
        $this->gadget->CheckPermission('ManageUsers');
        if ($this->gadget->GetRegistry('crypt_enabled', 'Policy') == 'true') {
            require_once JAWS_PATH . 'include/Jaws/Crypt.php';
            $JCrypt = new Jaws_Crypt();
            $JCrypt->Init();
            $password = $JCrypt->decrypt($password);
            if (($password === false) || Jaws_Error::isError($password)) {
                $password = null;
            }
        }

        $status     = (int)$status;
        $superadmin = $GLOBALS['app']->Session->IsSuperAdmin()? (bool)$superadmin : false;
        $res = $this->_UserModel->AddUser($username,
                                          $nickname,
                                          $email,
                                          $password,
                                          $superadmin,
                                          $status,
                                          $concurrent_logins,
                                          null,
                                          $expiry_date);
        if (Jaws_Error::isError($res)) {
            $GLOBALS['app']->Session->PushLastResponse($res->getMessage(),
                                                       RESPONSE_ERROR);
        } else {
            $guid = $this->gadget->GetRegistry('anon_group');
            if (!empty($guid)) {
                $this->_UserModel->AddUserToGroup($res, (int)$guid);
            }
            $GLOBALS['app']->Session->PushLastResponse(_t('USERS_USERS_CREATED', $username),
                                                       RESPONSE_NOTICE);
        }

        return $GLOBALS['app']->Session->PopLastResponse();
    }

    /**
     * Updates user information
     *
     * @access  public
     * @param   int     $uid        User ID
     * @param   string  $username   Username
     * @param   string  $password   Password
     * @param   string  $nickname   User's display name
     * @param   string  $email      User's email
     * @param   int     $superadmin User's type (ADMIN or NORMAL)
     * @param   int     $concurrent_logins   Concurrent logins limitation
     * @param   string  $expiry_date  Expiry date
     * @param   int     $status     user's status (null: all users, 0: disabled, 1: activated, 2: not verified)
     * @return  array   Response array (notice or error)
     */
    function UpdateUser($uid, $username, $password, $nickname, $email, $superadmin, $concurrent_logins,
                        $expiry_date, $status)
    {
        $this->gadget->CheckPermission('ManageUsers');
        if ($this->gadget->GetRegistry('crypt_enabled', 'Policy') == 'true') {
            require_once JAWS_PATH . 'include/Jaws/Crypt.php';
            $JCrypt = new Jaws_Crypt();
            $JCrypt->Init();
            $password = $JCrypt->decrypt($password);
            if (($password === false) || Jaws_Error::isError($password)) {
                $password = null;
            }
        }

        if ($uid == $GLOBALS['app']->Session->GetAttribute('user')) {
            $status      = null;
            $superadmin  = null;
            $expiry_date = null;
        } else {
            $status = (int)$status;
            if (!$GLOBALS['app']->Session->IsSuperAdmin()) {
                $status      = null;
                $superadmin  = null;
                $expiry_date = null;
            }
        }
        $res = $this->_UserModel->UpdateUser($uid,
                                             $username,
                                             $nickname,
                                             $email,
                                             $password,
                                             $superadmin,
                                             $status,
                                             $concurrent_logins,
                                             null,
                                             $expiry_date);
        if (Jaws_Error::isError($res)) {
            $GLOBALS['app']->Session->PushLastResponse($res->getMessage(), RESPONSE_ERROR);
        } else {
            $GLOBALS['app']->Session->PushLastResponse(_t('USERS_USERS_UPDATED', $username), RESPONSE_NOTICE);
        }

        return $GLOBALS['app']->Session->PopLastResponse();
    }

    /**
     * Deletes the user
     *
     * @access  public
     * @param   int     $uid   User ID
     * @return  array   Response array (notice or error)
     */
    function DeleteUser($uid)
    {
        $this->gadget->CheckPermission('ManageUsers');
        if ($uid == $GLOBALS['app']->Session->GetAttribute('user')) {
            $GLOBALS['app']->Session->PushLastResponse(_t('USERS_USERS_CANT_DELETE_SELF'),
                                                       RESPONSE_ERROR);
        } else {
            $profile = $this->_UserModel->GetUser((int)$uid);
            if (!$GLOBALS['app']->Session->IsSuperAdmin() && $profile['superadmin']) {
                $GLOBALS['app']->Session->PushLastResponse(_t('USERS_USERS_CANT_DELETE', $profile['username']),
                                                           RESPONSE_ERROR);
                return $GLOBALS['app']->Session->PopLastResponse();
            }

            if (!$this->_UserModel->DeleteUser($uid)) {
                $GLOBALS['app']->Session->PushLastResponse(_t('USERS_USERS_CANT_DELETE', $profile['username']),
                                                           RESPONSE_ERROR);
            } else {
                $GLOBALS['app']->Session->PushLastResponse(_t('USERS_USER_DELETED', $profile['username']),
                                                           RESPONSE_NOTICE);
            }
        }
        return $GLOBALS['app']->Session->PopLastResponse();
    }

    /**
     * Delete a session
     *
     * @access  public
     * @param   int     $sid    Session ID
     * @return  array   Response array (notice or error)
     */
    function DeleteSession($sid)
    {
        $this->gadget->CheckPermission('ManageOnlineUsers');
        if ($GLOBALS['app']->Session->Delete($sid)) {
            $GLOBALS['app']->Session->PushLastResponse(
                _t('USERS_ONLINE_SESSION_DELETED'),
                RESPONSE_NOTICE
            );
        } else {
            $GLOBALS['app']->Session->PushLastResponse(
                _t('USERS_ONLINE_SESSION_NOT_DELETED'),
                RESPONSE_ERROR
            );
        }

        return $GLOBALS['app']->Session->PopLastResponse();
    }

    /**
     * Block IP address
     *
     * @access  public
     * @param   string  $ip
     * @return  array   Response array (notice or error)
     */
    function IPBlock($ip)
    {
        $this->gadget->CheckPermission('ManageOnlineUsers');
        $this->gadget->CheckPermission('ManageIPs');

        $mPolicy = $GLOBALS['app']->LoadGadget('Policy', 'AdminModel');
        if ($mPolicy->AddIPRange($ip, null, true)) {
            $GLOBALS['app']->Session->PushLastResponse(
                _t('POLICY_RESPONSE_IP_ADDED'),
                RESPONSE_NOTICE
            );
        } else {
            $GLOBALS['app']->Session->PushLastResponse(
                _t('POLICY_RESPONSE_IP_NOT_ADDED'),
                RESPONSE_ERROR
            );
        }

        return $GLOBALS['app']->Session->PopLastResponse();
    }

    /**
     * Block agent
     *
     * @access  public
     * @param   string  $agent
     * @return  array   Response array (notice or error)
     */
    function AgentBlock($agent)
    {
        $this->gadget->CheckPermission('ManageOnlineUsers');
        $this->gadget->CheckPermission('ManageAgents');

        $mPolicy = $GLOBALS['app']->LoadGadget('Policy', 'AdminModel');
        if ($mPolicy->AddAgent($agent, true)) {
            $GLOBALS['app']->Session->PushLastResponse(
                _t('POLICY_RESPONSE_AGENT_ADDED'),
                RESPONSE_NOTICE
            );
        } else {
            $GLOBALS['app']->Session->PushLastResponse(
                _t('POLICY_RESPONSE_AGENT_NOT_ADDEDD'),
                RESPONSE_ERROR
            );
        }

        return $GLOBALS['app']->Session->PopLastResponse();
    }

    /**
     * Updates modified user ACL keys
     *
     * @access  public
     * @param   int     $uid    User ID
     * @param   array   $keys   ACL Keys
     * @return  array   Response array (notice or error)
     */
    function UpdateUserACL($uid, $keys)
    {
        $this->gadget->CheckPermission('ManageUserACLs');
        $uModel = $GLOBALS['app']->LoadGadget('Users', 'AdminModel', 'UserACL');
        $res = $uModel->UpdateUserACL($uid, $keys);
        if (Jaws_Error::IsError($res)) {
            $GLOBALS['app']->Session->PushLastResponse($res->GetMessage(),
                                                       RESPONSE_ERROR);
        } else {
            $GLOBALS['app']->Session->PushLastResponse(_t('USERS_USERS_ACL_UPDATED'),
                                                       RESPONSE_NOTICE);
        }

        return $GLOBALS['app']->Session->PopLastResponse();
    }

    /**
     * Updates ACL keys of the group
     *
     * @access  public
     * @param   int     $guid   Group ID
     * @param   array   $keys   ACL Keys
     * @return  array   Response array (notice or error)
     */
    function UpdateGroupACL($guid, $keys)
    {
        $this->gadget->CheckPermission('ManageGroupACLs');
        $uModel = $GLOBALS['app']->LoadGadget('Users', 'AdminModel', 'GroupACL');
        $res = $uModel->UpdateGroupACL($guid, $keys);
        if (Jaws_Error::IsError($res)) {
            $GLOBALS['app']->Session->PushLastResponse($res->GetMessage(),
                                                       RESPONSE_ERROR);
        } else {
            $GLOBALS['app']->Session->PushLastResponse(_t('USERS_GROUPS_ACL_UPDATED'),
                                                       RESPONSE_NOTICE);
        }

        return $GLOBALS['app']->Session->PopLastResponse();
    }

    /**
     * Adds a user to groups
     *
     * @access  public
     * @param   int     $uid    User ID
     * @param   array   $groups Array with group id
     * @return  array   Response array (notice or error)
     */
    function AddUserToGroups($uid, $groups)
    {
        $this->gadget->CheckPermission('ManageGroups');
        $oldGroups = $this->_UserModel->GetGroupsOfUser((int)$uid);
        if (!Jaws_Error::IsError($oldGroups)) {
            foreach ($groups as $group) {
                if (false === $gIndex = array_search($group, $oldGroups)) {
                    $this->_UserModel->AddUserToGroup($uid, $group);
                } else {
                    unset($oldGroups[$gIndex]);
                }
            }

            // delete remainder groups
            foreach ($oldGroups as $group) {
                $this->_UserModel->DeleteUserFromGroup($uid, $group);
            }

            $GLOBALS['app']->Session->PushLastResponse(_t('USERS_GROUPS_UPDATED_USERS'),
                                                       RESPONSE_NOTICE);
        } else {
            $GLOBALS['app']->Session->PushLastResponse($res->GetMessage(),
                                                       RESPONSE_ERROR);
        }

        return $GLOBALS['app']->Session->PopLastResponse();
    }

    /**
     * Adds a group of users(by their IDs) to a certain group
     *
     * @access  public
     * @param   int     $guid  Group ID
     * @param   array   $users Array with user ID
     * @return  array   Response array (notice or error)
     */
    function AddUsersToGroup($guid, $users)
    {
        $this->gadget->CheckPermission('ManageGroups');
        $uModel = $GLOBALS['app']->LoadGadget('Users', 'AdminModel', 'UsersGroup');
        $res = $uModel->AddUsersToGroup($guid, $users);
        if (Jaws_Error::IsError($res)) {
            $GLOBALS['app']->Session->PushLastResponse($res->GetMessage(), RESPONSE_ERROR);
        } else {
            $GLOBALS['app']->Session->PushLastResponse(_t('USERS_GROUPS_UPDATED_USERS'), RESPONSE_NOTICE);
        }
        return $GLOBALS['app']->Session->PopLastResponse();
    }

    /**
     * Updates User gadget settings
     *
     * @access  public
     * @param   string  $method     Authentication method
     * @param   string  $anon       Anonymous users can auto-register
     * @param   string  $repetitive Anonymous can register by repetitive email
     * @param   string  $act        Activation type
     * @param   int     $group      Default group of anonymous registered user
     * @param   string  $recover    Users can recover their passwords
     * @return  array   Response array (notice or error)
     */
    function SaveSettings($method, $anon, $repetitive, $act, $group, $recover)
    {
        $this->gadget->CheckPermission('ManageProperties');
        $uModel = $GLOBALS['app']->LoadGadget('Users', 'AdminModel', 'Settings');
        $res = $uModel->SaveSettings($method, $anon, $repetitive, $act, $group, $recover);
        if (Jaws_Error::IsError($res)) {
            $GLOBALS['app']->Session->PushLastResponse($res->GetMessage(), RESPONSE_ERROR);
        } else {
            $GLOBALS['app']->Session->PushLastResponse(_t('USERS_PROPERTIES_UPDATED'), RESPONSE_NOTICE);
        }
        return $GLOBALS['app']->Session->PopLastResponse();
    }

    /**
     * Returns an array with the ACL keys of the user
     *
     * @access  public
     * @param   int     $uid    User ID
     * @return  mixed   Array of ACL keys or false on failure
     */
    function GetUserACLKeys($uid)
    {
        $this->gadget->CheckPermission('ManageUserACLs');
        $profile = $this->_UserModel->GetUser((int)$uid);
        if (isset($profile['username'])) {
            $uModel = $GLOBALS['app']->LoadGadget('Users', 'AdminModel', 'UserACL');
            $acl = $uModel->GetUserACLKeys($profile['username']);
            return $acl;
        }
        return false;
    }

    /**
     * Returns an array with the ACL keys of the group
     *
     * @access  public
     * @param   int     $guid   Group ID
     * @return  mixed   Array of ACL keys or false on failure
     */
    function GetGroupACLKeys($guid)
    {
        $this->gadget->CheckPermission('ManageGroupACLs');
        $profile = $this->_UserModel->GetGroup((int)$guid);
        if (isset($profile['name'])) {
            $uModel = $GLOBALS['app']->LoadGadget('Users', 'AdminModel', 'GroupACL');
            $acl = $uModel->GetGroupACLKeys($guid);
            return $acl;
        }
        return false;
    }

    /**
     * Updates my account
     *
     * @access  public
     * @param   string  $uid        User ID
     * @param   string  $username   Username
     * @param   string  $password   Password
     * @param   string  $nickname   User display name
     * @param   string  $email      User email
     * @return  array   Response array (notice or error)
     */
    function UpdateMyAccount($uid, $username, $password, $nickname, $email)
    {
        $this->gadget->CheckPermission('EditUserName,EditUserNickname,EditUserEmail,EditUserPassword', false);

        if ($this->gadget->GetRegistry('crypt_enabled', 'Policy') == 'true') {
            require_once JAWS_PATH . 'include/Jaws/Crypt.php';
            $JCrypt = new Jaws_Crypt();
            $JCrypt->Init();
            $password = $JCrypt->decrypt($password);
            if (($password === false) || Jaws_Error::isError($password)) {
                $password = null;
            }
        }

        $res = $this->_UserModel->UpdateUser($uid,
                                             $username,
                                             $nickname,
                                             $email,
                                             $password);
        if (Jaws_Error::isError($res)) {
            $GLOBALS['app']->Session->PushLastResponse($res->getMessage(),
                                                       RESPONSE_ERROR);
        } else {
            $GLOBALS['app']->Session->PushLastResponse(_t('USERS_MYACCOUNT_UPDATED'),
                                                       RESPONSE_NOTICE);
        }

        return $GLOBALS['app']->Session->PopLastResponse();
    }

    /**
     * Gets the user-groups form
     *
     * @access  public
     * @return  string  XHTML content
     */
    function UserGroupsUI()
    {
        $gadget = $GLOBALS['app']->LoadGadget('Users', 'AdminHTML', 'Users');
        return $gadget->UserGroupsUI();
    }

    /**
     * Gets the user-groups data
     *
     * @access  public
     * @param   string  $uid    User ID
     * @return  array   Groups data
     */
    function GetUserGroups($uid)
    {
        $groups = $this->_UserModel->GetGroupsOfUser((int)$uid);
        if (Jaws_Error::IsError($groups)) {
            return array();
        }

        return $groups;
    }

    /**
     * Returns the UI of the personal information
     *
     * @access  public
     * @return  string  XHTML content
     */
    function PersonalUI()
    {
        $gadget = $GLOBALS['app']->LoadGadget('Users', 'AdminHTML', 'Users');
        return $gadget->PersonalUI();
    }
    
    /**
     * Returns the UI of the preferences options
     *
     * @access  public
     * @return  string  XHTML content
     */
    function PreferencesUI()
    {
        $gadget = $GLOBALS['app']->LoadGadget('Users', 'AdminHTML', 'Users');
        return $gadget->PreferencesUI();
    }

    /**
     * Updates personal information of selected user
     *
     * @access  public
     * @param   int      $uid     User ID
     * @param   string   $fname   First name
     * @param   string   $lname   Last name
     * @param   string   $gender  User gender
     * @param   string   $dob     User birth date
     * @param   string   $url     User URL
     * @param   string  $avatar   User avatar
     * @param   bool    $privacy  User's display name
     * @return  array   Response array (notice or error)
     */
    function UpdatePersonal($uid, $fname, $lname, $gender, $dob, $url, $about, $avatar, $privacy)
    {
        $dob = empty($dob)? null : $dob;
        if (!empty($dob)) {
            $objDate = $GLOBALS['app']->loadDate();
            $dob = $objDate->ToBaseDate(preg_split('/[- :]/', $dob), 'Y-m-d H:i:s');
            $dob = $GLOBALS['app']->UserTime2UTC($dob, 'Y-m-d H:i:s');
        }

        $res = $this->_UserModel->UpdatePersonalInfo(
            $uid,
            array(
                'fname'   => $fname,
                'lname'   => $lname,
                'gender'  => $gender,
                'dob'     => $dob,
                'url'     => $url,
                'about'   => $about,
                'avatar'  => ($avatar == 'false')? null : $avatar,
                'privacy' => (bool)$privacy
            )
        );
        if ($res === false) {
            $GLOBALS['app']->Session->PushLastResponse(_t('USERS_USERS_PERSONALINFO_NOT_UPDATED'),
                                                       RESPONSE_ERROR);
        } else {
            $GLOBALS['app']->Session->PushLastResponse(_t('USERS_USERS_PERSONALINFO_UPDATED'),
                                                       RESPONSE_NOTICE);
        }

        return $GLOBALS['app']->Session->PopLastResponse();
    }

    /**
     * Updates preferences options of the user
     *
     * @access  public
     * @param   int     $uid       User ID
     * @param   string  $lang      User language
     * @param   string  $theme     User theme
     * @param   string  $editor    User editor
     * @param   string  $timezone  User timezone
     * @return  array   Response array (notice or error)
     */
    function UpdatePreferences($uid, $lang, $theme, $editor, $timezone)
    {
        if ($lang == '-default-') {
            $lang = null;
        }

        if ($theme == '-default-') {
            $theme = null;
        }

        if ($editor == '-default-') {
            $editor = null;
        }

        if ($timezone == '-default-') {
            $timezone = null;
        }

        $res = $this->_UserModel->UpdateAdvancedOptions($uid,
                                                        array('language' => $lang, 
                                                              'theme'    => $theme,
                                                              'editor'   => $editor,
                                                              'timezone' => $timezone));
        if ($res === false) {
            $GLOBALS['app']->Session->PushLastResponse(_t('USERS_USERS_NOT_ADVANCED_UPDATED'),
                                                       RESPONSE_ERROR);
        } else {
            $GLOBALS['app']->Session->PushLastResponse(_t('USERS_USERS_ADVANCED_UPDATED'),
                                                       RESPONSE_NOTICE);
        }

        return $GLOBALS['app']->Session->PopLastResponse();
    }

    /**
     * Gets information of a the group
     *
     * @access  public
     * @param   int     $guid  Group ID
     * @return  array   Group information
     */
    function GetGroup($guid)
    {
        $group = $this->_UserModel->GetGroup((int)$guid);
        if (Jaws_Error::IsError($group)) {
            return array();
        }

        return $group;
    }

    /**
     * Gets list of groups
     *
     * @access  public
     * @param   int     $offset Data offset
     * @return  array   Groups list
     */
    function GetGroups($offset)
    {
        $grpHTML = $GLOBALS['app']->LoadGadget('Users', 'AdminHTML', 'Groups');
        return $grpHTML->GetGroups(null, $offset);
    }

    /**
     * Adds a new group
     *
     * @access  public
     * @param   string  $name        Groups name
     * @param   string  $title       Groups title
     * @param   string  $description Groups description
     * @param   bool    $enabled     Group status
     * @return  array   Response array (notice or error)
     */
    function AddGroup($name, $title, $description, $enabled)
    {
        $this->gadget->CheckPermission('ManageGroups');
        if (trim($name) == '') {
            $GLOBALS['app']->Session->PushLastResponse(_t('USERS_GROUPS_INCOMPLETE_FIELDS'),
                                                       RESPONSE_ERROR);
        } else {
            $res = $this->_UserModel->AddGroup($name, $title, $description, (bool)$enabled);
            if ($res === false) {
                $GLOBALS['app']->Session->PushLastResponse(_t('USERS_GROUPS_NOT_CREATED', $title),
                                                           RESPONSE_ERROR);
            } else {
                $GLOBALS['app']->Session->PushLastResponse(_t('USERS_GROUPS_CREATED', $title),
                                                           RESPONSE_NOTICE);
            }
        }
        return $GLOBALS['app']->Session->PopLastResponse();
    }

    /**
     * Updates the group
     *
     * @access  public
     * @param   int     $guid        Group ID
     * @param   string  $name        Group name
     * @param   string  $title       Groups title
     * @param   string  $description Groups description
     * @param   bool    $enabled    Group status
     * @return  array   Response array (notice or error)
     */
    function UpdateGroup($guid, $name, $title, $description, $enabled)
    {
        $this->gadget->CheckPermission('ManageGroups');
        $res = $this->_UserModel->UpdateGroup($guid, $name, $title, $description, (bool)$enabled);
        if ($res === false) {
            $GLOBALS['app']->Session->PushLastResponse(_t('USERS_GROUPS_NOT_UPDATED', $title),
                                                       RESPONSE_ERROR);
        } else {
            $GLOBALS['app']->Session->PushLastResponse(_t('USERS_GROUPS_UPDATED', $title),
                                                       RESPONSE_NOTICE);
        }

        return $GLOBALS['app']->Session->PopLastResponse();
    }

    /**
     * Deletes the group
     *
     * @access  public
     * @param   int     $guid   Group ID
     * @return  array   Response array (notice or error)
     */
    function DeleteGroup($guid)
    {
        $this->gadget->CheckPermission('ManageGroups');
        $currentUid = $GLOBALS['app']->Session->GetAttribute('user');

        $groupinfo = $this->_UserModel->GetGroup((int)$guid);
        if (!$this->_UserModel->DeleteGroup($guid)) {
            $GLOBALS['app']->Session->PushLastResponse(_t('USERS_GROUPS_CANT_DELETE', $groupinfo['name']),
                                                       RESPONSE_ERROR);
        } else {
            $GLOBALS['app']->Session->PushLastResponse(_t('USERS_GROUPS_DELETED', $groupinfo['name']),
                                                       RESPONSE_NOTICE);
        }
        return $GLOBALS['app']->Session->PopLastResponse();
    }

    /**
     * Gets the users-group form
     *
     * @access  public
     * @return  string  XHTML content
     */
    function GroupUsersUI()
    {
        $grpHTML = $GLOBALS['app']->LoadGadget('Users', 'AdminHTML', 'Groups');
        return $grpHTML->GroupUsersUI();
    }

    /**
     * Gets the group-users array
     *
     * @access  public
     * @param   int     $gid    Group ID
     * @return  array   List of users
     */
    function GetGroupUsers($gid)
    {
        $users = $this->_UserModel->GetUsers((int)$gid);
        if (Jaws_Error::IsError($users)) {
            return array();
        }

        return $users;
    }

}