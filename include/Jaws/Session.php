<?php
/**
 * Class to manage User session.
 *
 * @category   Session
 * @package    Core
 * @author     Ivan -sk8- Chavero <imcsk8@gluch.org.mx>
 * @author     Jonathan Hernandez <ion@suavizado.com>
 * @author     Ali Fazelzadeh <afz@php.net>
 * @copyright  2004-2012 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/lesser.html
 */
define('SESSION_RESERVED_ATTRIBUTES', "sid,salt,type,user,user_name,superadmin,concurrent_logins,acl,updatetime");

/**
 * Responses
 */
define('RESPONSE_WARNING', 'RESPONSE_WARNING');
define('RESPONSE_ERROR',   'RESPONSE_ERROR');
define('RESPONSE_NOTICE',  'RESPONSE_NOTICE');

class Jaws_Session
{
    /**
     * Authentication model
     * @var     object $_AuthModel
     * @access  private
     */
    var $_AuthModel;

    /**
     * Authentication method
     * @var     string $_AuthMethod
     * @access  private
     */
    var $_AuthMethod;

    /**
     * Last error message
     * @var     string $_Error
     * @access  private
     * @see     GetError()
     */
    var $_Error;

    /**
     * Attributes array
     * @var     array $_Attributes
     * @access  private
     * @see     SetAttribute(), GetAttibute()
     */
    var $_Attributes = array();

    /**
     * Changes flag
     * @var     boolean $_HasChanged
     * @access  private
     */
    var $_HasChanged;

    /**
     * Session unique identifier
     * @var     string $_SessionID
     * @access  private
     */
    var $_SessionID;

    /**
     * Is session exists in browser or application
     * @var     boolean $_SessionExists
     * @access  private
     */
    var $_SessionExists = true;

    /**
     * An interface for available drivers
     *
     * @access  public
     */
    function &factory()
    {
        $SessionType = ucfirst(strtolower(APP_TYPE));
        $sessionFile = JAWS_PATH . 'include/Jaws/Session/'. $SessionType .'.php';
        if (!file_exists($sessionFile)) {
            $GLOBALS['log']->Log(JAWS_LOG_DEBUG, "Loading session $SessionType failed.");
            return new Jaws_Error("Loading session $SessionType failed.",
                                  __FUNCTION__);
        }

        include_once $sessionFile;
        $className = 'Jaws_Session_' . $SessionType;
        $obj = new $className();
        return $obj;
    }

    /**
     * Initializes the Session
     */
    function Init()
    {
        $this->_AuthMethod = $GLOBALS['app']->Registry->Get('/config/auth_method');
        $authFile = JAWS_PATH . 'include/Jaws/Auth/' . $this->_AuthMethod . '.php';
        if (empty($this->_AuthMethod) || !file_exists($authFile)) {
            $GLOBALS['log']->Log(JAWS_LOG_ERROR,
                                 $this->_AuthMethod . ' Error: ' . $authFile .
                                 ' file doesn\'t exists, using DefaultAuth');
            $this->_AuthMethod = 'Default';
        }

        // Try to restore session...
        $this->_HasChanged = false;

        // Delete expired sessions
        if (mt_rand(1, 32) == mt_rand(1, 32)) {
            $this->DeleteExpiredSessions();
        }
    }

    /**
     * Gets the session mode
     *
     * @access  public
     * @return  string  Session mode
     */
    function GetMode()
    {
        return $this->_Mode;
    }

