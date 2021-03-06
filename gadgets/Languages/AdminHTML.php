<?php
/**
 * Languages Core Gadget Admin
 *
 * @category   GadgetAdmin
 * @package    Languages
 * @author     Ali Fazelzadeh <afz@php.net>
 * @copyright  2007-2012 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/lesser.html
 */
class Languages_AdminHTML extends Jaws_Gadget_HTML
{
    /**
     * Calls default action(MainMenu)
     *
     * @access  public
     * @return  string  XHTML template content
     */
    function Admin()
    {
        $this->AjaxMe('script.js');

        $model = $GLOBALS['app']->LoadGadget('Languages', 'AdminModel');
        $tpl = new Jaws_Template('gadgets/Languages/templates/');
        $tpl->Load('AdminLanguages.html');
        $tpl->SetBlock('Languages');
        $tpl->SetVariable('language',   _t('LANGUAGES_LANGUAGE'));
        $tpl->SetVariable('component',  _t('LANGUAGES_COMPONENT'));
        $tpl->SetVariable('settings',   _t('LANGUAGES_SETTINGS'));
        $tpl->SetVariable('from',       _t('GLOBAL_FROM'));
        $tpl->SetVariable('to',         _t('GLOBAL_TO'));
        $tpl->SetVariable('base_script', BASE_SCRIPT);

        $btnExport =& Piwi::CreateWidget('Button','btn_export',
                                         _t('LANGUAGES_LANGUAGE_EXPORT'), STOCK_DOWN);
        $btnExport->AddEvent(ON_CLICK, 'javascript: export_lang();');
        $tpl->SetVariable('btn_export', $btnExport->Get());

        $tpl->SetBlock('Languages/properties');
        $langId =& Piwi::CreateWidget('Entry', 'lang_code', '');
        $tpl->SetVariable('lang_code', $langId->Get());
        $tpl->SetVariable('lbl_lang_code', _t('LANGUAGES_LANGUAGE_CODE'));

        $langName =& Piwi::CreateWidget('Entry', 'lang_name', '');
        $tpl->SetVariable('lang_name', $langName->Get());
        $tpl->SetVariable('lbl_lang_name', _t('LANGUAGES_LANGUAGE_NAME'));

        if ($this->gadget->GetPermission('ModifyLanguageProperties')) {
            $btnLang =& Piwi::CreateWidget('Button','btn_lang', '', STOCK_SAVE);
            $btnLang->AddEvent(ON_CLICK, 'javascript: save_lang();');
            $tpl->SetVariable('btn_lang', $btnLang->Get());
        }
        $tpl->ParseBlock('Languages/properties');

        $tpl->SetVariable('confirmSaveData',     _t('LANGUAGES_SAVEDATA'));
        $tpl->SetVariable('add_language_title',  _t('LANGUAGES_LANGUAGE_ADD'));
        $tpl->SetVariable('save_language_title', _t('LANGUAGES_LANGUAGE_SAVE'));

        // Langs
        $use_data_lang = $this->gadget->GetRegistry('use_data_lang') == 'true';
        $langs = Jaws_Utils::GetLanguagesList($use_data_lang);
        $tpl->SetBlock('Languages/lang');
        $tpl->SetVariable('selected', '');
        $tpl->SetVariable('code', '');
        $tpl->SetVariable('fullname', _t('LANGUAGES_LANGUAGE_NEW'));
        $tpl->ParseBlock('Languages/lang');

        foreach ($langs as $code => $fullname) {
            $tpl->SetBlock('Languages/lang');
            $tpl->SetVariable('selected', $code=='en'? 'selected="selected"': '');
            $tpl->SetVariable('code', $code);
            $tpl->SetVariable('fullname', $fullname);
            $tpl->ParseBlock('Languages/lang');
        }

        // Components
        $components = $model->GetComponents();
        $componentsName = array('Global', 'Gadgets', 'Plugins');
        foreach ($components as $compk => $compv) {
            if (is_array($compv)) {
                $tpl->SetBlock('Languages/group');
                $tpl->SetVariable('group', $componentsName[$compk]);
                foreach ($compv as $k => $v) {
                    $tpl->SetBlock('Languages/group/item');
                    $tpl->SetVariable('key', "$compk|$v");
                    $tpl->SetVariable('value', $v);
                    $tpl->ParseBlock('Languages/group/item');
                }
                $tpl->ParseBlock('Languages/group');
            } else {
                $tpl->SetBlock('Languages/component');
                $tpl->SetVariable('key', $compk);
                $tpl->SetVariable('value', $compv);
                $tpl->ParseBlock('Languages/component');
            }
        }

        $tpl->SetBlock('Languages/buttons');
        //checkbox_filter
        $check_filter =& Piwi::CreateWidget('CheckButtons', 'checkbox_filter');
        $check_filter->AddEvent(ON_CLICK, 'javascript: filterTranslated();');
        $check_filter->AddOption(_t('LANGUAGES_NOT_SHOW_TRANSLATED'), '', 'checkbox_filter');
        $tpl->SetVariable('checkbox_filter', $check_filter->Get());

        $cancel_btn =& Piwi::CreateWidget('Button','btn_cancel',
                                        _t('GLOBAL_CANCEL'), STOCK_CANCEL);
        $cancel_btn->AddEvent(ON_CLICK, 'javascript: stopAction();');
        $cancel_btn->SetStyle('visibility: hidden;');
        $tpl->SetVariable('cancel', $cancel_btn->Get());

        $save_btn =& Piwi::CreateWidget('Button','btn_save',
                                        _t('GLOBAL_SAVE', _t('LANGUAGES_CHANGES')), STOCK_SAVE);
        $save_btn->AddEvent(ON_CLICK, 'javascript: save_lang_data();');
        $save_btn->SetStyle('visibility: hidden;');
        $tpl->SetVariable('save', $save_btn->Get());
        $tpl->ParseBlock('Languages/buttons');

        $tpl->ParseBlock('Languages');
        return $tpl->Get();
    }

