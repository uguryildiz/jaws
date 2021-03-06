<?php
/**
 * Launcher execute script
 *
 * @category   Gadget
 * @package    Launcher
 * @author     Jonathan Hernandez <ion@suavizado.com>
 * @author     Ali Fazelzadeh <afz@php.net>
 * @copyright  2006-2012 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/gpl.html
 */
class Launcher_Actions_Execute extends Jaws_Gadget_HTML
{
    /**
     * Get Execute action params(scripts list)
     *
     * @access  public
     * @return  array   Actions array
     */
    function ExecuteLayoutParams()
    {
        $result  = array();
        $model   = $GLOBALS['app']->LoadGadget('Launcher', 'Model');
        $scripts = $model->GetScripts();
        if (!Jaws_Error::IsError($scripts)) {
            $pscripts = array();
            foreach ($scripts as $script) {
                $pscripts[$script] = $script;
            }

            $result[] = array(
                'title' => _t('LAUNCHER_SCRIPT'),
                'value' => $pscripts
            );
        }

        return $result;
    }

    /**
     * Show a Launcher
     *
     * @access  public
     * @param   string  $script     script name
     * @return  string  Script output content
     */
    function Execute($script = '')
    {
        $params = null;
        if (empty($script)) {
            $request =& Jaws_Request::getInstance();
            $script = $request->get('script', 'get');
            $params = $request->get('params', 'get');
        }

        if (!empty($script)) {
            // Check if script exists
            if (@include_once JAWS_PATH. "gadgets/Launcher/scripts/$script.php") {
                // Check if function exists and return its execution result
                if (function_exists($script)) {
                    return call_user_func($script, $params);
                } else {
                    return new Jaws_Error(_t('LAUNCHER_ERROR_FUNCTION_NOT_EXISTS', $script));
                }
            }
        }

        return new Jaws_Error(_t('LAUNCHER_ERROR_SCRIPT_NOT_EXISTS', $script));
    }

}