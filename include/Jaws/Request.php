<?php
/**
 * Get rid of register_globals things if active
 *
 * @author Richard Heyes
 * @author Stefan Esser
 * @url http://www.phpguru.org/article.php?ne_id=60
 */
$_SERVER['REQUEST_METHOD']  = array_key_exists('REQUEST_METHOD', $_SERVER)?
                                               strtoupper($_SERVER['REQUEST_METHOD']):
                                               'GET';
$_SERVER['CONTENT_TYPE']    = array_key_exists('CONTENT_TYPE', $_SERVER)?
                                               $_SERVER['CONTENT_TYPE']:
                                               '';
$_SERVER['HTTP_USER_AGENT'] = array_key_exists('HTTP_USER_AGENT', $_SERVER)?
                                               $_SERVER['HTTP_USER_AGENT']:
                                               '';
$_SERVER['HTTP_REFERER']    = array_key_exists('HTTP_REFERER', $_SERVER)?
                                               $_SERVER['HTTP_REFERER']:
                                               '';
if (ini_get('register_globals')) {
    // Might want to change this perhaps to a nicer error
    if (isset($_REQUEST['GLOBALS'])) {
        Jaws_Error::Fatal('GLOBALS overwrite attempt detected');
    }

    // Variables that shouldn't be unset
    $noUnset = array('GLOBALS',  '_GET',
                     '_POST',    '_COOKIE',
                     '_REQUEST', '_SERVER',
                     '_ENV',     '_FILES');

    $input = array_merge($_GET,    $_POST,
                         $_COOKIE, $_SERVER,
                         $_ENV,    $_FILES,
                         isset($_SESSION) ? $_SESSION : array());

    foreach ($input as $k => $v) {
        if (!in_array($k, $noUnset) && isset($GLOBALS[$k])) {
            unset($GLOBALS[$k]);
        }
    }
}

/**
 * We don't like magic_quotes, so we disable it ;-)
 *
 * Basis of the code were gotten from the book
 * php archs guid to PHP Security
 * @auhor Illia Alshanetsky <ilia@php.net>
 */
@set_magic_quotes_runtime(0);
if (get_magic_quotes_gpc()) {
    $input = array(&$_GET, &$_POST, &$_REQUEST, &$_COOKIE, &$_ENV, &$_SERVER);

    // between 5.0.0 and 5.1.0, array keys in the superglobals were escaped even with register_globals off
    $keybug = (version_compare(PHP_VERSION, '5.0.0', '>=') && version_compare(PHP_VERSION, '5.1.0', '<'));

    while (list($k, $v) = each($input)) {
        foreach ($v as $key => $val) {
            if (!is_array($val)) {
                $key = $keybug ? $key : stripslashes($key);
                $input[$k][$key] = stripslashes($val);
                continue;
            }
            $input[] =& $input[$k][$key];
        }
    }
    unset($input);
}

/**
 * Short description
 *
 * Long description
 *
 * @category   Jaws
 * @package    Jaws_Request
 * @author     Helgi Þormar Þorbjörnsson <dufuz@php.net>
 * @copyright  2006 Helgi Þormar Þorbjörnsson
 * @license    http://www.opensource.org/licenses/bsd-license.php  New BSD License
 */
class Jaws_Request
{
    /**
     * @var array
     */
    var $_filters;

    /**
     * @var array
     */
    var $_params;

    /**
     * @var array
     */
    var $_priority;

    /**
     * @var array
     */
    var $_includes;

    /**
     * @var array
     */
    var $_allowedTypes = array('get', 'post', 'cookie');

    /**
     * Constructor
     *
     * @access  public
     * @return  void
     */
    function Jaws_Request()
    {
        $this->_filters  = array();
        $this->_params   = array();
        $this->_priority = array();
        $this->_includes = array();
        $this->data['get']    = $_GET;
        $this->data['cookie'] = $_COOKIE;
        // support json encoded pos data
        if (false !== strpos($_SERVER['CONTENT_TYPE'], 'application/json')) {
            $json = file_get_contents('php://input');
            $this->data['post'] = Jaws_UTF8::json_decode($json);
        } else {
            $this->data['post'] = $_POST;
        }

        array_walk_recursive($this->data, array(&$this, 'nullstrip'));

        // Strict mode
        /*
        if (true) {
            unset($_GET);
            unset($_POST);
            unset($_REQUEST);
            unset($_COOKIE);
        }
        */
    }

    /**
     * Creates the Jaws_Request instance if it doesn't exist
     * else it returns the already created one.
     *
     * @return  object returns the instance
     * @access  public
     */
    function &getInstance()
    {
        static $instances;
        if (!isset($instances)) {
            $instances = array();
        }

        $signature = serialize(array('request'));
        if (!isset($instances[$signature])) {
            $instances[$signature] = new Jaws_Request();
        }

        return $instances[$signature];
    }

    /**
     * @param   string  $type
     * @return  mixed
     */
    function isTypeValid($type)
    {
        $type = strtolower($type);
        if (in_array($type, $this->_allowedTypes)) {
            return $type;
        }

        return false;
    }

    /**
     * Adds a filter that will be runned on output requested data
     *
     * @access  public
     * @param   string  $name       Name of the filter
     * @param   string  $function   The function that will be executed
     * @param   string  $params     Path of the included if it's needed for the function
     * @param   string  $include    Filename that include the filter function
     * @return  void
     */
    function addFilter($name, $function, $params = null, $include = '')
    {
        $this->_filters[$name] = $function;
        $this->_params[$name]  = $params;
        $this->_priority[]     = $name;
        if ($include != '') {
            $this->_includes[$name] = $include;
        }
    }

