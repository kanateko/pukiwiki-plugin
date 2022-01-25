<?php
/**
 * 添付ファイル一覧表示用プラグイン 配布版
 *
 * @version 1.0
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license http://www.gnu.org/licenses/gpl.ja.html GPL
 * -- Update --
 * 2022-01-26 v1.0 attachプラグインを改造しなくても動作するように仕様を変更
 *                 設定のキャッシュ利用の許可をデフォルトでtrueに変更
 *                 一括操作に凍結・解凍を追加
 *                 一括操作ボタンを左上に移動
 *                 一括操作画面で凍結されたファイルにマーク (*) を追加
 *                 全選択/解除用スクリプトを簡略化
 * 2022-01-23 v0.5 全てのキャッシュを削除する機能を追加
 *                 添付ファイルの削除に失敗した場合の処理を追加
 *                 一括操作画面に全選択/解除ボタンを追加
 * 2022-01-21 v0.4 添付ファイルの一括削除機能を追加
 *                 添付ファイルのリンクを修正
 *            v0.3 キャッシュの削除機能を追加
 *            v0.2 キャッシュ機能を追加
 * 2022-01-20 v0.1 初版作成
 */

// キャッシュ利用の許可
define('ATTACHLIST_ALLOW_CACHE', true);
// キャッシュのディレクトリ
define('ATTACHLIST_CACHE_DIR', CACHE_DIR . 'attachlist/');
// ファイルサイズをキロバイトではなくバイト表記にする
define('ATTACHLIST_DISPLAY_BYTE', false);

require_once(PLUGIN_DIR . 'attach.inc.php');

/**
 * ブロック型
 */
function plugin_attachlist_convert()
{
    global $vars;

    $page = $vars['page'];
    $dir = UPLOAD_DIR;
    $cache = ATTACHLIST_CACHE_DIR . encode($page) . '.dat';

    if (ATTACHLIST_ALLOW_CACHE && file_exists($cache) && attachlist_is_fresh_cache($dir, $cache)) {
        $attachlist = file_get_contents($cache);
    } else {
        $attachlist = attachlist_update_cache($page, $dir, $cache);
    }

    return '<div class="attachlist" style="margin-top:20px">' . $attachlist . '</div>';
}

/**
 * アクション型
 */
function plugin_attachlist_action()
{
    global $vars;

    if (empty($vars['page'])) return attachlist_clear_all_cache();
    $msg = 'ファイルの一括操作';
    if (isset($vars['pcmd'])) {
        switch($vars['pcmd']) {
            case 'upload':
                $attach = plugin_attach_action();
                $form = array('msg' => $attach['msg'], 'body' => $attach['body'] . plugin_attachlist_convert());
                return $form;
            case 'confirm':
                return attachlist_confirmation($msg, $vars['page']);
        }
    } else {
        return attachlist_authentification($msg, $vars['page']);
    }
}

/**
 * アップロードフォルダとキャッシュの更新日時を比較
 *
 * @param  string $dir      アップロードフォルダのパス
 * @param  string $cache    キャッシュのパス
 * @return bool   $is_fresh キャッシュが新しいかどうか
 */
function attachlist_is_fresh_cache($dir, $cache)
{
    $t_dir = filemtime($dir);
    $t_cache = filemtime($cache);

    $is_fresh = $t_dir < $t_cache;

    return $is_fresh;
}

/**
 * キャッシュの更新
 *
 * @param  string $page  対象のページ名
 * @param  string $dir   添付ファイルのあるディレクトリ
 * @param  string $cache 添付ファイル一覧のキャッシュのパス
 * @return string $body  html_convert済みの添付ファイル一覧
 */
