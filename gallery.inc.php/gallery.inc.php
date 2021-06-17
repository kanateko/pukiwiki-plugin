<?php
/**
 * 画像のギャラリー表示プラグイン
 * 
 * @version 0.2
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license http://www.gnu.org/licenses/gpl.ja.html GPL
 * -- Updates --
 * 2021-06-17 他のページの添付画像を表示する機能を追加
 *            画像の横幅を指定する機能を追加
 * 2021-06-16 初版作成
 */

function plugin_gallery_init()
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

    $_err = array (
        'unknown'  =>  '#gallery Error: Unknown argument -> ',
    );

    $option = array (
        'width'    =>  '300',
    );

    $args = func_get_args();
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

    $gallery_counts++;

    $gallery = <<<EOD
<div class="plugin-gallery">
$item
</div>
EOD;
    return $gallery;
    
}
?>