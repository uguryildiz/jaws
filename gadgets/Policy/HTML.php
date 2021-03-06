<?php
/**
 * Policy Core Gadget
 *
 * @category   Gadget
 * @package    Policy
 * @author     Ali Fazelzadeh <afz@php.net>
 * @copyright  2008-2012 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/lesser.html
 */
class Policy_HTML extends Jaws_Gadget_HTML
{
    /**
     * Calls default action
     *
     * @access  public
     * @return  string template content
     */
    function DefaultAction()
    {
        header('Location: '. BASE_SCRIPT);
    }

    /**
     * Tricky way to get the captcha image...
     * @access  public
     * @return PNG image
     */
    function Captcha()
    {
        $status = $this->gadget->GetRegistry('captcha');
        if (($status == 'DISABLED') ||
            ($status == 'ANONYMOUS' && $GLOBALS['app']->Session->Logged())) {
            return false;
        }

        static $objCaptcha;
        if (!isset($objCaptcha)) {
            $objCaptcha = array();
        }

        $dCaptcha = $this->gadget->GetRegistry('captcha_driver');
        if (!isset($objCaptcha[$dCaptcha])) {
            require_once JAWS_PATH . 'gadgets/Policy/captchas/' . $dCaptcha . '.php';
            $objCaptcha[$dCaptcha] = new $dCaptcha();
        }

        $objCaptcha[$dCaptcha]->Image();
    }

}