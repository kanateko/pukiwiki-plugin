<?php
/**
* detailsタグを使ったシンプルな折りたたみプラグイン
*
* @version 1.2
* @author kanateko
* @link https://jpngamerswiki.com/?56478a40a9
* @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
* -- Updates --
* 2024-08-17 v1.2 class指定オプション追加
* 2022-06-17 v1.1 サマリ部分にPukiWiki記法を使用できるよう拡張
* 2020-02-10 v1.0 コードの微調整&整理
* 2019-09-22 v0.8 初版作成
*/

function plugin_expand_init()
{
    $msg['_expand_messages'] = [
        'label_summary' => '詳細を表示'
    ];
    set_plugin_messages($msg);
}

function plugin_expand_convert(...$args)
{
    global $_expand_messages;

    if (count($args) < 1) return;

    $details = array_pop($args);
    $details = preg_replace("/\r|\r\n/", "\n", $details);

    // オプション振り分け
    foreach ($args as $arg) {
        if (preg_match('/^open$/',$arg)) {
            // 展開して表示
            $tag = ' open';
        } elseif (preg_match('/^(color|size|class)=(.+)$/',$arg, $m)) {
            // summaryのスタイル
            $options[$m[1]] = htmlsc($m[2]);
        } else {
            // その他はsummaryとする
            $summary = make_link($arg);
        }
    }

    // 表示内容の最終調整
    $tag = $tag ?: '';
    $summary = $summary ?: $_expand_messages['label_summary'];
    $class = isset($options['class']) ? ' ' . $options['class'] . '"' : '';
    $size = isset($options['size']) ? ' style="font-size:' . $options['size'] . ';"' : '';
    if ($options['color']) {
        $summary = '<span style="color:' . $options['color'] . ';">' . $summary . '</span>';
    }
    $details = convert_html($details);

    // 実際に表示する内容
    $body = <<<EOD
    <details class="plugin-expand$class"$tag>
        <summary$size>$summary</summary>
        $details
    </details>
    EOD;

    return $body;
}

