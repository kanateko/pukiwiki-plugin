<?php
/**
 * 添付ファイル一覧表示用プラグイン 配布版
 *
 * attach.inc.phpを改造してアップロード・削除・名前変更時にキャッシュを更新させるようにする必要あり
 *
 * @version 0.4
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license http://www.gnu.org/licenses/gpl.ja.html GPL
 * -- Update --
 * 2022-01-21 v0.4 添付ファイルの一括削除機能を追加
 *                 添付ファイルのリンクを修正
 *            v0.3 キャッシュの削除機能を追加
 *            v0.2 キャッシュ機能を追加
 * 2022-01-20 v0.1 初版作成
 */

// キャッシュ利用の許可
// 要attach.inc.phpの改変
define('ATTACHLIST_ALLOW_CACHE', false);
// キャッシュのディレクトリ
define('ATTACHLIST_CACHE_DIR', CACHE_DIR . 'attachlist/');
// ファイルサイズをキロバイトではなくバイト表記にする
define('ATTACHLIST_DISPLAY_BYTE', false);

/**
 * ブロック型
 */
function plugin_attachlist_convert()
{
    global $vars;

    $page = encode($vars['page']);
    $dir = UPLOAD_DIR;
    $cache = ATTACHLIST_CACHE_DIR . $page . '.dat';

    if (file_exists($cache) && ATTACHLIST_ALLOW_CACHE) {
        $attachlist = file_get_contents($cache);
    } else {
        $attachlist = attachlist_update_cache($page, $dir, $cache);
    }

    return '<div class="attachlist" style="margin-top:20px">' . $attachlist . '</div>';
}

/**
 * アクション型
 *
 * 添付ファイルの一括操作。v0.4時点では削除のみ可能。
 */
function plugin_attachlist_action()
{
    global $vars;

    if (! isset($vars['page'])) return;
    $msg = 'ファイルの一括操作';
    if (isset($vars['mode'])) {
        switch($vars['mode']) {
            case 'confirm':
                return attachlist_confirmation($msg, $vars['page']);
        }
    } else {
        return attachlist_authentification($msg, $vars['page']);
    }
}

/**
 * キャッシュの更新
 *
 * @param  string $page  エンコード済みのページ名
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
    $e_page = urlencode(decode($page));
    $ref = '[[%name%:' . $uri . '?cmd=attach&pcmd=open&file=%ename%&refer=' . $e_page . ']]';
    $info = '&size(12){&#91;[[詳細:' . $uri . '?cmd=attach&pcmd=info&file=%ename%&refer=' . $e_page . ']]&#93;};';

    $body = '|~ファイル名|~ファイルサイズ|~アップロード日時|h' . "\n";
    $body .= '|380|SIZE(14):RIGHT:200|SIZE(14):CENTER:200|c' . "\n";
    foreach ($files as $file) {
        $e_name = urlencode($file['name']);
        $body .= '|' . str_replace('%name%', $file['name'], str_replace('%ename%', $e_name, $ref)) . ' ' . str_replace('%ename%', $e_name, $info) . '|' . $file['size'] . '|' . $file['time'] . '|' . "\n";
    }
    $ctrl = 'RIGHT:&#91;[[ファイルの一括操作>' . $uri . '?cmd=attachlist&page=' . $e_page . ']]&#93;';
    $body = convert_html('ファイル数：' . count($files) . "\n" . $body . "\n" . $ctrl);

    // キャッシュの生成
    if (ATTACHLIST_ALLOW_CACHE) file_put_contents($cache, $body);

    return $body;
}

/**
 * ページに添付されたファイルの情報を取得する
 *
 * @param  string $page  エンコード済みのページ名
 * @param  string $dir   添付ファイルのあるディレクトリ
 * @return array  $files 各添付ファイルの情報
 */