    /**
     * Login
     *
     * @param   string  $username   Username
     * @param   string  $password   Password
     * @param   boolean $remember   Remember me
     * @param   string  $authmethod Authentication method
     * @return  boolean True if succeed.
     */
    function Login($username, $password, $remember, $authmethod = '')
    {
        $GLOBALS['log']->Log(JAWS_LOG_DEBUG, 'LOGGIN IN');
        if (!$this->_SessionExists) {
            return Jaws_Error::raiseError(_t('GLOBAL_ERROR_SESSION_NOTFOUND'),
                                          __FUNCTION__,
                                          JAWS_ERROR_NOTICE);
        }

        if ($username !== '' && $password !== '') {
            if (!empty($authmethod)) {
                $authmethod = preg_replace('#[^[:alnum:]_-]#', '', $authmethod);
            } else {
                $authmethod = $this->_AuthMethod;
            }

            require_once JAWS_PATH . 'include/Jaws/Auth/' . $authmethod . '.php';
            $className = 'Jaws_Auth_' . $authmethod;
            $this->_AuthModel = new $className();
            $result = $this->_AuthModel->Auth($username, $password);
            if (!Jaws_Error::isError($result)) {
                $result = $this->_AuthModel->GetAttributes();
                if (!Jaws_Error::isError($result)) {
                    $existSessions = 0;
                    if (!empty($result['concurrent_logins'])) {
                        $existSessions = $this->GetUserSessions($result['id'], true);
                    }

                    if (empty($existSessions) || $result['concurrent_logins'] > $existSessions)
                    {
                        $this->Create($result, $remember);
                        return true;
                    } else {
                        $result = new Jaws_Error(_t('GLOBAL_ERROR_LOGIN_CONCURRENT_REACHED'),
                                                 __FUNCTION__,
                                                 JAWS_ERROR_NOTICE);
                    }
                }
            }

            return $result;
        }

        return new Jaws_Error(_t('GLOBAL_ERROR_LOGIN_WRONG'),
                                 __FUNCTION__,
                                 JAWS_ERROR_NOTICE);
    }

    /**
     * Return session login status
     * @access  public
     */
    function Logged()
    {
        return $this->GetAttribute('logged');
    }

    /**
     * Logout
     *
     * Logout from session
     * reset session values
     */
    function Logout()
    {
        $this->Reset();
        $this->Synchronize($this->_SessionID);
        $GLOBALS['log']->Log(JAWS_LOG_DEBUG, 'Session logout');
    }

    /**
     * Return last error message
     * @access  public
     */
    function GetError()
    {
        return $this->_Error;
    }

    /**
     * Loads Jaws Session
     *
     * @param   string  $sid Session identifier
     * @return  boolean True if can load session, false if not
     */
    function Load($sid)
    {
        $GLOBALS['log']->Log(JAWS_LOG_DEBUG, 'Loading session');
        $this->_SessionID = '';
        @list($sid, $salt) = explode('-', $sid);
        $session = $this->GetSession((int)$sid);
        if (is_array($session)) {
            $checksum = md5($session['user'] . $session['data']);
            $expTime = time() - 60 * (int) $GLOBALS['app']->Registry->Get('/policy/session_idle_timeout');

            $this->_SessionID  = $session['sid'];
            $this->_Attributes = unserialize($session['data']);

            // check session longevity
            if ($session['updatetime'] < ($expTime - $session['longevity'])) {
                $GLOBALS['app']->Session->Logout();
                $GLOBALS['log']->Log(JAWS_LOG_NOTICE, 'Previous session has expired');
                return false;
            }

            // user expiry date
            $expiry_date = $this->GetAttribute('expiry_date');
            if (!empty($expiry_date) && $expiry_date <= time()) {
                $GLOBALS['app']->Session->Logout();
                $GLOBALS['log']->Log(JAWS_LOG_NOTICE, 'This username is expired');
                return false;
            }

            // logon hours
            $logon_hours = $this->GetAttribute('logon_hours');
            if (!empty($logon_hours)) {
                $wdhour = explode(',', $GLOBALS['app']->UTC2UserTime(time(), 'w,G', true));
                $lhByte = hexdec($logon_hours{$wdhour[0]*6 + intval($wdhour[1]/4)});
                if ((pow(2, fmod($wdhour[1], 4)) & $lhByte) == 0) {
                    $GLOBALS['app']->Session->Logout();
                    $GLOBALS['log']->Log(JAWS_LOG_NOTICE, 'Logon hours terminated');
                    return false;
                }
            }

            // concurrent logins
            if ($session['updatetime'] < $expTime) {
                $logins = $this->GetAttribute('concurrent_logins');
                $existSessions = $this->GetUserSessions($this->GetAttribute('user'), true);
                if (!empty($existSessions) && !empty($logins) && $existSessions >= $logins) {
                    $GLOBALS['app']->Session->Logout();
                    $GLOBALS['log']->Log(JAWS_LOG_NOTICE, 'Maximum number of concurrent logins reached');
                    return false;
                }
            }

            // browser agent
            $xss = $GLOBALS['app']->loadClass('XSS', 'Jaws_XSS');
            $agent = $xss->filter($_SERVER['HTTP_USER_AGENT']);
            if ($agent !== $session['agent']) {
                $GLOBALS['app']->Session->Logout();
                $GLOBALS['log']->Log(JAWS_LOG_NOTICE, 'Previous session agent has been changed');
                return false;
            }

            // salt & checksum
            if (($salt !== $this->GetAttribute('salt')) || ($checksum !== $session['checksum'])) {
                $GLOBALS['app']->Session->Logout();
                $GLOBALS['log']->Log(JAWS_LOG_NOTICE, 'Previous session salt has been changed');
                return false;
            }

            // check referrer of request
            $referrer = @parse_url($_SERVER['HTTP_REFERER']);
            if ($referrer && isset($referrer['host'])) {
                $referrer = $referrer['host'];
            } else {
                $referrer = $_SERVER['HTTP_HOST'];
            }

            if (!$this->GetAttribute('logged') ||
                (JAWS_SCRIPT != 'admin') ||
                $referrer == $_SERVER['HTTP_HOST'] ||
                $session['referrer'] === md5($referrer))
            {
                $GLOBALS['log']->Log(JAWS_LOG_DEBUG, 'Session was OK');
                return true;
            } else {
                $GLOBALS['app']->Session->Logout();
                $GLOBALS['log']->Log(JAWS_LOG_NOTICE, 'Session found but referrer changed');
                return false;
            }
        }

        $GLOBALS['log']->Log(JAWS_LOG_DEBUG, 'No previous session exists');
        return false;
    }

