<?php
/**
* 指定した領域のタブ切り替え表示を可能にするプラグイン
*
* @version 1.2.0
* @author kanateko
* @link https://jpngamerswiki.com/?f51cd63681
* @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
* 
* 2020-10-16 タブの最大数を3つ -> 無制限に変更、全体的に作り直し (ver 1.2.0)
* 2019-06-30 初版作成、タブは3つに限定
*/

//タブ分割用の文字列
define('SPLIT_TAG', '#split');

function plugin_tab_convert()
{
    static $tab_counts = 0;

    if (func_num_args() < 1) return "#tab(tab 1, tab 2, ...){{content 1 #split content 2 #split ...}}";
    $args = func_get_args();                         //引数
    $tabs = func_num_args() - 1;                     //タブの個数
    $contents_raw = array_pop($args);                //マルチライン部分
    $contents = explode(SPLIT_TAG, $contents_raw);   //各タブの内容
    $tab_wrap = '';                                  //タブ領域全体

    for ($i = 0; $i < $tabs; $i++) {
        $id = $tab_counts . '-' . $i;
        $label = htmlsc($args[$i]);
        $contents[$i] = str_replace("\r", "\n", str_replace("\r\n", "\n", $contents[$i]));
        $content = convert_html($contents[$i]);

        if ($i == 0) { //最初のタブにchecked
            $tab_wrap .= <<<EOD
<input id="tab{$id}" type="radio" name="tab_{$tab_counts}" class="tab_switch" checked="checked" /><label class="tab_label" for="tab{$id}">{$label}</label>
<div id="content{$id}" class="tab_content">
    {$content}
</div>

EOD;
        } else {
            $tab_wrap .= <<<EOD
<input id="tab{$id}" type="radio" name="tab_{$tab_counts}" class="tab_switch" /><label class="tab_label" for="tab{$id}">{$label}</label>
<div id="content{$id}" class="tab_content">
    {$content}
</div>

EOD;
        }
    }

    $body = <<<EOD
<div class="tab_wrap">
    {$tab_wrap}
</div>
EOD;

    $tab_counts++;

    return $body;
}