function attachlist_update_cache($page, $dir, $cache)
{
    // キャッシュフォルダの確認と作成
    if (! file_exists(ATTACHLIST_CACHE_DIR) && ATTACHLIST_ALLOW_CACHE) {
        mkdir(ATTACHLIST_CACHE_DIR, 0755);
        chmod(ATTACHLIST_CACHE_DIR, 0755);
    }

    // 各添付ファイルの情報を取得する
    $files = attachlist_get_files($page, $dir);
    if (empty($files)) return;

    // 添付ファイルの情報をテーブルに整形
    $uri = get_base_uri(PKWK_URI_ABSOLUTE);
    $e_page = urlencode($page);
    $ref = '[[%name%:' . $uri . '?cmd=attach&pcmd=open&file=%ename%&refer=' . $e_page . ']]';
    $info = '&size(12){&#91;[[詳細:' . $uri . '?cmd=attach&pcmd=info&file=%ename%&refer=' . $e_page . ']]&#93;};';

    $body = '|~ファイル名|~ファイルサイズ|~アップロード日時|h' . "\n";
    $body .= '|380|SIZE(14):RIGHT:200|SIZE(14):CENTER:200|c' . "\n";
    foreach ($files as $file) {
        $e_name = urlencode($file['name']);
        $body .= '|' . str_replace('%name%', $file['name'], str_replace('%ename%', $e_name, $ref)) . ' ' . str_replace('%ename%', $e_name, $info) . '|' . $file['size'] . '|' . $file['time'] . '|' . "\n";
    }
    $ctrl = 'ファイル数：' . count($files) . ' &#91;[[ファイルの一括操作>' . $uri . '?cmd=attachlist&page=' . $e_page . ']]&#93;';
    $body = convert_html($ctrl . "\n" . $body);

    // キャッシュの生成
    if (ATTACHLIST_ALLOW_CACHE) file_put_contents($cache, $body);

    return $body;
}

/**
 * ページに添付されたファイルの情報を取得する
 *
 * @param  string $page  対象のページ名
 * @param  string $dir   添付ファイルのあるディレクトリ
 * @return array  $files 各添付ファイルの情報
 */
function attachlist_get_files($page, $dir)
{
    // ページに添付されたファイルの一覧を取得
    $pattern = $dir . encode($page) . '_' . '*';
    $s_files = glob($pattern);
    $files = array();
    foreach ($s_files as $i => $file) {
        // 各ファイルの情報を取得
        preg_match('/.+_([^\.]+)$/', $file, $matches);
        if (empty($matches[1])) continue;
        $files[$i]['name'] = decode($matches[1]);
        $files[$i]['time'] = format_date(filemtime($file));
        $files[$i]['size'] = attachlist_get_filesize($file);
    }

    return $files;
}

/**
 * 添付ファイルのサイズを取得する
 *
 * @param  string $file 添付ファイルのパス
 * @return void
 */
function attachlist_get_filesize($file)
{
    $size = filesize($file);

    if (ATTACHLIST_DISPLAY_BYTE) {
    // バイト表示
        return number_format($size, 1) . ' B';
    } else {
        return number_format($size / 1024, 1) . ' KB';
    }
}

/**
 * ファイル一括操作の管理パスワード認証
 *
 * @param  string $msg  タブに表示する文章
 * @param  string $page 対象のページ名
 * @return array        各種フォーム
 */
function attachlist_authentification($msg, $page)
{
    global $vars;

    // ページ名のチェック
    if (! is_page($page)) {
        $body = '<p>ページ "' . htmlsc($page) . '" は存在しません</p>';
        return array('msg' => $msg, 'body' => $body);
    }

    // 認証用フォームの作成
    $auth_failed = '<p>パスワードが違います</p>' . "\n";
    $body = <<<EOD
<form method="post" action="./">
    <input type="hidden" name="cmd" value="attachlist">
    <input type="hidden" name="page" value="$page">
    <input type="password" name="pass">
    <input type="submit" value="認証">
</form>
EOD;

    // パスワードのチェック
    if ($vars['pass']) {
        if (pkwk_login($vars['pass'])) {
            // パスワードがあっていれば選択用フォームを表示
            return array('msg' => $msg, 'body' => attachlist_listup_files($page));
        } else {
            return array('msg' => $msg, 'body' => $auth_failed . $body);
        }
    } else {
        return array('msg' => $msg, 'body' => $body);
    }
}

/**
 * チェックボックス付きの添付ファイル一覧を取得
 *
 * @param  string $page 対象のページ名
 * @return string $body 添付ファイルの一覧
 */
