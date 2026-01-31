<?php
/**
* ページの有無でリンクを制御するプラグイン
*
* @version 0.1.0
* @author kanateko
* @link https://jpngamerswiki.com/?f51cd63681
* @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
* -- Updates --
* 2026-01-31 v0.1.0 初版作成
*/

function plugin_npnl_inline(string ...$args): string
{
    $text = trim(array_pop($args));
    if (is_page($text)) return make_link('[[' . $text . ']]');

    return $text;
}