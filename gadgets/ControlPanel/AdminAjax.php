<?php
/**
 * ControlPanel AJAX API
 *
 * @category   Ajax
 * @package    ControlPanel
 * @author     Ali Fazelzadeh <afz@php.net>
 * @copyright  2012 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/lesser.html
 */
class ControlPanelAdminAjax extends Jaws_Ajax
{
    /**
     * Constructor
     *
     * @access  public
     * @param   object  $model  Jaws_Model reference
     */
    function ControlPanelAdminAjax(&$model)
    {
        $this->_Model =& $model;
    }

}