    /**
     * Calls default action(MainMenu)
     *
     * @access  public
     * @param   string  $module 
     * @param   string  $type   
     * @param   string  $langTo 
     * @return  string  XHTML template content
     */
    function GetLangDataUI($module, $type, $langTo)
    {
        $model = $GLOBALS['app']->LoadGadget('Languages', 'AdminModel');
        $tpl = new Jaws_Template('gadgets/Languages/templates/');
        $tpl->Load('LangStrings.html');
        $tpl->SetBlock('LangStrings');

        $langFrom = $this->gadget->GetRegistry('base_lang');
        $data = $model->GetLangData($module, $type, $langTo, $langFrom);
        $color = 'even';
        if (count($data['strings']) > 0) {
            foreach($data['strings'] as $k => $v) {
                $tpl->SetBlock('LangStrings/item');
                $tpl->SetVariable('color', $color);
                $color = ($color=='odd')? 'even' : 'odd';
                if ($v[$langTo] == '') {
                    $tpl->SetVariable('from', '<span style="color: #f00;">' . nl2br($v[$langFrom]) . '</span>');
                } else {
                    $tpl->SetVariable('from', '<span style="color: #000;">' . nl2br($v[$langFrom]) . '</span>');
                }

                $brakeLines = substr_count($v[$langFrom], "\n");
                $rows = floor((strlen($v[$langFrom]) - $brakeLines*2)/42) + $brakeLines;
                if ($brakeLines == 0) {
                    $rows++;
                }

                $tpl->SetVariable('dir', $data['lang_direction']);
                $tpl->SetVariable('row_count', $rows);
                $tpl->SetVariable('height', $rows*18);
                $tpl->SetVariable('field', $k);
                $tpl->SetVariable('to', str_replace('"', '&quot;', $v[$langTo]));
                $tpl->ParseBlock('LangStrings/item');
            }
        }

        foreach($data['meta'] as $k => $v) {
            $tpl->SetBlock('LangStrings/MetaData');
            $tpl->SetVariable('label', $k);
            $tpl->SetVariable('value', $v);
            $tpl->ParseBlock('LangStrings/MetaData');
        }

        $tpl->ParseBlock('LangStrings');
        return $tpl->Get();
    }

    /**
     * Export language
     *
     * @access  public
     * @return  void
     */
    function Export()
    {
        $request =& Jaws_Request::getInstance();
        $lang = $request->get('lang', 'get');

        require_once PEAR_PATH. "File/Archive.php"; 
        $tmpDir = sys_get_temp_dir();
        $tmpFileName = "$lang.tar";
        $tmpArchiveName = $tmpDir. DIRECTORY_SEPARATOR. $tmpFileName;
        $res = File_Archive::extract(File_Archive::read(JAWS_DATA. "languages/$lang", $lang),
                                     File_Archive::toArchive($tmpArchiveName,
                                                             File_Archive::toFiles())
                                    );
        if (!PEAR::isError($res)) {
            Jaws_Utils::Download($tmpArchiveName, $tmpFileName);
        }
        Jaws_Header::Referrer();
    }
}
