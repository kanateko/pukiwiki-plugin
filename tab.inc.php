<?php
/**
* 指定した領域のタブ切り替え表示を可能にするプラグイン
*
* @version 1.3
* @author kanateko
* @link https://jpngamerswiki.com/?f51cd63681
* @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
*
* 2022-02-08 v1.3 別タイプの書式を追加
*            v1.2 コードを整理
*                 ラベルの数が足りない場合のエラーを追加
* 2020-10-16 v1.1 タブの最大数を3つ -> 無制限に変更
*            v1.0 全体的に作り直し
* 2019-06-30 v0.1 初版作成、タブは3つに限定
*/

//タブ分割用の文字列
define('TAB_SPLIT_TAG', '#split');

/**
 * 初期設定
 */
function plugin_tab_init()
{
    global $_tab_messages;

    $_tab_messages = [
        'msg_usage'  => '#tab(label 1, label 2, ...){{<br>
                         content 1<br>' . TAB_SPLIT_TAG . '<br>
                         content 2<br>...<br>}}',
        'msg_exceed' => '#tab Error: The number of contents exceeds the number of labels.'
    ];
}

/**
 * ブロック型
 */
function plugin_tab_convert()
{
    global $_tab_messages;
    static $tab_counts = 0;

    if (func_num_args() < 1) return '<p>' . $_tab_messages['msg_usage'] . '<p>';
    $args = func_get_args();
    $source = preg_replace("/\r|\r\n/", "\n", array_pop($args));
    if (empty($args)) list($labels, $source) = plugin_tab_alt_format($source);
    else $labels = $args;
    $contents = explode(TAB_SPLIT_TAG, $source);
    if (count($labels) < count($contents)) return '<p>' . $_tab_messages['msg_exceed'] . '</p>';

    // タブの作成
    $tabs = '';
    foreach ($labels as $i => $label) {
        $label = htmlsc($label);
        $checked = $i == 0 ? ' checked="checked"' : '';
        $content = convert_html($contents[$i]);
        $id = $tab_counts . '-' .  $i;

        $tabs .= <<<EOD
<input id="tab$id" type="radio" name="tab_$tab_counts" class="tab-switch"$checked />
<label class="tab-label" for="tab$id">$label</label>
<div id="content$id" class="tab-content">
$content
</div>
EOD;
    }

    $body = '<div class="plugin-tab">' . "\n" . $tabs . "\n" . '</div>';
    $tab_counts++;

    return $body;
}

/**
 * 別書式のラベルとコンテンツ判別
 *
 * @param string $source 引数のマルチライン部分
 * @return array ラベルとコンテンツ
 */
function plugin_tab_alt_format($source)
{
    preg_match_all("/#:(.+)\n/", $source, $matches);
    $labels = [];
    foreach ($matches[1] as $i => $label) {
        $labels[] = $label;
        $replace = $i == 0 ? '' : TAB_SPLIT_TAG . "\n";
        $source = str_replace($matches[0][$i], $replace, $source);
    }

    return array($labels, $source);
}
