<?php
/**
* table内でリスト表示するためのプラグイン
*
* @version 0.2
* @author kanateko
* @link https://jpngamerswiki.com/?54760078c9
* @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
* -- Updates --
* 2022-03-27 v0.2 リストを横並びにする機能を追加
* 2021-03-25 v0.1 初版作成
*/

// デフォルトスタイル
define('UL_DEFAULT_STYLE', 'ul');
// デフォルト整列方向 v = 縦, h = 横
define('UL_DEFAULT_DIRECTION', 'v');

function plugin_ul_convert()
{
    if (func_num_args() < 1) return;
    $args = func_get_args();
    $options = array (
	    'style'     => UL_DEFAULT_STYLE,
        'direction' => UL_DEFAULT_DIRECTION,
	);
    $items = [];
    $li = '';

    // オプションの判別
    foreach ($args as $arg) {
        if (preg_match('/^(ul|ol)$/', $arg, $m_style)) {
            // スタイル変更
            $options['style'] = $m_style[1];
        } elseif (preg_match('/^(h|v)$/', $arg, $m_direction)) {
            // 整列方向変更
            $options['direction'] = $m_direction[1];
        } else {
            // リストアイテム
            $items[] = $arg;
        }
    }

    // リストを整形
    foreach ($items as $item) {
        $item = preg_replace('/^~/', '', $item);
        $item = str_replace('p>', 'li>', convert_html($item));
        $li .= $item . "\n";
    }

    $direction = $options['direction'] == 'h' ? ' data-direction="' . $options['direction'] . '"' : '';

    // リスト作成
    $body = <<<EOD
<{$options['style']} class="list1 list-indent1 list-plugin"$direction>
    $li
</{$options['style']}>
EOD;
    return $body;
}