    /**
     * Create a new session for a given data
     * @param   array  $info      User attributes
     * @param   boolean $remember Remember me
     * @return  boolean True if can create session.
     */
    function Create($info = array(), $remember = false)
    {
        if (empty($info)) {
            $info['id']          = '';
            $info['internal']    = false;
            $info['username']    = '';
            $info['superadmin']  = false;
            $info['groups']      = array();
            $info['nickname']    = '';
            $info['logon_hours'] = '';
            $info['expiry_date'] = 0;
            $info['concurrent_logins'] = 0;
            $info['email']      = '';
            $info['url']        = '';
            $info['avatar']     = '';
            $info['language']   = '';
            $info['theme']      = '';
            $info['editor']     = '';
            $info['timezone']   = null;
        }

        $this->_Attributes = array();
        $this->SetAttribute('user',        $info['id']);
        $this->SetAttribute('internal',    $info['internal']);
        $this->SetAttribute('salt',        uniqid(mt_rand(), true));
        $this->SetAttribute('type',        APP_TYPE);
        $this->SetAttribute('username',    $info['username']);
        $this->SetAttribute('superadmin',  $info['superadmin']);
        $this->SetAttribute('groups',      $info['groups']);
        $this->SetAttribute('logon_hours', $info['logon_hours']);
        $this->SetAttribute('expiry_date', $info['expiry_date']);
        $this->SetAttribute('concurrent_logins', $info['concurrent_logins']);
        $this->SetAttribute('longevity',  $remember?
                                          (int)$GLOBALS['app']->Registry->Get('/policy/session_remember_timeout')*3600 : 0);
        $this->SetAttribute('logged',     !empty($info['id']));
        //profile
        $this->SetAttribute('nickname',   $info['nickname']);
        $this->SetAttribute('email',      $info['email']);
        $this->SetAttribute('url',        $info['url']);
        $this->SetAttribute('avatar',     $info['avatar']);
        //preferences
        $this->SetAttribute('language',   $info['language']);
        $this->SetAttribute('theme',      $info['theme']);
        $this->SetAttribute('editor',     $info['editor']);
        $this->SetAttribute('timezone',  (trim($info['timezone']) == "") ? null : $info['timezone']);

        $this->_SessionID = $this->Synchronize($this->_SessionID);
        return true;
    }

    /**
     * Reset current session
     * @return  boolean True if can reset it
     */
    function Reset()
    {
        $this->_Attribute = array();
        $this->SetAttribute('user',        '');
        $this->SetAttribute('salt',        uniqid(mt_rand(), true));
        $this->SetAttribute('type',        APP_TYPE);
        $this->SetAttribute('internal',    false);
        $this->SetAttribute('username',    '');
        $this->SetAttribute('superadmin',  false);
        $this->SetAttribute('groups',      array());
        $this->SetAttribute('logon_hours', '');
        $this->SetAttribute('expiry_date', 0);
        $this->SetAttribute('concurrent_logins', 0);
        $this->SetAttribute('longevity',  0);
        $this->SetAttribute('logged',     false);
        $this->SetAttribute('nickname',   '');
        $this->SetAttribute('email',      '');
        $this->SetAttribute('url',        '');
        $this->SetAttribute('avatar',     '');
        $this->SetAttribute('language',   '');
        $this->SetAttribute('theme',      '');
        $this->SetAttribute('editor',     '');
        $this->SetAttribute('timezone',   null);
        return true;
    }

