<?php
/**
* table内でリスト表示するためのプラグイン
*
* @version 0.1.0
* @author kanateko
* @link https://jpngamerswiki.com/?54760078c9
* @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
*
* 2021-03-25 初版作成
*/

function plugin_ul_convert() {
    if (func_num_args() < 1) return;
    $args = func_get_args();
    $option = array (
	    'style' => 'ul', // スタイル (ul or ol)
	);
    $li = '';

    foreach ($args as $arg) {
        // olオプション判別
        if (preg_match('/^ol$/', $arg)) {
            $option['style'] = 'ol';
        } else {
            // HTML化
            $arg = str_replace('p>', 'li>', convert_html($arg));
            $li .= $arg;
        }
    }

    // リスト作成
    $body = <<<EOD
<{$option['style']} class="list1 list-indent1 list-plugin">
    $li
</{$option['style']}>
EOD;
    return $body;
}

?>
