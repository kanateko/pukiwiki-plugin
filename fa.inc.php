<?php
/**
 * Font Awesomeのアイコンを表示するプラグイン
 *
 * v5.15.3フリー版 https://fontawesome.com/v5.15/icons?d=gallery&p=2&m=free
 *
 * @version 1.0
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license http://www.gnu.org/licenses/gpl.ja.html GPL
 * -- Update --
 * 2021-07-09 初版作成
 */

 define('FA_ADD_CLASS', ' fa-fw');
 define('FA_OPT_VAR', ['color']);

function plugin_fa_inline()
{
    $msg = array(
        'class'  => '&amp;fa; Error: Invalid class. -> ',
        'option' => '&amp;fa; Error: Invalid option. -> ',
    );

    $args = func_get_args();
    $class = array_pop($args);
    $style = '';

    if (! preg_match('/^fa[srb]? /', $class)) {
        return $msg['class'] . $class;
    }

    if($args) {
        foreach($args as $arg) {
            $arg = htmlsc($arg);
            if (strpos($arg, '=') !== false) {
                list($key, $val) = explode('=', $arg);
                if (in_array($key, FA_OPT_VAR)) {
                    $style .= ' ' . $key . ':' . $val . ';';
                } else {
                    return $msg['option'] . $arg;
                }
            } else if ($arg == 'solid') {
                $style .= ' font-weight:900;';
            } else if ($arg == 'regular') {
                $style .= ' font-weight:400;';
            } else {
                return $msg['option'] . $arg;
            }
        }
        $style = ' style="' . $style . '"';
    }

    return '<i class="' . $class . FA_ADD_CLASS .  '"' . $style . '></i>';
}

?>