<?php
/**
 * Users Core Gadget
 *
 * @category   GadgetModel
 * @package    Users
 * @author     Jonathan Hernandez <ion@suavizado.com>
 * @author     Ali Fazelzadeh <afz@php.net>
 * @copyright  2004-2012 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/lesser.html
 */
class Users_Model_Admin_Settings extends Jaws_Model
{
    /**
     * Save user config settings
     *
     * @access  public
     * @param   string  $method     Authentication method
     * @param   string  $anon       Anonymous users can auto-register
     * @param   string  $repetitive Anonymous can register by repetitive email
     * @param   string  $act        Activation type
     * @param   integer $type       User's type
     * @param   integer $group      Default group of anonymous registered user
     * @param   string  $recover    Users can recover their passwords
     * @return  boolean Success/Failure
     */
    function SaveSettings($method, $anon, $repetitive, $act, $group, $recover)
    {
        $xss = $GLOBALS['app']->loadClass('XSS', 'Jaws_XSS');

        $method     = $xss->parse($method);
        $anon       = $xss->parse($anon);
        $repetitive = $xss->parse($repetitive);
        $recover    = $xss->parse($recover);

        $res = true;
        if ($GLOBALS['app']->Session->GetPermission('Users', 'ManageAuthenticationMethod')) {
            $methods = Jaws::getAuthMethods();
            if ($methods !== false && in_array($method, $methods)) {
                $res = $GLOBALS['app']->Registry->Set('/config/auth_method', $method);
            }
        }
        $res = $res && $GLOBALS['app']->Registry->Set('/config/anon_register', $anon);
        $res = $res && $GLOBALS['app']->Registry->Set('/config/anon_repetitive_email', $repetitive);
        $res = $res && $GLOBALS['app']->Registry->Set('/config/anon_activation', $act);
        $res = $res && $GLOBALS['app']->Registry->Set('/config/anon_group', (int)$group);
        $res = $res && $GLOBALS['app']->Registry->Set('/gadgets/Users/password_recovery', $recover);
        if ($res) {
            $GLOBALS['app']->Registry->Commit('Users');
            $GLOBALS['app']->Registry->Commit('core');
            $GLOBALS['app']->ACL->Commit('core');
            return true;
        }

        return new Jaws_Error(_t('USERS_PROPERTIES_CANT_UPDATE'), _t('USERS_NAME'));
    }

}