<?php
/**
 * 画像リサイズ・圧縮ライブラリ
 * 
 * PHP拡張モジュールのGDを使って画像の比率を保ったままリサイズする。
 * 対応している拡張子はjpg, png, gifの3つで、圧縮後は全てjpgに変換される。圧縮率は40に設定。
 * ソースコードは以下から拝借。
 * @link https://qiita.com/suin/items/b01eebc05209dba0eb3e
 * 
 * edited by kanateko for PukiWiki use.
 */

/**
 * 画像のサムネイルを保存する
 * @param string $srcPath
 * @param string $dstPath
 * @param int $maxWidth
 * @param int $maxHeight
 */
function make_thumbnail($srcPath, $dstPath, $maxWidth, $maxHeight)
{
    list($originalWidth, $originalHeight) = getimagesize($srcPath);
    list($canvasWidth, $canvasHeight) = get_contain_size($originalWidth, $originalHeight, $maxWidth, $maxHeight);
    transform_image_size($srcPath, $dstPath, $canvasWidth, $canvasHeight);
}

/**
 * 画像のサイズを変形して保存する
 * @param string $srcPath
 * @param string $dstPath
 * @param int $width
 * @param int $height
 */
function transform_image_size($srcPath, $dstPath, $width, $height)
{
    list($originalWidth, $originalHeight, $type) = getimagesize($srcPath);
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($srcPath);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($srcPath);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($srcPath);
            break;
        default:
            //throw new RuntimeException("サポートしていない画像形式です: $type");
    }

    $canvas = imagecreatetruecolor($width, $height);
    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $originalWidth, $originalHeight);
    imagejpeg($canvas, $dstPath, 40);
    imagedestroy($source);
    imagedestroy($canvas);
}

/**
 * 内接サイズを計算する
 * @param int $width
 * @param int $height
 * @param int $containerWidth
 * @param int $containerHeight
 * @return array
 */
function get_contain_size($width, $height, $containerWidth, $containerHeight)
{
    $ratio = $width / $height;
    $containerRatio = $containerWidth / $containerHeight;
    if ($ratio > $containerRatio) {
        return [$containerWidth, intval($containerWidth / $ratio)];
    } else {
        return [intval($containerHeight * $ratio), $containerHeight];
    }
}
?>