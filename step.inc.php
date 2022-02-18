<?php
/**
 * 縦型ステップフロー作成プラグイン
 *
 * @version 0.1
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * -- Update --
 * 2022-02-17 v0.1 初版作成
 */

// 各フローのラベルに使用する文字列 (番号抜き)
define('STEP_LABEL_STRING', 'STEP ');
// ステップフローのコンテナのタグ
define('STEP_LIST_TAG', 'ol');

/**
 * 初期化
 */
function plugin_step_init()
{
    global $_step_messages;

    $_step_messages = [
        'msg_usage' => '<p>#step{{<br>#:&lt;title&gt;<br>&lt;content&gt;<br>...<br>}}<p>'
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

    $body = convert_stepflow($parts['titles'], $parts['contents']);

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
    for ($i = 0; preg_match('/#step(\{{2,})/', $source, $start); $i++) {
        $end = str_replace('{', '}', $start[1]);
        preg_match('/' . $start[0] . '[\s\S]+?' . $end . '/', $source, $step);
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
 * ステップフローを組み立てる
 *
 * @param array   $titles   タイトルの配列
 * @param array   $contents コンテンツの配列
 * @return string $body     HTML変換済みのステップフロー
 */
function convert_stepflow($titles, $contents)
{
    $step_counts = 1;
    $label = STEP_LABEL_STRING;
    $tag = STEP_LIST_TAG;
    $child = preg_match('/ul|ol/', $tag) ? 'li' : 'div';

    $body = '';
    foreach ($titles as $i => $title) {
        $body .= <<<EOD
<$child class="step-flow">
    <div class="step-label">$label$step_counts</div>
    <div class="step-title">$title</div>
    <div class="step-content">
        $contents[$i]
    </div>
</$child>
EOD;
        $step_counts++;
    }
    $body = '<' . $tag .  'class="plugin-step">' . "\n" . $body . "\n" . '</' . $tag .  '>';

    return $body;
}