<?php
/**
 * 画像のギャラリー表示プラグイン
 * 
 * @version 0.1
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license http://www.gnu.org/licenses/gpl.ja.html GPL
 * -- Updates --
 * 2021-06-16 初版作成 (ver 0.1)
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

    $args = func_get_args();
    $images = array_pop($args);
    $images = str_replace("\r", "\n", str_replace("\r\n", "\n", $images));
    preg_match_all('/.+\.(?:jpe?g|png|gif)>?.*?\n/i', $images, $matches);

    $item = '';
    $url = get_base_uri() . '?plugin=ref&amp;page=' . $vars['page'];

    foreach ($matches[0] as $image) {
        if (strpos($image, '>') !== false) {
            list($image, $cap) = explode('>', $image);
            $item .= '<figure class="gallery-item"><a href="' . $url  . '&src=' . $image .'"
             class="gallery-thumb" data-lightbox="gallery-' . $gallery_counts . '"
              data-title="' . $cap . '"><img class="gallery-source" src="' . $url  . '&src=' . $image .'"></a>
              <figcaption class="gallery-caption">' . $cap . '</figcaption></figure>' . "\n";
        } else {
            $item .= '<figure class="gallery-item"><a href="' . $url  . '&src=' . $image .'"
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