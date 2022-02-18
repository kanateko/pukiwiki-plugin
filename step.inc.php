<?php
/**
 * 縦型ステップフロー作成プラグイン
 *
 * @version 0.3
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * -- Update --
 * 2022-02-18 v0.3 マーカーのスタイルを変更する機能を追加
 *            v0.2 ラベルを変更する機能を追加
 * 2022-02-17 v0.1 初版作成
 */

// 各フローのラベルに使用する文字列 (番号抜き)
define('STEP_LABEL_STRING', 'STEP');
// ステップフローのコンテナのタグ
define('STEP_LIST_TAG', 'ol');
// マーカーのデフォルトスタイル
define('STEP_MARKER_DEFAULT', 'border');

/**
 * 初期化
 */
function plugin_step_init()
{
    global $_step_messages;

    $_step_messages = [
        'msg_usage'   => '<p>#step{{<br>#:&lt;title&gt;<br>&lt;content&gt;<br>...<br>}}<p>',
        'msg_unknown' => '#step Error: Unknown argument -> '
    ];
}

/**
 * ブロック型
 */
function plugin_step_convert()
{
    global $_step_messages;

    $args = func_get_args();
    if (count($args) == 0) return $_step_messages['msg_usage'];

    $source = preg_replace("/\r|\r\n/", "\n", array_pop($args));
    $parts = get_stepflow_parts($source);

    if (! empty($args)) {
        $options = get_options($args);
        if (! is_array($options)) return $options;
    } else {
        $options = null;
    }

    $body = convert_stepflow($parts['titles'], $parts['contents'], $options);

    return $body;
}

/**
 * 各フローのタイトルとコンテンツを取得
 *
 * @param  string $source マルチライン部分
 * @return array  $parts  タイトルとコンテンツを含めた配列
 */
function get_stepflow_parts($source)
{
    // 入れ子の同プラグインを一時的に置換
    $step_nested = [];
    preg_match_all('/#step([^{]*?)({{2,})/', $source, $matches);
    foreach ($matches[0] as $i => $start) {
        $start = str_replace($matches[1][$i], preg_quote($matches[1][$i]), $start);
        $end = str_replace('{', '}', $matches[2][$i]);
        preg_match('/' . $start . '[\s\S]+?' . $end . '/', $source, $step);
        $source = str_replace($step[0], '%step' . $i . '%', $source);
        $step_nested[$i] = $step[0];
    }

    // 各フローのタイトルを取得
    preg_match_all("/#:(.+?)\n/", $source, $matches);
    foreach ($matches[1] as $i => $title) {
        $parts['titles'][] = make_link($title);
        $replace = $i == 0 ? '' : '%title%';
        $source = str_replace($matches[0][$i], $replace, $source);
    }

    // 一時的に置換していた部分をもとに戻してからコンテンツ部分を取得
    foreach ($step_nested as $i => $step) $source = str_replace('%step' . $i . '%', $step, $source);
    $parts['contents'] = explode('%title%', $source);
    foreach ($parts['contents'] as $i => $content) $parts['contents'][$i] = convert_html($content);

    return $parts;
}

/**
 * オプションの判別
 *
 * @param  array $args    引数
 * @return array $options オプションの配列
 */
function get_options($args)
{
    global $_step_messages;

    $options = [];

    foreach ($args as $arg) {
        $arg = htmlsc($arg);
        if (preg_match('/^(label|pre|marker|mcolor)=(.+)$/', $arg, $matches)) {
            $options[$matches[1]] = $matches[2];
        } else {
            return '<p>' . $_step_messages['msg_unknown'] . $arg . '</p>';
        }
    }

    return $options;
}

/**
 * ステップフローを組み立てる
 *
 * @param  array   $titles   タイトルの配列
 * @param  array   $contents コンテンツの配列
 * @param  array   $options  オプションの配列
 * @return string  $body     HTML変換済みのステップフロー
 */
function convert_stepflow($titles, $contents, $options)
{
    $step_counts = 1;
    // タグ
    $tag = STEP_LIST_TAG;
    $child = preg_match('/ul|ol/', $tag) ? 'li' : 'div';
    // ラベル
    $label = $options['label'] ?: STEP_LABEL_STRING;
    $pre = $options['pre'] ? $options['pre'] . '-' : '';
    // マーカー
    $options['marker'] = $options['marker'] ?? STEP_MARKER_DEFAULT;
    $marker = ' data-marker-style="' . $options['marker'] . '"';
    if ($options['mcolor']) {
        $mcolor = ' style="border-color:' . $options['mcolor'];
        $mcolor .= $options['marker'] == 'border' ? '"' : ';background-color:' . $options['mcolor'] . '"';
    } else {
        $mcolor='';
    }

    $body = '';
    foreach ($titles as $i => $title) {
        $body .= <<<EOD
<$child class="step-flow">
    <div class="step-label">
        <span class="step-marker"$mcolor$marker></span>
        <span class="step-label-str">$label</span><span class="step-label-num">$pre$step_counts</span>
    </div>
    <div class="step-title">$title</div>
    <div class="step-content">
        $contents[$i]
    </div>
</$child>
EOD;
        $step_counts++;
    }
    $body = '<' . $tag .  ' class="plugin-step">' . "\n" . $body . "\n" . '</' . $tag .  '>';

    return $body;
}