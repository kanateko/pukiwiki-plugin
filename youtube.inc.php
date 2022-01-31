<?php
/**
* Youtubeをページに埋め込むプラグイン
*
* @version 1.4
* @author kanateko
* @link https://jpngamerswiki.com/?82f1460fdb
* @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
* 2021-10-12 (ver 1.5)
*   iframeにloading属性を追加
* 2021-07-07 (ver 1.4)
*	埋め込み用のURLを更新
* 2020-02-12 (ver 1.3)
*   レスポンシブ化
*   ループ機能追加
* 2020-02-11 (ver 1.2)
*   インライン型の廃止
*   objectモードの廃止
*   ループ機能追加
*   ユーザー指定機能追加
*   キーワード指定機能追加
* 2018-10-09 初版作成
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
		//'height' =>	315, // 高さ (レスポンシブのため使用しない)
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
	$id = htmlsc($id);
	if (preg_match('/^(list|user|search)=(.+)/', $id, $match)) {
		// リスト
		$video = '/videoseries?listType=' . $list_type[$match[1]] . '&list=' .$match[2];
		$isList = TRUE;
	} else if (preg_match('/^PL[\w\-_]{32}$/', $id)) {
		// 再生リスト (PL + 32文字)
		$video = '/videoseries?listType=' . $list_type['list'] . '&list=' . $id;
		$isList = TRUE;
	} else if (preg_match('/^[\w\-_]{11}$/', $id)) {
		// 動画ID
		$video = '/' . $id;
	} else {
		return $error_msg['id'];
	}

	// オプションの振り分け
	foreach ($args as $arg) {
		$arg = htmlsc($arg);

		if (preg_match('/^(\d+)p*x(\d+)*$/', $arg, $match)) {
			// px指定 or 幅x高さ (旧版との互換性のため)
			$style['width'] = $match[1];
		} else if (preg_match('/(.+)=(\d+)/', $arg, $match)) {
			// その他のオプション
			switch ($match[1]) {
				// 幅
				case 'width':
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
		if(!$isList && $key == 'index' || $isList && $key == 'loop' || $val === 0) continue;
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
	$frame = '<iframe loading="lazy" style="position:absolute;width:100%;height:100%;" src="' . $url . '"
	frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture"
	allowfullscreen></iframe>';

	// 埋め込み部分の全体を作成
	$body = <<<EOD
<div class="youtube_embed" style="max-width:100%;width:{$style['width']}px">
	<div class="youtube_container" style="position:relative;height:0;padding-bottom:56.25%;">
		$frame
	</div>
</div>
EOD;

	return $body;
}