function attachlist_listup_files($page)
{
    // 各添付ファイルの情報を取得
    $files = attachlist_get_files($page, UPLOAD_DIR);
    $body = '';
    foreach ($files as $i => $file) {
        // 凍結されているファイルにマークを追加
        $obj = new AttachFile($page, $file['name'], 0);
        $obj->getstatus();
        $freezed = $obj->status['freeze'] ? '*' : '';
        // チェックボックス付きのリストを作成
        $body .=
            '<li><input type="checkbox" class="check_list" name="file[]" value="' . $file['name'] .
            '"><a href="' . get_base_uri() . '?cmd=attach&pcmd=open&file='
            . urlencode($file['name']) . '&refer=' . urlencode($page) . '">'
            . $file['name'] . '</a>' . $freezed . '</li>' . "\n";
    }
    $body = '<ul>' . "\n" . $body . "\n" . '</ul>';

    // 全選択/解除用スクリプト
    $js = <<<EOD
<script>
    const check_all = document.querySelector("#check_all");
    const check_list = document.querySelectorAll(".check_list");

    check_all.addEventListener('change', () => {
        if (check_all.checked) {
            check_list.forEach (checkbox => (checkbox.checked = true));
        } else {
            check_list.forEach (checkbox => (checkbox.checked = false));
        }
    });
</script>
EOD;

    // 選択用フォームの作成
    $body = <<<EOD
<p>* = 凍結されたファイル</p>
<p style="user-select:none">
    <input type="checkbox" id="check_all">
    <label for="check_all">全て選択 / 解除</label>
</p>
<form method="post" action="./">
    $body
    <input type="hidden" name="cmd" value="attachlist">
    <input type="hidden" name="pcmd" value="confirm">
    <input type="hidden" name="page" value="$page">
    <input type="submit" name="mode" value="削除">
    <input type="submit" name="mode" value="凍結">
    <input type="submit" name="mode" value="解凍">
</form>
$js
EOD;

    return $body;
}

/**
 * 操作するファイルの最終確認
 *
 * @param  string $msg  タブに表示する文章
 * @param  string $page 対象のページ名
 * @return array        各種フォームもしくは操作完了のメッセージ
 */
function attachlist_confirmation($msg, $page)
{
    global $vars;

    // モード選択
    $mode = isset($vars['mode']) ? htmlsc($vars['mode']) : '';

    // 選択した添付ファイルのリストを作成
    $targets = '';
    if ($vars['file']) {
        foreach ($vars['file'] as $i => $val) {
            $targets .= '<li><input type="hidden" name="file[' . $i . ']" value="' . $val . '">' . $val . '</li>' . "\n";
        }
        $targets = '<ul>' . $targets . '</ul>';
    } else {
        // ファイルが一つも選択されていなかった場合はエラー
        return array('msg' => $msg, 'body' => '<p>ファイルが選択されていません</p>');
    }

    // 最終確認用フォームの作成
    $auth_failed = '<p>パスワードが違います</p>' . "\n";
    $body = <<<EOD
<p>以下のファイルを{$mode}します</p>
<form method="post" action="./">
    <input type="hidden" name="cmd" value="attachlist">
    <input type="hidden" name="pcmd" value="confirm">
    <input type="hidden" name="mode" value="$mode">
    <input type="hidden" name="page" value="$page">
    <input type="hidden" name="refer" value="$page">
    <input type="hidden" name="age" value="0">
    $targets
    <input type="password" name="pass">
    <input type="submit" value="実行">
</form>
EOD;

    // パスワードのチェック
    if ($vars['pass']) {
        if (pkwk_login($vars['pass'])) {
            // パスワードがあっていればファイルの操作を開始
            if (! empty($mode)) $body = attachlist_manage_files($mode, $page);
            return array('msg' => $msg, 'body' => $body);
        } else {
            return array('msg' => $msg, 'body' => $auth_failed . $body);
        }
    } else {
        return array('msg' => $msg, 'body' => $body);
    }
}

/**
 * 添付ファイルの一括操作
 *
 * @param  string $mode 削除/凍結/解凍
 * @param  string $page 対象のページ
 * @return string $body 操作したファイルの一覧
 */
