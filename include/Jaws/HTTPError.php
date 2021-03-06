<?php
/**
 * Show the Jaws Page not found message
 *
 * @category   Application
 * @package    Core
 * @author     Jonathan Hernandez <ion@suavizado.com>
 * @author     Ali Fazelzadeh <afz@php.net>
 * @copyright  2006-2012 Jaws Development Group
 * @license    http://www.gnu.org/copyleft/lesser.html
 */
class Jaws_HTTPError
{
    function Get($code, $title = null, $message = null)
    {
        $xss = $GLOBALS['app']->loadClass('XSS', 'Jaws_XSS');
        switch ($code) {
            case 404:
                if (isset($_SERVER['REQUEST_URI']) && !empty($_SERVER['REQUEST_URI'])) {
                    $uri = $_SERVER['REQUEST_URI'];
                } elseif (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
                    $uri = $_SERVER['PHP_SELF'] . '?' .$_SERVER['QUERY_STRING'];
                } else {
                    $uri = '';
                }

                if (empty($message)) {
                    $uri = $xss->filter(urldecode($uri));
                    $message = _t('GLOBAL_HTTP_ERROR_CONTENT_404', $uri);
                }
                header($xss->filter($_SERVER['SERVER_PROTOCOL'])." 404 Not Found");
                $title = empty($title)? _t('GLOBAL_HTTP_ERROR_TITLE_404') : $title;
                break;

            case 403:
                header($xss->filter($_SERVER['SERVER_PROTOCOL'])." 403 Forbidden");
                $title   = empty($title)? _t('GLOBAL_HTTP_ERROR_TITLE_403') : $title;
                $message = empty($message)? _t('GLOBAL_HTTP_ERROR_CONTENT_403') : $message;
                break;

            case 500:
                header($xss->filter($_SERVER['SERVER_PROTOCOL'])." 500 Internal Server Error");
                $title   = empty($title)? _t('GLOBAL_HTTP_ERROR_TITLE_500') : $title;
                $message = empty($message)? _t('GLOBAL_HTTP_ERROR_CONTENT_500') : $message;
                break;

            case 503:
                header($xss->filter($_SERVER['SERVER_PROTOCOL'])." 503 Service Unavailable");
                $title   = empty($title)? _t('GLOBAL_HTTP_ERROR_TITLE_503') : $title;
                $message = empty($message)? _t('GLOBAL_HTTP_ERROR_CONTENT_503') : $message;
                break;

            default:
                $title   = empty($title)? _t("GLOBAL_HTTP_ERROR_TITLE_$code") : $title;
                $message = empty($message)? _t("GLOBAL_HTTP_ERROR_CONTENT_$code") : $message;
        }

        // if current theme has a error code html file, return it, if not return the messages.
        $theme = $GLOBALS['app']->GetTheme();
        $site_name = $GLOBALS['app']->Registry->Get('site_name', 'Settings', JAWS_COMPONENT_GADGET);
        if (file_exists($theme['path'] . "$code.html")) {
            $tpl = new Jaws_Template();
            $tpl->Load("$code.html");
            $tpl->SetBlock($code);

            //set global site config
            $direction = _t('GLOBAL_LANG_DIRECTION');
            $dir  = $direction == 'rtl' ? '.' . $direction : '';
            $brow = $GLOBALS['app']->GetBrowserFlag();
            $brow = empty($brow)? '' : '.'.$brow;

            $tpl->SetVariable('.dir', $dir);
            $tpl->SetVariable('.browser', $brow);
            $tpl->SetVariable('site-name',   $site_name);
            $tpl->SetVariable('site-title',  $site_name);
            $tpl->SetVariable('site-slogan', $GLOBALS['app']->Registry->Get('site_slogan', 'Settings', JAWS_COMPONENT_GADGET));
            $tpl->SetVariable('site-author',      $GLOBALS['app']->Registry->Get('site_author', 'Settings', JAWS_COMPONENT_GADGET));
            $tpl->SetVariable('site-copyright',   $GLOBALS['app']->Registry->Get('copyright', 'Settings', JAWS_COMPONENT_GADGET));
            $tpl->SetVariable('site-description', $GLOBALS['app']->Registry->Get('site_description', 'Settings', JAWS_COMPONENT_GADGET));

            $tpl->SetVariable('title',   $title);
            $tpl->SetVariable('content', $message);

            $tpl->ParseBlock($code);
            return $tpl->Get();
        }

        return "<div class=\"gadget\"><h2>{$title}</h2><div class=\"content\">{$message}</div></div>";
    }

}