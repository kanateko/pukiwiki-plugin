<?php
/*
 * License: GPLv3
 * Version: 1.01
 * Release: 2019/09/22
 * Auther: kanateko
 * Manual: https://jpngamerswiki.com/?56478a40a9
 * Description: 指定した領域を折りたたみ表示するプラグイン。summaryとdetailsタグを使用。
 * -- Update --
 * 2020/02/10 (ver 1.01)
 * - コードの微調整&整理。
*/

function plugin_expand_convert()
{
  if (func_num_args() < 1) return;

  $option = array (
		'color' => 'inherit', // 文字色
    'size'  => 'inherit', // 文字サイズ
	);
  $args = func_get_args();
  $details = array_pop($args);
  $details = str_replace("\r", "\n", str_replace("\r\n", "\n", $details));

  // オプション振り分け
  foreach ($args as $arg) {
    $arg = htmlspecialchars($arg);
    // 展開して表示
    if (preg_match('/^open$/',$arg)) {
      $tag = '<details  class="plugin_expand" open>';
    }
    // summaryのスタイル
    else if (preg_match('/^(color|size)=.+/',$arg)) {
      list($key, $val) = explode('=',$arg);
      $option[$key] = $val;
    }
    // その他はsummaryとする
    else {
      $summary = $arg;
    }
  }

  // 表示内容の最終調整
  if (empty($tag)) $tag = '<details  class="plugin_expand">';
  if (empty($summary)) $summary = '詳細を表示';
  $details = convert_html($details);

  // 実際に表示する内容
  $body = <<<EOD
  $tag
	<summary style="color:{$option['color']};font-size:{$option['size']};">$summary</summary>
  $details
  </details>
EOD;
  return $body;
}

?>