    /**
     * Set a session attribute
     *
     * @param   string $name attribute name
     * @param   string $value attribute value
     * @return  boolean True if attribute has changed
     */
    function SetAttribute($name, $value)
    {
        if (!array_key_exists($name, $this->_Attributes) || ($this->_Attributes[$name] != $value))
        {
            if (is_array($value) && $name == 'LastResponses') {
                $this->_Attributes['LastResponses'][] = $value;
            } else {
                $this->_Attributes[$name] = $value;
            }
            $this->_HasChanged = true;
            return true;
        }

        return false;
    }

    /**
     * Get a session attribute
     *
     * @param   string $name attribute name
     * @return  string value of the attribute
     */
    function GetAttribute($name)
    {
        // Deprecated: in next major version will be removed
        if ($name == 'user_id') {
            $name = 'user';
        }

        if (array_key_exists($name, $this->_Attributes)) {
            return $this->_Attributes[$name];
        }

        return null;
    }

    /**
     * Get a session attributes
     *
     * @return  array value of the attributes
     */
    function GetAttributes()
    {
        $names = func_get_args();
        // for support array of keys array
        if (isset($names[0][0]) && is_array($names[0][0])) {
            $names = $names[0];
        }

        if (empty($reg_keys)) {
            return $this->_Attributes;
        }

        $attributes = array();
        foreach ($names as $name) {
            $attributes[$name] = $this->GetAttribute($name);
        }

        return $attributes;
    }

    /**
     * Delete a session attribute
     *
     * @param   string $name attribute name
     * @return  boolean True if attribute has been deleted
     */
    function DeleteAttribute($name)
    {
        if (array_key_exists($name, $this->_Attributes)) {
            unset($this->_Attributes[$name]);
            $this->_HasChanged = true;
            return true;
        }

        return false;
    }

    /**
     * Get permission on a given gadget/task
     *
     * @param   string $gadget Gadget name
     * @param   string $task Task name
     * @return  boolean True if granted, else False
     */
    function GetPermission($gadget, $task)
    {
        $user = $this->GetAttribute('username');
        $groups = $this->GetAttribute('groups');
        return $GLOBALS['app']->ACL->GetFullPermission($user, $groups, $gadget, $task, $this->IsSuperAdmin());
    }

    /**
     * Check permission on a given gadget/task
     *
     * @param   string $gadget Gadget name
     * @param   string $task Task name
     * @param   string $errorMessage Error message to return
     * @return  boolean True if granted, else throws an Exception(Jaws_Error::Fatal)
     */
    function CheckPermission($gadget, $task, $errorMessage = '')
    {
        if ($this->GetPermission($gadget, $task)) {
            return true;
        }

        if (empty($errorMessage)) {
            $errorMessage = 'User '.$this->GetAttribute('username').
                ' don\'t have permission to execute '.$gadget.'::'.$task;
        }

        Jaws_Error::Fatal($errorMessage);
    }

    /**
     * Returns true if user is a super-admin (aka superroot)
     *
     * @access  public
     * @return  boolean
     */
    function IsSuperAdmin()
    {
        return $this->GetAttribute('logged') && $this->GetAttribute('superadmin');
    }

