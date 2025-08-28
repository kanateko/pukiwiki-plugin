<?php
/**
 * 画像リサイズ・圧縮ライブラリ
 *
 * 画像を指定したサイズに縮小、トリミングして保存する。
 * 参考：https://qiita.com/suin/items/b01eebc05209dba0eb3e
 *
 * @version 1.2
 * @author kanateko
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * -- Updates --
 * 2025-08-28 v1.2 AVIF画像に対応
 * 2021-07-28 v1.1 WEBP画像に対応
 */

 class ImageResizer
 {
    /**
     * 画像のサムネイルを保存する
     * @param string $srcPath 元画像のパス
     * @param string $dstPath サムネイルの保存先
     * @param int $canvasWidth 作成するサムネイルの幅
     * @param int $canvasHeight 作成するサムネイルの高さ
     * @param int $comp 圧縮率
     * @param bool $fitTop 上端に合わせて切り抜く
     * @param bool $contain 元の比率を保持する
     */
    public static function make_thumbnail($srcPath, $dstPath, $canvasWidth, $canvasHeight, $comp, $fitTop = true, $contain = false)
    {
        list($originalWidth, $originalHeight, $type) = getimagesize($srcPath);
        if (is_null($originalWidth) || is_null($originalHeight)) return false;

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
            case IMAGETYPE_WEBP:
                $source = imagecreatefromwebp($srcPath);
                break;
            case IMAGETYPE_AVIF:
                $source = imagecreatefromavif($srcPath);
                break;
            default:
                return false;
                //throw new RuntimeException("サポートしていない画像形式です: $type");
        }

        // 比率を保持するか
        if ($contain) {
            list($canvasWidth, $canvasHeight) = self::get_contain_size($canvasWidth, $canvasHeight, $originalWidth, $originalHeight);
            $x = $y = 0;
            $calWidth = $originalWidth;
            $calHeight = $originalHeight;
        } else {
            list($calWidth, $calHeight, $x, $y) = self::get_cover_size($canvasWidth, $canvasHeight, $originalWidth, $originalHeight);
            if ($fitTop) $y = 0;
        }

        $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);
        imagecopyresampled($canvas, $source, 0, 0, $x, $y, $canvasWidth, $canvasHeight, $calWidth, $calHeight);
        imagejpeg($canvas, $dstPath, $comp);
        imagedestroy($source);
        imagedestroy($canvas);
    }

    /**
     * トリミングに必要な値を計算する
     *
     * @param  int $canvasWidth 作成するサムネイルの幅
     * @param  int $canvasHeight 作成するサムネイルの高さ
     * @param  int $originalWidth 元画像の幅
     * @param  int $originalHeight 元画像の高さ
     * @return array 計算後の高さと幅、基準点
     */
    private static function get_cover_size($canvasWidth, $canvasHeight, $originalWidth, $originalHeight)
    {
        $w = $originalHeight * ($canvasWidth / $canvasHeight);
        $h = $originalWidth * ($canvasHeight / $canvasWidth);
        if ($w > $originalWidth) {
            $x = 0;
            $calWidth = $originalWidth;
        } else {
            $x = floor(($originalWidth - $w) / 2);
            $calWidth = $w;
        }
        if ($h > $originalHeight) {
            $y = 0;
            $calHeight = $originalHeight;
        } else {
            $y = floor(($originalHeight - $h) / 2);
            $calHeight = $h;
        }

        return [$calWidth, $calHeight, $x, $y];
    }

    /**
     * 内接サイズを計算する
     *
     * @param  int $canvasWidth 作成するサムネイルの幅
     * @param  int $canvasHeight 作成するサムネイルの高さ
     * @param  int $originalWidth 元画像の幅
     * @param  int $originalHeight 元画像の高さ
     * @return array 計算後の高さと幅
     */
    private static function get_contain_size($canvasWidth, $canvasHeight, $originalWidth, $originalHeight)
    {
        $ratio = $originalWidth / $originalHeight;
        $canvasRatio = $canvasWidth / $canvasHeight;
        if ($ratio > $canvasRatio) {
            return [$canvasWidth, intval($canvasWidth / $ratio)];
        } else {
            return [intval($canvasHeight * $ratio), $canvasHeight];
        }
    }
}
