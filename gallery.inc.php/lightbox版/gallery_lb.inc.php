<?php
/**
 * lightbox版 画像のギャラリー表示プラグイン
 * 
 * @version 0.5
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license http://www.gnu.org/licenses/gpl.ja.html GPL
 * -- Updates --
 * 
 * 2021-06-17 画像追加機能を追加
 *            他のページの添付画像を表示する機能を追加
 *            画像の横幅を指定する機能を追加
 * 2021-06-16 初版作成
 */

// アップロード可能なファイルの最大サイズ
define('PLUGIN_GALLERY_MAX_FILESIZE', (2048 * 1024)); // default: 2MB

function plugin_gallery_lb_init()
{
    global $head_tags;
	//jsとcssを読み込む
	$head_tags[] = '<script src="' . SKIN_DIR . 'lb/js/lightbox.min.js"></script>';
	$head_tags[] = '<link rel="stylesheet" href="' . SKIN_DIR . 'lb/css/lightbox.min.css" />';
}

function plugin_gallery_convert()
{
    global $vars;
    static $gallery_counts = 0;

    // エラーメッセージ類
    $_err = array (
        'usage'    =>  '#gallery([width=][noadd]){{<br />image>caption<br />}}',
        'unknown'  =>  '#gallery Error: Unknown argument -> ',
    );

    // オプション
    $option = array (
        'width'    =>  '300',
        'noadd'      =>  '',
    );

    $args = func_get_args();
    if (func_num_args() == 0) return $_err['usage'];

    $images = array_pop($args);
    $images = str_replace("\r", "\n", str_replace("\r\n", "\n", $images));
    preg_match_all('/.+\.(?:jpe?g|png|gif)>?.*?\n/i', $images, $matches);

    $item = '';
    $url_base = get_base_uri() . '?plugin=ref&amp;page=';

    // オプション判別
    if (!empty($args)) {
        foreach ($args as $arg) {
            if (preg_match('/(width)=(\d+)/', $arg, $match)) {
                $option[$match[1]] = $match[2];
            } else if (preg_match('/^(noadd)$/', $arg, $match)) {
                $option[$match[1]] = ' style="display:none"';
            } else {
                return $_err['unknown'] . $arg;
            }
        }
    }

    // ギャラリーアイテムの作成
    foreach ($matches[0] as $image) {
        // 添付ファイルのあるページを確認
        if(strpos($image, '/') !== false) {
            list($page, $image) = explode('/', $image);
        } else {
            $page = $vars['page'];
        }
        $url = $url_base . $page;

        // キャプションの有無を判別
        if (strpos($image, '>') !== false) {
            list($image, $cap) = explode('>', $image);
            $item .= '<figure class="gallery-item" style="width:' . $option['width'] . 'px"><a href="' . $url  . '&src=' . $image .'"
             class="gallery-thumb" data-lightbox="gallery-' . $gallery_counts . '"
              data-title="' . $cap . '"><img class="gallery-source" src="' . $url  . '&src=' . $image .'"></a>
              <figcaption class="gallery-caption">' . $cap . '</figcaption></figure>' . "\n";
        } else {
            $item .= '<figure class="gallery-item" style="width:' . $option['width'] . 'px"><a href="' . $url  . '&src=' . $image .'"
             class="gallery-thumb" data-lightbox="gallery-' . $gallery_counts . '">
             <img class="gallery-source" src="' . $url  . '&src=' . $image .'"></a></figure>' . "\n";
        }
    }

    // ギャラリーの作成
    $gallery = <<<EOD
<div class="plugin-gallery">
$item
</div>
<div class="gallery-add"{$option['noadd']}>
    <a href="./?plugin=gallery&gallery_no=$gallery_counts&page={$vars['page']}">画像を追加する</a>
</div>
EOD;

    $gallery_counts++;
    return $gallery;
    
}

function plugin_gallery_lb_action ()
{
    global $vars;
    $page = $vars['page'];
    $gallery_no = $vars['gallery_no'];
    $max_size = number_format(PLUGIN_GALLERY_MAX_FILESIZE/1024);
    $new_item = '';

    // 画像追加用フォーム
    $body = <<<EOD
<div style="margin:10px">
    <span class="small">アップロード可能なファイルの最大サイズ：$max_size KB</span>
    <form enctype="multipart/form-data" action="./" method="post" >
        <input type="hidden" name="plugin" value="gallery" />
        <input type="hidden" name="gallery_no" value="$gallery_no" />
        <input type="hidden" name="page" value="$page" />
        <input type="file" name="attach_file"  accept="image/png, image/jpeg, image/gif" />&nbsp;
        <label for="caption">キャプション: </label>
        <input type="text" name="caption" />
        <input type="submit" name="btn_submit" value="追加"/>
    </form>
</div>
EOD;

    // 添付画像があればアップロード
    if (isset($_FILES['attach_file']) && is_uploaded_file($_FILES['attach_file']['tmp_name'])
     && $_FILES['attach_file']['size'] < PLUGIN_GALLERY_MAX_FILESIZE) {
        $t_file = $_FILES['attach_file']['tmp_name'];
        $file = $_FILES['attach_file']['name'];
        $attach_name = strtoupper(bin2hex($page)) . '_' . strtoupper(bin2hex($file));
        move_uploaded_file($t_file, UPLOAD_DIR . $attach_name);
        $new_item = $file;
    
        // キャプションの有無を判別
        if (!empty($_POST['caption'])) {
            $new_item .= '>' . htmlsc($_POST['caption']);
        }

        // ページ内容を書き換え
        $postdata_old = get_source($page, TRUE, TRUE);
        preg_match_all('/(#gallery[^{]*?{{\n(?:[^}]*?\n)*)}}/', $postdata_old, $matches);
        $gallery_items = $matches[1][$gallery_no];
        $postdata = str_replace($gallery_items, $gallery_items . $new_item . "\n", $postdata_old);
        page_write($page, $postdata, FALSE);

        // 処理が終わったら元のページに戻る
        $uri = get_base_uri() . get_short_url_from_pagename($page);
        header("Location: " . $uri);
    }

    return array('msg' => '画像を追加', 'body' => $body);
}
?>