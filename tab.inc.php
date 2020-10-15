<?php
/**
 * tab.inc.php
 * license: GPL v3
 * version: 1.2
 * release: 2019.06.30
 * auther: kanateko
 * site: https://jpngamerswiki.com/
 * description: 指定した領域のタブ切り替え表示を可能にするプラグイン
 * update: 
 * - 2020.10.16 - v1.2 - タブの最大数を3つ -> 無制限に変更、全体的に作り直し
 */

//タブ分割用の文字列
define('SPLIT_TAG', '#split');

function plugin_tab_init() {
    global $global_tab_counts;
    $global_tab_counts = '0';
}

function plugin_tab_convert()
{
    global $global_tab_counts;

    if (func_num_args() < 1) return "#tab(tab 1, tab 2, ...){{content 1 #split content 2 #split ...}}";
    $args = func_get_args();                         //引数
    $tabs = func_num_args() - 1;                     //タブの個数
    $contents_raw = array_pop($args);                //マルチライン部分
    $contents = explode(SPLIT_TAG, $contents_raw);   //各タブの内容
    $tab_wrap = '';                                  //タブ領域全体

    for ($i = 0; $i < $tabs; $i++) {
        $id = $global_tab_counts . '-' . $i;
        $label = htmlsc($args[$i]);
        $contents[$i] = str_replace("\r", "\n", str_replace("\r\n", "\n", $contents[$i]));
        $content = convert_html($contents[$i]);

        if ($i == 0) { //最初のタブにchecked
            $tab_wrap .= <<<EOD
<input id="tab{$id}" type="radio" name="tab_{$global_tab_counts}" class="tab_switch" checked="checked" /><label class="tab_label" for="tab{$id}">{$label}</label>
<div id="content{$id}" class="tab_content">
    {$content}
</div>

EOD;
        } else {
            $tab_wrap .= <<<EOD
<input id="tab{$id}" type="radio" name="tab_{$global_tab_counts}" class="tab_switch" /><label class="tab_label" for="tab{$id}">{$label}</label>
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

    $global_tab_counts++;

    return $body;
}
?>