function attachlist_manage_files($mode, $page)
{
    global $vars, $_attach_messages;

    $files = $vars['file'];
    $result = array();
    $lines = array();

    // 行う処理の判別
    switch($mode) {
        case '削除': $pcmd = 'delete'; break;
        case '凍結': $pcmd = 'freeze'; break;
        case '解凍': $pcmd = 'unfreeze'; break;
        default : return '<p>不明な処理：' . htmlsc($mode) . '</p>';
    }
    // attachの処理成功時のメッセージ
    $success = $_attach_messages['msg_' . $pcmd . 'd'];
    // 使用するattachの関数
    if ($pcmd == 'unfreeze') {
        $attach = 'attach_freeze';
    } else {
        $attach = 'attach_' . $pcmd;
    }

    // ファイルごとに処理
    foreach ($files as $vars['file']) {
        $file = $vars['file'];
        if ($pcmd == 'delete') {
            $result = $attach();
        } else {
            $result = $attach($pcmd == 'freeze' ? true : false);
        }

        if ($result['msg'] == $success) {
            // 処理成功のメッセージを受け取ったらファイル名を記録
            $lines[] = $file;
        } else if ($result['msg'] == $_attach_messages['msg_info']) {
            // 凍結されたファイルを削除しようとした場合のメッセージ
            return $_attach_messages['msg_isfreeze'];
        } else {
            return $result['msg'];
        }
    }

    // 処理したファイルの一覧を表示
    $body ='';
    foreach ($lines as $line) {
        $body .= '<li>' . $line . '</li>' . "\n";
    }
    $body = '<ul>' . "\n" . $body . "\n" . '</ul>';
    $body = '<p>以下のファイルを' . $mode . 'しました</p>' . "\n" . $body . "\n" ;
    $body .= '<p><a href="' . get_base_uri() . '?' . urlencode($page) . '">ページに戻る</a></p>';

    return $body;
}

/**
 * キャッシュファイルの一括削除
 *
 * @return array キャッシュクリアの確認・完了画面
 */
function attachlist_clear_all_cache()
{
    global $vars;

    $msg = 'キャッシュのクリア';
    $pattern = ATTACHLIST_CACHE_DIR . '*.dat';

    // 認証用フォームの作成
    $auth_failed = '<p>パスワードが違います</p>' . "\n";
    $body = <<<EOD
<p>添付ファイル一覧のキャッシュをクリアします</p>
<form method="post" action="./">
    <input type="hidden" name="cmd" value="attachlist">
    <input type="password" name="pass">
    <input type="submit" value="実行">
</form>
EOD;

    // パスワードのチェック
    if ($vars['pass']) {
        if (pkwk_login($vars['pass'])) {
            // 認証できたらキャッシュの検索開始
            $caches = glob($pattern);
            if (empty($caches)) {
                // datファイルがなければ終了
                $body = '<p>キャッシュが見つかりませんでした</p>';
            } else {
                // datファイルがあればそれらを削除
                $body = '<p>以下のページのキャッシュを削除しました<p>' . "\n";
                foreach ($caches as $i => $cache) {
                    preg_match('/.+\/(.+).dat/', $cache, $matches);
                    $page = decode($matches[1]);
                    $attrs = get_page_link_a_attrs($page);
                    if (unlink($cache)) {
                        // 削除に成功したページをリストアップ
                        $body .= '<li><a href="' . get_page_uri($page) . '" class="' .
                        $attrs['class'] . '" data-mtime="' . $attrs['data_mtime'] .
                        '">' . $page . '</a></li>' . "\n";
                    } else {
                        // 削除に失敗したら処理を終了
                        $body = '<p>"' . htmlsc(decode($matches[1])) . '" のキャッシュが削除できませんでした</p>';
                        break;
                    }
                }
            }
            return array('msg' => $msg, 'body' => $body);
        } else {
            return array('msg' => $msg, 'body' => $auth_failed . $body);
        }
    } else {
        return array('msg' => $msg, 'body' => $body);
    }
}

?>
