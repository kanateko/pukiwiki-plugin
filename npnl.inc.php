<?php
/**
* ページの有無でリンクを制御するプラグイン
*
* @version 0.2.0
* @author kanateko
* @link https://jpngamerswiki.com/?f51cd63681
* @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
* -- Updates --
* 2026-02-12 v0.2.0 相対パスに対応
*                   ベースネームで表示するオプションを追加
* 2026-01-31 v0.1.0 初版作成
*/

function plugin_npnl_inline(string ...$args): string
{
    global $vars;

    $text = trim(array_pop($args));
    $options = [];

    if (! empty($args)) {
        foreach ($args as $arg) {
            $arg = trim(htmlsc($arg));
            if ($arg === 'base') $options['base'] = true;
        }
    }

    if (str_contains($text, './')) $text = get_fullname($text, $vars['page']);

    if (is_page($text)) {
        $text = $options['base'] ? basename($text) . '>' . $text : $text;
        return make_link('[[' . $text . ']]');
    }

    return $text;
}