<?php
/*
* License: GPLv3
* Version:1.21
* Release:2018/10/09
* Auther: kanateko
* Manual: https://jpngamerswiki.com/?ff7d0a095a
* Description: Youtubeをページに埋め込むプラグイン。多機能。
* -- Update --
* 2020/02/11 (ver 1.21)
* - インライン型の廃止。
* - objectモードの廃止。
* - ループ機能追加。
* - ユーザー指定機能追加。
* - キーワード指定機能追加。
*/

function plugin_youtube_convert() {
	// エラーメッセージ
	$error_msg = array(
		'time' => '#youtube Error: 再生の開始位置は終了位置よりも前に設定してください。',
		'arg'  => '#youtube Error: オプションの設定が正しくありません。',
		'id'   => '#youtube Error: 動画IDが設定されていません。',
	);

	if (func_num_args() < 1) return $error_msg['id'];

	// スタイル
	$style = array(
		'width'  =>	560, // 幅
		'height' =>	315, // 高さ
	);
	// オプション
	$option = array(
		'loop'     => 0, // ループ再生: off
		'autoplay' => 0, // 自動再生: off
		'start'    => 0, // 開始時間: off
		'end'      => 0, // 終了時間: off
		'index'    => 0, // リストの開始位置: 0
	);
	// リストのタイプ
	$list_type = array(
		'list'   => 'playList',     // 再生リスト
		'user'   => 'user_uploads', // ユーザー
		'search' => 'search',       // 検索結果
	);

	$args = func_get_args();
	$isList = FALSE;
	$params = '';
	$url_base = 'https://www.youtube.com/embed';

	//動画IDなどの取得
	$id = array_shift($args);
	$id = htmlspecialchars($id);
	if (preg_match('/^(list|user|search)=(.+)/', $id, $match)) {
		// リスト
		$video = '?listType=' . $list_type[$match[1]] . '&list=' .$match[2];
		$isList = TRUE;
	} else if (preg_match('/^PL[\w\-_]{32}$/', $id)) {
		// 再生リスト (PL + 32文字)
		$video = '?listType=' . $list_type['list'] . '&list=' . $id;
		$isList = TRUE;
	} else if (preg_match('/^[\w\-_]{11}$/', $id)) {
		// 動画ID
		$video = '/' . $id;
	} else {
		return $error_msg['id'];
	}

	// オプションの振り分け
	foreach ($args as $arg) {
		$arg = htmlspecialchars($arg);

		if (preg_match('/^(\d+)x(\d+)$/', $arg, $match)) {
			// 幅x高さ
			$style['width'] = $match[1];
			$style['height'] = $match[2];
		} else if (preg_match('/(.+)=(\d+)/', $arg, $match)) {
			// その他のオプション
			switch ($match[1]) {
				// 幅と高さ
				case 'width':
				case 'height':
					$style[$match[1]] = $match[2];
					break;
				// 0か1のみのオプション
				case 'loop':
				case 'autoplay':
					if ($match[2] > 1) $match[2] = 1;
				// 0と1以外設定可能なオプション
				case 'index':
				case 'start':
				case 'end':
					$option[$match[1]] = $match[2];
					break;
				default:
					return $error_msg['arg'];
			}
		} else {
			return $error_msg['arg'];
		}
	}

	// 開始時間と終了時間をチェック
	if ($option['end'] > 0 && $option['start'] > $option['end']) return $error_msg['time'];

	// パラメータの作成
	foreach ($option as $key => $val) {
		// 不要なパラメータを排除
		if(!$isList && $key == 'index' || $lisList && $key == 'loop' || $val === 0) continue;
		// ループ用設定
		if ($key == 'loop') {
			$key = 'version3&loop';
			$val = '1&playlist=' . $id;
		}
		// 必要なパラメータを追加
		if (empty($params)) {
			// 最初のパラメータ
			$params = $isList ? '&' : '?';
			$params .= $key . '=' . $val;
		} else {
			// 2つ目以降
			$params .= '&' . $key . '=' . $val;
		}
	}

	// 埋め込みURLを作成
	$url = $url_base . $video . $params;

	// フレーム部分を作成
	$frame = '<iframe width="' . $style['width'] . '" height="' . $style['height'] . '" src="' . $url . '"
	frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
	allowfullscreen></iframe>';

	// 埋め込み部分の全体を作成
	$body = <<<EOD
<div class="youtube_embed">
	$frame
</div>
EOD;

	return $body;
}
?>
