<?php
/**
 * Jaws Upgrade Stage - From 0.8.16 to 0.8.17
 *
 * @category   Application
 * @package    UpgradeStage
 * @author     Ali Fazelzadeh <afz@php.net>
 * @copyright  2012 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/lesser.html
 */
class Upgrader_0816To0817 extends JawsUpgraderStage
{
    /**
     * Builds the upgader page.
     *
     * @access  public
     * @return  string A block of valid XHTML to display an introduction and form.
     */
    function Display()
    {
        $tpl = new Jaws_Template(UPGRADE_PATH  . 'stages/0816To0817/templates/');
        $tpl->Load('display.html', false, false);
        $tpl->SetBlock('0816To0817');

        $tpl->setVariable('lbl_info',  _t('UPGRADE_VER_INFO', '0.8.16', '0.8.17'));
        $tpl->setVariable('lbl_notes', _t('UPGRADE_VER_NOTES'));
        $tpl->SetVariable('next',      _t('GLOBAL_NEXT'));

        $tpl->ParseBlock('0816To0817');
        return $tpl->Get();
    }

    /**
     * Does any actions required to finish the stage, such as DB queries.
     *
     * @access  public
     * @return  bool|Jaws_Error  Either true on success, or a Jaws_Error
     *                          containing the reason for failure.
     */
    function Run()
    {
        // Connect to database
        require_once JAWS_PATH . 'include/Jaws/DB.php';
        $GLOBALS['db'] = new Jaws_DB($_SESSION['upgrade']['Database']);
        if (Jaws_Error::IsError($GLOBALS['db'])) {
            _log(JAWS_LOG_DEBUG,"There was a problem connecting to the database, please check the details and try again");
            return new Jaws_Error(_t('UPGRADE_DB_RESPONSE_CONNECT_FAILED'), 0, JAWS_ERROR_WARNING);
        }

        // Create application
        include_once JAWS_PATH . 'include/Jaws.php';
        $GLOBALS['app'] = new Jaws();
        $GLOBALS['app']->loadClass('ACL', 'Jaws_ACL');
        $GLOBALS['app']->loadClass('Registry', 'Jaws_Registry');
        $GLOBALS['app']->loadClass('Translate', 'Jaws_Translate');
        $GLOBALS['app']->Registry->Init();

        // This is needed for most gadgets
        require_once JAWS_PATH . 'include/Jaws/Gadget.php';
        require_once JAWS_PATH . 'include/Jaws/Template.php';
        require_once JAWS_PATH . 'include/Jaws/Header.php';
        $GLOBALS['app']->loadClass('Map', 'Jaws_URLMapping');

        // Input datas
        $timestamp = $GLOBALS['db']->Date();

        //registry keys
        $GLOBALS['app']->Registry->Set('/version', JAWS_VERSION);
        $GLOBALS['app']->Registry->Set('/last_update', $timestamp);

        // Commit the changes so they get saved
        $GLOBALS['app']->Registry->commit('core');

        // ACL keys
        $GLOBALS['app']->ACL->DeleteKey('/priority');

        $gadgets = array('Users', 'Policy');
        foreach ($gadgets as $gadget) {
            $result = Jaws_Gadget::UpdateGadget($gadget);
            if (Jaws_Error::IsError($result)) {
                _log(JAWS_LOG_DEBUG,"There was a problem upgrading core gadget: $gadget");
                return new Jaws_Error(_t('UPGRADE_VER_RESPONSE_GADGET_FAILED', $gadget), 0, JAWS_ERROR_ERROR);
            }
        }

        _log(JAWS_LOG_DEBUG,"Cleaning previous maps cache data files - step 0.8.16->0.8.17");
        //Make sure user don't have any data/maps stuff
        $path = JAWS_DATA . 'maps';
        if (!Jaws_Utils::Delete($path, false)) {
            _log(JAWS_LOG_DEBUG,"Can't delete $path");
        }

        _log(JAWS_LOG_DEBUG,"Cleaning previous registry cache data files - step 0.8.16->0.8.17");
        //Make sure user don't have any data/cache/registry stuff
        $path = JAWS_DATA . 'cache/registry';
        if (!Jaws_Utils::Delete($path, false)) {
            _log(JAWS_LOG_DEBUG,"Can't delete $path");
        }

        _log(JAWS_LOG_DEBUG,"Cleaning previous acl cache data files - step 0.8.16->0.8.17");
        //Make sure user don't have any data/cache/acl stuff
        $path = JAWS_DATA . 'cache/acl';
        if (!Jaws_Utils::Delete($path, false)) {
            _log(JAWS_LOG_DEBUG,"Can't delete $path");
        }

        return true;
    }

}