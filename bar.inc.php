<?php
/**
* バーグラフを表示するプラグイン
* 
* @version 0.8.0
* @author kanateko
* @link https://jpngamerswiki.com/?f51cd63681
* @license http://www.gnu.org/licenses/gpl.ja.html GPL
* -- Update --
* 2020-03-09 初版公開
*/

function plugin_bar_convert() {
    // エラーメッセージ
	$err_msg = array(
		'noargs'    => '#bar | Usage: #bar(length[,label1=,label2=,size=,position=,color=,bgcolor=,width=,height=])',
		'length'    => '#bar | Error: Length needs to be set between 0 to 100.',
        'unknown'   => '#bar | Error: Unknown argument detected.',
        'value'     => '#bar | Error: Incorrect value detected.',
	);
    
	// オプション
	$option = array(
		'label1'     => '',        // ラベル (左)
        'label2'     => '',        // ラベル (右)
        'lsize'      => '12',      // ラベルの大きさ
        'lcolor'     => 'inherit', // ラベルの色
		'color'      => '#cbd1c9', // バーの色
		'bgcolor'    => '#8d8d8d', // 背景色
        'width'      => '300',     // バーの幅
        'height'     => '5',       // バーの高さ
	);
	
	$args = func_get_args();
    $bar = array_shift($args);
    $bar = htmlsc($bar);

    if (func_num_args() == 0) {
        // 引数が空の場合
        return $err_msg['noargs'];
    } else if (!is_numeric($bar) || $bar < 0 || $bar > 100) {
        // 数字でない場合、負の値や101以上である場合
        return $err_msg['length'];
    }

    // オプション判別
    if (!empty($args)) {
        $keys = array_keys($option);
        foreach ($args as $arg) {
            $arg = htmlsc($arg);
            if (strpos($arg, "=")) {
                // "="が含まれていればオプションと判別
                list($key, $val) = explode('=', $arg);
                if (array_search($key, $keys) !== false) {
                    // オプションの指定が正しいか判断
                    if ($key == 'lsize'|'width'|'height' && !is_numeric($val)|$val < 1) {
                        return $err_msg['value'] . " (" . $arg . ")";
                    } else {
                        $option[$key] = $val;
                    }
                } else {
                    return $err_msg['unknown'] . " (" . $key . ")";
                }
            }
        }
    }

    // ラベルが設定されていれば表示する
    if (empty($option['label1'])) {
        $label1 = '';
    } else {
        $label1 = <<<EOD
        <div class="bar-label" style="float:left">{$option['label1']}</div>
        EOD;
    }
    if (empty($option['label2'])) {
        $label2 = '';
    } else {
        $label2 = <<<EOD
        <div class="bar-label" style="float:right">{$option['label2']}</div>
        EOD;
    }
    if (empty($label1) && empty($label2)) {
        $label = '';
    } else {
        $label = <<<EOD
        {$label1}
        {$label2}
        <br>
        EOD;
    }

    // バーグラフ部分作成
    $body = <<<EOD
<div class="bar" style="max-width:100%;width:{$option['width']}px;font-size:{$option['lsize']}px;color:{$option['lcolor']}">
    {$label}
    <div class="bar-graph" style="max-width:100%;width:{$option['width']}px;height:{$option['height']}px;position:relative">
        <div class="bar-back" style="width:100%;height:100%;background:{$option['bgcolor']};position:absolute"></div>
        <div class="bar-front" style="width:{$bar}%;height:100%;background:{$option['color']};position:absolute"></div>
    </div>
</div>
EOD;

    return $body;
}
?>