function attachlist_get_files($page, $dir)
{
    // ページに添付されたファイルの一覧を取得
    $pattern = $dir . $page . '_' . '*';
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
 * キャッシュの削除
 *
 * @param  string $refer 対象のページ名
 * @return void
 */
function attachlist_delete_cache($refer)
{
    $refer = encode($refer);
    $cache = ATTACHLIST_CACHE_DIR . $refer . '.dat';

    if (file_exists($cache) && ATTACHLIST_ALLOW_CACHE) {
        unlink($cache);
    }
}

/**
 * ファイル一括操作の管理パスワード認証
 *
 * @param  string $msg  タブに表示する文章
 * @param  string $page 対象のページ名
 * @return array       各種フォーム
 */
function attachlist_authentification($msg, $page)
{
    global $vars;

    // ページ名のチェック
    if (! is_page($page)) return array('msg' => $msg, 'body' => '<p>ページが存在しません</p>');

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
    $files = attachlist_get_files(encode($page), UPLOAD_DIR);
    $body = '';
    foreach ($files as $i => $file) {
        // チェックボックス付きのリストを作成
        $body .=
            '<li><input type="checkbox" name="param[]" value="file=' . urlencode($file['name']) .
            '&refer=' . urlencode($page) . '"><a href="' . get_base_uri() . '?cmd=attach&pcmd=open&file='
            . urlencode($file['name']) . '&refer=' . urlencode($page) . '">' . $file['name'] . '</a></li>' . "\n";
    }
    $body = '<ul>' . "\n" . $body . "\n" . '</ul>';

    // 選択用フォームの作成
    $body = <<<EOD
    <form method="post" action="./">
        $body
        <input type="hidden" name="cmd" value="attachlist">
        <input type="hidden" name="mode" value="confirm">
        <input type="hidden" name="page" value="$page">
        <input type="submit" name="delete" value="削除">
    </form>
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

    // 選択した添付ファイルのリストを作成
    $del_list = '';
    if ($vars['param']) {
        foreach ($vars['param'] as $i => $val) {
            preg_match('/file=(.+)\&refer=(.+)/', $val, $matches);
            $del_list .= '<li>
            <input type="hidden" name="param[' . $i . ']" value="' . $matches[0] . '">
            <input type="hidden" name="file_al[' . $i . ']" value="' . urldecode($matches[1]) . '">
            <input type="hidden" name="refer_al[' . $i . ']" value="' . urldecode($matches[2]) . '">
            <input type="hidden" name="age_al[' . $i . ']" value="0">'
            . urldecode($matches[1]) . '</li>' . "\n";
        }
        $del_list = '<ul>' . $del_list . '</ul>';
    } else {
        // ファイルが一つも選択されていなかった場合はエラー
        return array('msg' => $msg, 'body' => '<p>ファイルが選択されていません</p>');
    }

    // 最終確認用フォームの作成
    $auth_failed = '<p>パスワードが違います</p>' . "\n";
    $body = <<<EOD
    <p>以下のファイルを削除します</p>
    <form method="post" action="./">
        <input type="hidden" name="cmd" value="attachlist">
        <input type="hidden" name="mode" value="confirm">
        <input type="hidden" name="page" value="$page">
        $del_list
        <input type="password" name="pass">
        <input type="submit" value="実行">
    </form>
EOD;

    // パスワードのチェック
    if ($vars['pass']) {
        if (pkwk_login($vars['pass'])) {
            // パスワードがあっていればファイルの操作を開始
            return array('msg' => $msg, 'body' => attachlist_delete_files($page));
        } else {
            return array('msg' => $msg, 'body' => $auth_failed . $body);
        }
    } else {
        return array('msg' => $msg, 'body' => $body);
    }
}

/**
 * 添付ファイルの一括削除
 *
 * @param  string $page 対象のページ名
 * @return string $body 削除完了時のメッセージ
 */
function attachlist_delete_files($page)
{
    global $vars;

    $dir = UPLOAD_DIR;
    $file = $vars['file_al'];
    $refer = $vars['refer_al'];

    // 選択された添付ファイルとログファイルを削除
    foreach ($vars['param'] as $i => $param) {
        if (empty($param[$i])) continue;
        $t_file = $dir . encode($refer[$i]) . '_' . encode($file[$i]);
        $t_log = $t_file . '.log';
        unlink($t_file);
        unlink($t_log);
    }

    // 削除したファイルの一覧を作成
    $body = '';
    foreach ($file as $i => $name) {
        $body .= '<li>' . $name . '</li>' . "\n";
    }
    $body = '<ul>' . "\n" . $body . "\n" . '</ul>';
    $body = '<p>以下のファイルを削除しました</p>' . "\n" . $body . "\n" ;
    $body .= '<p><a href="' . get_base_uri() . '?' . urlencode($page) . '">ページに戻る</a></p>';

    // 添付ファイル一覧のキャッシュをクリア
    attachlist_delete_cache($page);

    return $body;
}

?>