    /**
     * Strip null character
     *
     * @access  public
     * @param   string  $value  Referenced value
     * @return  void
     */
    function nullstrip(&$value)
    {
        if (is_string($value)) {
            $value = preg_replace(array('/\0+/', '/(\\\\0)+/'), '', $value);
        }
    }

    /**
     * Strip ambiguous characters
     *
     * @access  public
     * @param   string  $value
     * @return  string  The striped data
     */
    function strip_ambiguous($value)
    {
        if (is_string($value)) {
            return preg_replace('/%00/', '', $value);
        }
    }

    /**
     * Filter data with added filter functions
     *
     * @access  public
     * @param   string  $value Referenced value
     * @return  string  The filtered data
     */
    function filter(&$value)
    {
        if (is_string($value)) {
            foreach ($this->_priority as $filter) {
                $function = $this->_filters[$filter];
                if (isset($this->_includes[$filter]) && file_exists($this->_includes[$filter])) {
                    include_once $this->_includes[$filter];
                }

                $params = array();
                $params[] = $value;
                if (is_array($this->_params[$filter])) {
                    $params = array_merge($params, $this->_params[$filter]);
                } else {
                    $params[] = $this->_params[$filter];
                }

                $value = call_user_func_array($function, $params);
            }
        }
    }

    /**
     * Does the recursion on the data being fetched
     *
     * @access  private
     * @param   mixed   $key            The key being fetched, it can be an array with multiple keys in it to fetch and
     *                                  then an array will be returned accourdingly.
     * @param   string  $type           Which super global is being fetched from
     * @param   bool    $filter         Returns filtered data or not
     * @param   bool    $json_decode    Decode JSON data or not
     * @return  mixed   Null if there is no data else an string|array with the processed data
     */
    function _get($key, $type = '', $filter = true, $json_decode = false)
    {
        $type = empty($type)? strtolower($_SERVER['REQUEST_METHOD']) : $type;
        if (is_array($key)) {
            $result = array();
            foreach ($key as $k) {
                $result[$k] = $this->_get($k, $type, $filter, $json_decode);
            }

            return $result;
        }

        if (isset($this->data[$type][$key])) {
            $value = $json_decode? Jaws_UTF8::json_decode($this->data[$type][$key]) : $this->data[$type][$key];
            // try unserialize value
            if (false !== $tvalue = @unserialize($value)) {
                $value = $tvalue;
                unset($tvalue);
            }

            if ($filter) {
                if (is_array($value)) {
                    array_walk_recursive($value, array(&$this, 'filter'));
                } else {
                    $this->filter($value);
                }
            }

            return $value;
        }

        return null;
    }

    /**
     * Fetches the data, filters it and then it returns it.
     *
     * @access  public
     * @param   mixed   $key            The key being fetched, it can be an array with multiple keys in it to fetch and then
     *                                  an array will be returned accourdingly.
     * @param   mixed   $types          Which super global is being fetched from, it can be an array
     * @param   bool    $filter         Returns filtered data or not
     * @param   bool    $json_decode    Decode JSON data or not
     * @return  mixed   Returns string or an array depending on the key, otherwise Null if key not exist
     */
    function get($key, $types = '', $filter = true, $json_decode = false)
    {
        $result = null;
        if (empty($types)) {
            switch (strtolower($_SERVER['REQUEST_METHOD'])) {
                case 'get':
                    $types = array('get', 'post');
                    break;

                case 'post':
                    $types = array('post', 'get');
                    break;

                default:
                    return null;
            }
        } elseif (!is_array($types)) {
            $types = array($types);
        }

        foreach ($types as $type) {
            $result = $this->_get($key, $type, $filter, $json_decode);
            if (!is_null($result)) {
                break;
            }
        }

        return $result;
    }

    /**
     * Fetches the filtered data with out filter, it's like using the super globals straight.
     *
     * @access  public
     * @param   string  $type   Which super global is being fetched from
     * @param   bool    $filter Returns filtered data or not
     * @return  array   Filtered Data array
     */
    function getAll($type = '', $filter = true)
    {
        $type = empty($type)? strtolower($_SERVER['REQUEST_METHOD']) : $type;
        if (!isset($this->data[$type]) || empty($this->data[$type])) {
            return array();
        }

        if ($filter) {
            $values = array_map(array($this, '_get'), array_keys($this->data[$type]));
            return array_combine(array_keys($this->data[$type]), $values);
        } else {
            return $this->data[$type];
        }
    }

    /** Creates a new key or updates an old one, doesn't support recursive stuff atm
     * One idea would be to have set('get', 'foo/bar/foobar', 'sm00ke') and resolve the path
     * another would be to allow arrays like crazy but still
     *
     * @param   string  $type
     * @param   string  $key
     * @param   mixed   $value
     * @return  bool
     */
    function set($type, $key, $value)
    {
        $type = $this->isTypeValid($type);
        if (!$type) {
            return false;
        }

        $this->data[$type][$key] = $value;
        return true;
    }

    /**
     * Reset super global request variables
     *
     * @access  public
     * @param   string  $type   Which super global is being reset,
     *                          if no passed value reset all super global request vaiables
     * @return  bool    True
     */
    function reset($type = '')
    {
        $type = $this->isTypeValid($type);
        switch ($type) {
            case 'get':
                unset($_GET);
                $this->data['get'] = array();
                break;

            case 'post':
                unset($_POST);
                $this->data['post'] = array();
                break;

            case 'cookie':
                unset($_COOKIE);
                $this->data['cookie'] = array();
                break;

            default:
                unset($_GET);
                unset($_POST);
                unset($_REQUEST);
                unset($_COOKIE);
                $this->data = array();
        }

        return true;
    }

}