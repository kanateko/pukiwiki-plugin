<?php
/**
 * photoswipe版 画像のギャラリー表示プラグイン
 *
 * @version 1.7
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * -- Updates --
 * 2021-09-29 v1.7 ファイルとキャプションのセパレータを変更するための設定を追加
 * 2021-07-26 v1.6 対応する拡張子にwebpを追加 (一覧表示のみ)
 *                 初期化用コードの挿入部分を少し変更
 * 2021-07-11 v1.5 初期化用コードの呼び出しを1回のみに変更
 * 2021-07-01 v1.4 階層化されたページの添付ファイルを正常に表示できなかった問題を修正
 * 2021-06-22 v1.3 短縮URLを導入してあるかどうかで処理を変えるよう変更
 *                 一覧画像の高さ指定機能を追加
 *            v1.2 アップロード後元のページに戻る際にFatalErrorが出る問題を修正
 *                 一覧画像の高さが自動調整されなかった問題を修正
 * 2021-06-21 v1.1 画像の縁取り無効オプションを追加
 *                 切り抜きがsquareの場合はキャプションを表示するよう変更
 * 2021-06-20 v1.0 ページの編集権限をチェックする機能を追加
 *                 画像追加ボタンのデザインを調整
 *                 画像一覧の左寄せ・中央・右寄せを設定する機能を追加
 *                 画像一覧の画像を正方形・円に切り抜いて表示する機能を追加
 *                 画像一覧でキャプションを非表示にする機能を追加
 * 2021-06-19 v0.7 添付されたファイルのフォーマット判別を厳正化
 * 2021-06-18 v0.6 使用するライブラリをlightnox.jsからphotoswipe.jsに変更
 * 2021-06-17 v0.5 画像追加機能を追加
 *                 画像追加ボタンの表示/非表示切り替え機能を追加
 *            v0.2 他のページの添付画像を表示する機能を追加
 *                 画像の横幅を指定する機能を追加
 * 2021-06-16 v0.1 初版作成
 */

// アップロード可能なファイルの最大サイズ
define('PLUGIN_GALLERY_MAX_FILESIZE', (1024 * 1024)); // default: 1MB
// URL短縮機能を導入してあるか
define('PLUGIN_GALLERY_USE_SHORT_URL', false);
// ファイルとキャプションのセパレータ
define('PLUGIN_GALLERY_SEPARATOR', '>');

function plugin_gallery_init()
{
    global $head_tags;
    //jsとcssを読み込む
    $head_tags[] = '<script src="' . SKIN_DIR . 'pswp/photoswipe.min.js"></script>';
    $head_tags[] = '<script src="' . SKIN_DIR . 'pswp/photoswipe-ui-default.min.js"></script>';
    $head_tags[] = '<script src="' . SKIN_DIR . 'pswp/photoswipe-simplify.min.js"></script>';
    $head_tags[] = '<link rel="stylesheet" href="' . SKIN_DIR . 'pswp/photoswipe.css" />';
    $head_tags[] = '<link rel="stylesheet" href="' . SKIN_DIR . 'pswp/default-skin/default-skin.css" />';
}

function plugin_gallery_convert()
{
    global $vars;
    static $gallery_counts = 0;

    // エラーメッセージ類
    $_err = array (
        'usage'    =>  '#gallery([width=][noadd]){{<br />image>caption<br />}}',
        'unknown'  =>  '#gallery Error: Unknown argument -> '
    );

    // オプション
    $option = array (
        'size'     =>  'height:180px',
        'position' =>  ' flex-center',
        'noadd'    =>  '',
        'nocap'    =>  '',
        'nowrap'   =>  '',
        'trim'     =>  ''
    );

    $args = func_get_args();
    if (func_num_args() == 0) return $_err['usage'];

    $images = array_pop($args);
    $images = str_replace("\r", "\n", str_replace("\r\n", "\n", $images));
    preg_match_all('/.+\.(?:jpe?g|png|gif|webp)>?.*?\n/i', $images, $matches);

    $item = '';
    $url_base = get_base_uri() . '?plugin=ref&amp;page=';

    // オプション判別
    if (!empty($args)) {
        foreach ($args as $arg) {
            if (preg_match('/^(width|height)=(\d+)$/', $arg, $match)) {
                $option['size'] = $match[1] . ':' . $match[2] . 'px';
            } else {
                switch ($arg) {
                    case 'left':
                    case 'right':
                    case 'center':
                        $option['position'] = ' flex-' . $arg;
                        break;
                    case 'circle':
                        $option['nocap'] = ' hidden';
                    case 'square':
                        $option['trim'] = ' trim-' . $arg;
                        break;
                    case 'nocap':
                    case 'noadd':
                        $option[$arg] = ' hidden';
                        break;
                    case 'nowrap':
                        $option[$arg] = ' ' . $arg;
                        break;
                    default:
                        return $_err['unknown'] . $arg;
                }
            }
        }
        if (!empty($option['trim'])) {
            $option['size'] = str_replace('height', 'width', $option['size']);
        }
    }

    // ギャラリーアイテムの作成
    foreach ($matches[0] as $image) {
        // 添付ファイルのあるページを確認
        if(strpos($image, '/') !== false) {
            preg_match('/(.+)\/([^\/]+)/', $image, $matches);
            $page = $matches[1];
            $image = $matches[2];
        } else {
            $page = $vars['page'];
        }
        $url = $url_base . $page;

        // キャプションの有無を判別
        $item .= '<figure class="gallery-item' . $option['nowrap'] . '" style="' . $option['size'] . '">
        <a href="' . $url  . '&src=%image%"class="gallery-thumb"%data-cap%">
        <img class="gallery-source" src="' . $url  . '&src=%image%"></a>
        %figcap%</figure>' . "\n";

        if (strpos($image, PLUGIN_GALLERY_SEPARATOR) !== false) {
            list($image, $cap) = explode(PLUGIN_GALLERY_SEPARATOR, $image);
            $item = str_replace('%data-cap%', ' data-caption="' . $cap . '"', $item);
            $item = str_replace('%figcap%', '<figcaption class="gallery-caption' . $option['nocap'] . '">' . $cap . '</figcaption>', $item);
        } else {
            $item = str_replace('%data-cap%', '', $item);
            $item = str_replace('%figcap%', '', $item);
        }
        $item = str_replace('%image%', $image, $item);
    }

    // photoswipeの初期化用コード
    $js = '';
    if ($gallery_counts === 0) {
        $js = <<<EOD
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                photoswipeSimplify.init();
            });
        </script>
        EOD;
    }

    // ギャラリーの作成
    $gallery = <<<EOD
    <div class="plugin-gallery{$option['position']}{$option['trim']}" id="gallery-$gallery_counts" data-pswp>
        $item
    </div>
    <div class="gallery-add{$option['noadd']}">
        <a href="./?plugin=gallery&gallery_no=$gallery_counts&page={$vars['page']}">画像を追加する</a>
    </div>
    $js
    EOD;

    $gallery_counts++;
    return $gallery;
}

