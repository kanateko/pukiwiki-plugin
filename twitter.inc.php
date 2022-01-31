<?php
/**
* Twitter埋め込みプラグイン
*
* @version 1.1.0
* @author kanateko
* @link https://jpngamerswiki.com/?0f5ec903b8
* @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
* -- Update --
* 2020-03-10 単一ツイートの埋め込み機能を追加 (ver 1.1)
* 2018-09-17 初版作成
*/

function plugin_twitter_convert()
{
	if (func_num_args() < 1) return "#twitter([username] or [tweetURL])";

	$args = func_get_args();
	$src = htmlsc(array_shift($args));

	// 埋め込みの種類判別
	if (strpos($src, '://') === false) $isTimeLine = true;	// タイムライン
	else $isTimeLine = false;								// 単一ツイート

	// オプション
	$option = array(
		'width'		=>	'540',		// 横幅
		'height'	=>	'450',		// 高さ
		'theme'		=>	'light',	// テーマ色
		'noconv'	=>	''			// 会話非表示 (単一ツイート時のみ)
	);

	// オプション判別
	foreach ($args as $arg) {
		$arg = htmlsc($arg);
		$err_msg = 'Error: Invalid Argument (' . $arg . ')';

		if (preg_match('/(\d+)x(\d+)/', $arg, $match)) {
			// 横x縦
			$option['width'] = $match[1];
			$option['height'] = $match[2];
		} else if (strpos($arg, '=') !== false) {
			// 横幅、高さ、テーマ色
			list($key, $val) = explode('=', $arg);
			if (!key_exists($key, $option)||$key == 'noconv') return $err_msg;
			$option[$key] = $val;
		} else {
			switch ($arg) {
				case 'dark':
					// テーマ色
					$option['theme'] = 'dark';
					break;
				case 'noconv':
					// 会話非表示
					$option['noconv'] = 'data-conversation="none" ';
					break;
				default:
					return $err_msg;
			}
		}
	}

	// 埋め込み部分を作成
	if ($isTimeLine) {
		$html = '<a class="twitter-timeline" data-lang="ja"
			data-width="' . $option['width'] . '" data-height="' . $option['height'] . '" data-theme="' . $option['theme'] . '"
				href="https://twitter.com/'. $src . '?ref_src=twsrc%5Etfw">Tweets by '. $src . '</a>';
	} else {
		$html = '<blockquote class="twitter-tweet" data-lang="ja"
			' . $option['noconv'] . 'data-width="' . $option['width'] . '" data-theme="' . $option['theme'] . '">
				 <a href="' . $src . '?ref_src=twsrc%5Etfw">tweet</a></blockquote>';
		}
	$html .= '<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>';

	return $html;
}

?>