    /**
     * Synchronize current session
     */
    function Synchronize()
    {
        // agent
        $xss = $GLOBALS['app']->loadClass('XSS', 'Jaws_XSS');
        $agent = $xss->filter($_SERVER['HTTP_USER_AGENT']);
        // ip
        $ip = 0;
        if (preg_match('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $_SERVER['REMOTE_ADDR'])) {
            $ip = ip2long($_SERVER['REMOTE_ADDR']);
            $ip = ($ip < 0)? ($ip + 0xffffffff + 1) : $ip;
        }
        // referrer
        $referrer = @parse_url($_SERVER['HTTP_REFERER']);
        if ($referrer && isset($referrer['host']) && ($referrer['host'] != $_SERVER['HTTP_HOST'])) {
            $referrer = $referrer['host'];
        } else {
            $referrer = '';
        }

        if (!empty($this->_SessionID)) {
            // Now we sync with a previous session only if has changed
            if ($GLOBALS['app']->Session->_HasChanged) {
                $params = array();
                $serialized = serialize($GLOBALS['app']->Session->_Attributes);
                $params['sid']        = $this->_SessionID;
                $params['data']       = $serialized;
                $params['user']       = $GLOBALS['app']->Session->GetAttribute('user');
                $params['longevity']  = $GLOBALS['app']->Session->GetAttribute('longevity');
                $params['referrer']   = md5($referrer);
                $params['checksum']   = md5($params['user'] . $serialized);
                $params['ip']         = $ip;
                $params['agent']      = $agent;
                $params['updatetime'] = time();

                $sql = '
                    UPDATE [[session]] SET
                        [user]       = {user},
                        [data]       = {data},
                        [longevity]  = {longevity},
                        [referrer]   = {referrer},
                        [checksum]   = {checksum},
                        [ip]         = {ip},
                        [agent]      = {agent},
                        [updatetime] = {updatetime}
                    WHERE [sid] = {sid}';

                $result = $GLOBALS['db']->query($sql, $params);
                if (!Jaws_Error::IsError($result)) {
                    $GLOBALS['log']->Log(JAWS_LOG_DEBUG, 'Session synchronized succesfully');
                    return $this->_SessionID;
                }
            } else {
                $params = array();
                $params['sid']        = $this->_SessionID;
                $params['updatetime'] = time();
                $sql = '
                    UPDATE [[session]] SET
                        [updatetime] = {updatetime}
                    WHERE [sid] = {sid}';
                $result = $GLOBALS['db']->query($sql, $params);
                if (!Jaws_Error::IsError($result)) {
                    $GLOBALS['log']->Log(JAWS_LOG_DEBUG, 'Session synchronized succesfully(only modification time)');
                    return $this->_SessionID;
                }
            }
        } else {
            //A new session, we insert it to the DB
            $updatetime = time();
            $GLOBALS['app']->Session->SetAttribute('groups', array());
            $serialized = serialize($GLOBALS['app']->Session->_Attributes);

            $params = array();
            $params['data']       = $serialized;
            $params['longevity']  = $GLOBALS['app']->Session->GetAttribute('longevity');
            $params['app_type']   = APP_TYPE;
            $params['user']       = $GLOBALS['app']->Session->GetAttribute('user');
            $params['referrer']   = md5($referrer);
            $params['checksum']   = md5($params['user'] . $serialized);
            $params['ip']         = $ip;
            $params['agent']      = $agent;
            $params['updatetime'] = $updatetime;
            $params['createtime'] = $updatetime;

            $sql = '
                INSERT INTO [[session]]
                    ([user], [type], [longevity], [data], [referrer], [checksum],
                     [ip], [agent], [createtime], [updatetime])
                VALUES
                    ({user}, {app_type}, {longevity}, {data}, {referrer}, {checksum},
                     {ip}, {agent}, {createtime}, {updatetime})';

            $result = $GLOBALS['db']->query($sql, $params);
            if (!Jaws_Error::IsError($result)) {
                $result = $GLOBALS['db']->lastInsertID('session', 'sid');
                if (!Jaws_Error::IsError($result) && !empty($result)) {
                    return $result;
                }
            }
        }

        return false;
    }

    /**
     * Delete a session
     *
     * @param   integer  $sid  Session ID
     * @return  boolean Success/Failure
     */
    function Delete($sid)
    {
        $sql = 'DELETE FROM [[session]] WHERE [sid] = {sid}';
        $result = $GLOBALS['db']->query($sql, array('sid' => $sid));
        if (Jaws_Error::IsError($result)) {
            return false;
        }

        return true;
    }

    /**
     * Deletes all sessions of an user
     *
     * @param   string  $user   User's ID
     * @return  boolean Success/Failure
     */
    function DeleteUserSessions($user)
    {
        //Get the sessions ID of the user
        $sql = 'DELETE FROM [[session]] WHERE [user] = {user}';
        $result = $GLOBALS['db']->query($sql, array('user' => (string)$user));
        if (Jaws_Error::IsError($result)) {
            return false;
        }

        return true;
    }

    /**
     * Delete expired sessions
     */
    function DeleteExpiredSessions()
    {
        $params = array();
        $params['expired'] = time() - ($GLOBALS['app']->Registry->Get('/policy/session_idle_timeout') * 60);
        $sql = "DELETE FROM [[session]] WHERE [updatetime] < ({expired} - [longevity])";
        $result = $GLOBALS['db']->query($sql, $params);
        if (Jaws_Error::IsError($result)) {
            return false;
        }

        return true;
    }

    /**
     * Returns all users's sessions count
     *
     * @access  public
     * @param   integer $user   User ID
     * @return  mixed   Session count if exist, false otherwise
     */
    function GetUserSessions($user, $onlyOnline = false)
    {
        $params = array();
        $params['user'] = (string)$user;
        $params['expired'] = time() - ($GLOBALS['app']->Registry->Get('/policy/session_idle_timeout') * 60);
        $sql = '
            SELECT COUNT([user])
            FROM [[session]]
            WHERE [user] = {user}';

        if ($onlyOnline) {
            $sql.= ' AND [updatetime] >= {expired}';
        }

        $count = $GLOBALS['db']->queryOne($sql, $params);
        if (Jaws_Error::isError($count)) {
            return false;
        }

        return (int) $count;
    }

    /**
     * Returns the session values
     *
     * @access  private
     * @param   string  $sid  Session ID
     * @return  mixed   Session values if exist, false otherwise
     */
    function GetSession($sid)
    {
        $params = array();
        $params['sid'] = $sid;

        $sql = '
            SELECT
                [sid], [user], [data], [referrer], [checksum], [ip], [agent],
                [updatetime], [longevity]
            FROM [[session]]
            WHERE
                [sid] = {sid}';

        $result = $GLOBALS['db']->queryRow($sql, $params);
        if (!Jaws_Error::isError($result) && isset($result['sid'])) {
            return $result;
        }

        return false;
    }

    /**
     * Push a simple response (no CSS and special data)
     *
     * @access  public
     * @param   string  $msg    Response's message
     */
    function PushSimpleResponse($msg, $resource = 'SimpleResponse')
    {
        $this->SetAttribute($resource, $msg);
    }

    /**
     * Prints (returns) the last simple response
     *
     * @access  public
     * @param   string  $resource Resource's name
     * @param   boolean $removePoppedResource
     * @return  mixed   Last simple response
     */
    function PopSimpleResponse($resource = 'SimpleResponse', $removePoppedResource = true)
    {
        $response = $this->GetAttribute($resource);
        if ($removePoppedResource) {
            $this->DeleteAttribute($resource);
        }

        if (empty($response)) {
            return false;
        }

        return $response;
    }

    /**
     * Add the last response to the session system
     *
     * @access  public
     * @param   string  $msg    Response's message
     * @param   string  $level  Response type
     * @param   mixed   $data   Extra data
     */
    function PushLastResponse($msg, $level = RESPONSE_WARNING, $data = null)
    {
        if (!defined($level)) {
            $level = RESPONSE_WARNING;
        }

        switch ($level) {
            case RESPONSE_ERROR:
                $css = 'error-message';
                break;
            case RESPONSE_NOTICE:
                $css = 'notice-message';
                break;
            case RESPONSE_WARNING:
            default:
                $css = 'warning-message';
                break;
        }

        $this->SetAttribute('LastResponses',
                            array('message' => $msg,
                                  'data'    => $data,
                                  'level'   => $level,
                                  'css'     => $css
                                  )
                            );
    }

    /**
     * Get the response
     *
     * @access  public
     * @return  string  Returns the message of the response
     */
    function GetResponse($msg, $level = RESPONSE_WARNING, $data = null)
    {
        if (!defined($level)) {
            $level = RESPONSE_WARNING;
        }

        switch ($level) {
            case RESPONSE_ERROR:
                $css = 'error-message';
                break;
            case RESPONSE_NOTICE:
                $css = 'notice-message';
                break;
            case RESPONSE_WARNING:
            default:
                $css = 'warning-message';
                break;
        }

        return array('message' => $msg,
                     'data'    => $data,
                     'level'   => $level,
                     'css'     => $css);
    }

    /**
     * Prints and deletes the last response of a gadget
     *
     * @access  public
     * @param   string  $gadget Gadget's name
     * @return  string  Returns the message of the last response and false if there's no response
     */
    function PopLastResponse()
    {
        $responses = $this->GetAttribute('LastResponses');
        if ($responses === null) {
            return false;
        }

        $this->DeleteAttribute('LastResponses');
        $responses = array_reverse($responses);
        if (empty($responses[0]['message'])) {
            return false;
        }

        return $responses;
    }

}