/**
 * 画像の追加
 */
function plugin_gallery_action()
{
    global $vars;
    $page = $vars['page'];
    $gallery_no = $vars['gallery_no'];
    $file = $_FILES['attach_file'];
    $max_size = number_format(PLUGIN_GALLERY_MAX_FILESIZE/1024);

    //編集権限のチェック
    check_editable($page);

    // 画像追加用フォーム
    $body = <<<EOD
    <div style="margin:10px">
        <span class="small">アップロード可能なファイルの最大サイズ：$max_size KB</span>
        <form enctype="multipart/form-data" action="./" method="post" >
            <input type="hidden" name="plugin" value="gallery" />
            <input type="hidden" name="gallery_no" value="$gallery_no" />
            <input type="hidden" name="page" value="$page" />
            <input type="file" name="attach_file"  accept="image/png, image/jpeg, image/gif, image/webp" />&nbsp;
            <label for="caption">キャプション: </label>
            <input type="text" name="caption" />
            <input type="submit" name="btn_submit" value="追加"/>
        </form>
    </div>
    EOD;

    // 添付画像があればアップロード
    if (isset($file) && is_uploaded_file($file['tmp_name'])) {
        // ファイルがアップロードに適しているか判定
        $upload_err = plugin_gallery_check_file($file, $max_size);
        if ($upload_err !== false) {
            return array('msg' => '画像を追加', 'body' => $upload_err . $body);
        }

        // アップロードされた画像を保存
        $attach_name = strtoupper(bin2hex($page)) . '_' . strtoupper(bin2hex($file['name']));
        move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $attach_name);
        $new_item = $file['name'];

        // キャプションの有無を判別
        if (!empty($_POST['caption'])) {
            $new_item .= '>' . htmlsc($_POST['caption']);
        }

        // ページ内容を書き換え
        $postdata_old = get_source($page, true, true);
        preg_match_all('/(#gallery[^{]*?{{\n(?:[^}]*?\n)*)}}/', $postdata_old, $matches);
        $gallery_items = $matches[1][$gallery_no];
        $postdata = str_replace($gallery_items, $gallery_items . $new_item . "\n", $postdata_old);
        page_write($page, $postdata, false);

        // 処理が終わったら元のページに戻る
        $pagename = PLUGIN_GALLERY_USE_SHORT_URL ? get_short_url_from_pagename($page) : '?' . $page;
        $uri = get_base_uri() . $pagename;
        header("Location: " . $uri);
    }

    return array('msg' => '画像を追加', 'body' => $body);
}

/**
 * 添付ファイルチェック
 */
function plugin_gallery_check_file($file, $max_size)
{
    $upload_err = array (
        'size'    =>  '<span>ファイルサイズが' . $max_size . 'KBを超えています。</span><br />' . "\n",
        'format'  =>  '<span>画像ファイル （jpg, png, gif）以外はアップロードできません。</span><br />' . "\n"
    );

    // 最大サイズ
    if ($file['size'] > PLUGIN_GALLERY_MAX_FILESIZE) {
        return $upload_err['size'];
    }

    // フォーマット
    $mime_type = getimagesize($file['tmp_name'])['mime'];
    if(!preg_match('/image\/(jpeg|png|gif|webp)/i', $mime_type)) {
        return $upload_err['format'];
    }

    return false;
}
