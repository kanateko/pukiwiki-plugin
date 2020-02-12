<?php
/*
* License: GPLv3
* Version:0.82
* Release:2018/10/09
* Auther: kanateko
* Manual: https://jpngamerswiki.com/?ff7d0a095a
* Description: slickを利用した画像スライドショープラグイン
* -- Update --
* 2020/02/10 (ver 0.82)
* - 公開用にheadに出力するタグを修正。
*/

function plugin_slideshow_init()
{
	global $head_tags;
	//jsとcssを読み込む
	$head_tags[] = '<script type="text/javascript" src="' . SKIN_DIR . 'slick/slick.min.js"></script>';
	$head_tags[] = '<link rel="stylesheet" type="text/css" href="' . SKIN_DIR . 'slick/slick.css" media="screen" />';
	$head_tags[] = '<link rel="stylesheet" type="text/css" href="' . SKIN_DIR . 'slick-theme.css" media="screen" />';
}

function plugin_slideshow_convert()
{
	global $vars;
	$args = func_get_args();
	$option = array(
		'speed' => '3000', // 自動再生時の再生スピード
		'auto'  => 'true', // 自動再生のon/off
	);
	$url_base = get_base_uri() . '?plugin=ref&amp;page='; //画像のあるページのURL
	$contents = ''; // スライダー部分

	//引数が1つ以上あるとスライダーを表示する
	if(func_num_args() < 1) return;

	foreach ($args as $arg){
		$arg = htmlspecialchars($arg);
		// オプション振り分け
		if (preg_match('/^(speed|auto)=/', $arg)) {
			// slickの設定
			list($key, $val) = explode('=', $arg);
			$option[$key] = $val;
		} else {
			// 画像指定
			if (strpos($arg, '/') === false) {
				// 現在のページにある画像
				$page = urlencode($vars['page']);
				$file = $arg;
			} else {
				// 別ページにある画像
				list($page, $file) = explode('/', $arg);
				$page = urlencode($page);
			}
			// URL作成
			$url = $url_base . $page . '&amp;src=' . $file;
		}
		//画像を追加する
		$contents .= <<<EOD
<div class="img_slide">
	<a href = "$url"><img data-lazy="$url" /></a>
</div>

EOD;
		}

		//スライドショー部分とslickの設定
		$body = <<<EOD
<div class='container'>
	<div class='slick-box'>
$contents
	</div>
</div>

<script type="text/javascript">
	$(function(){
		$('.slick-box').slick({
			autoplay: {$option['auto']},
			autoplaySpeed: {$option['speed']},
			dots: true,
			cssEase: 'linear',
			pauseOnFocus: true,
			valiableWidth: true,
			adaptiveHeight: true,
			lazyLoad: 'progressive',
		});
	});
</script>
EOD;
	return $body;
}

?>
