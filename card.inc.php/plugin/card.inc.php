<?php
/**
 * ブログカード風にページを並べるプラグイン
 *
 * @version 0.7.0
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license http://www.gnu.org/licenses/gpl.ja.html GPL
 * -- Update --
 * 2021-04-01 短縮URLライブラリ未導入でも動くよう修正
 *            エイリアスに対応
 * 2021-03-31 カラム数指定機能追加
 *            サムネイルキャッシュ機能追加
 * 2021-03-30 初版作成
 */

// デスクリプションを非表示にするカラム数
define('NO_DESC_NUM', 4);
// 固定レイアウト化
define('FIX_WIDTH', FALSE);
define('WRAPPER_WIDTH', '770px');
// サムネイル作成
define('ALLOW_MAKE_THUMBNAILS', TRUE);
define('THUMB_DIR', IMAGE_DIR . 'thumb/');
// 短縮URLの使用/未使用
define('USE_SHORT_URL', TRUE);

// 画像圧縮ライブラリの読込
require_once(PLUGIN_DIR . 'resize.php');

function plugin_card_convert()
{
    // メッセージ
    $msg = array (
        'usage'    =>    '#card([2-6]){{ internal links }}',
        'unknown'  =>    '#card Error: Unknown argument. -> ',
        'noexists' =>    '#card Error: There is no such page. -> ',
        'range'    =>    '#card Error: The number of columns must be set between 1 to 6'
    );

    // カラム数関連
    $cols = array (
        'num'      =>    1,
        'width'    =>    600,
        'class'    =>    ''
    );

    // 引数がなければ使い方表示
    if (func_num_args() == 0) return $msg['usage'];

    // 変数
    $args = func_get_args();
    $list = convert_html(array_pop($args));
    $uri = get_base_uri();
    $card_list = '';

    // 幅固定
    $fix_width = FIX_WIDTH ? ' style="width:' . WRAPPER_WIDTH . '"' : '';    

    // カラム数設定
    if (!empty($args)) {
        $arg = htmlsc($args[0]);
        if(is_numeric($arg)) {
            if ($arg == 1) {
                unset($arg);
            } else if ($arg > 1 && $arg < 7) {
                // ページ幅770pxでだいたいいい感じに収まるよう調整
                $cols['width'] = (750 / $arg - (10 - ($arg - 1)));
                if ($arg > 2) {
                    // 3列以上の場合はサムネイルを最大化 & デスクリプション非表示判定
                    $cols['class'] = $arg < NO_DESC_NUM ? '-bigimg' : '-bigimg nodesc';
                }
            } else {
                return $msg['range'];
            }
        } else {
            return $msg['unknown'] . $arg;
        }
    }    
    // ページ名の取得
    $pagenames = array();
    preg_match_all('/<a.*?href="\.\/\?([^\.]+?)".*?>(.+?)<\/a>/', $list, $matches);
    
    foreach ($matches[1] as $href) {
        if (USE_SHORT_URL) {
            // URL短縮ライブラリを導入済みの場合
            $pagenames[$href] = get_pagename_from_short_url($href);
        } else {
            // URL短縮ライブラリを未導入の場合
            $pagenames[$href] = urldecode($href);
        }
        if (!file_exists(get_filename($pagenames[$href]))) return $msg['noexists'] . $pagenames[$href];
    }

    foreach($pagenames as $href => $pagename) {
        // URLの作成
        $url = $uri . '?' . $href;
        //デスクリプションの取得
        $description = plugin_card_make_description($pagename);
        // 更新日の取得
        $filetime = get_filetime($pagename);
        $date = get_date('Y-m-d', $filetime);
        $date_long = format_date($filetime);

        // サムネイルの取得 (ページでrefプラグインが使われている必要あり)
        $eyecatch = IMAGE_DIR . 'eyecatch.jpg';
        $source = get_source($pagename,true,true);
        preg_match('/ref\(([^,]+(\.jpg|\.png|\.gif))/', $source, $match_thumb);
        if (ALLOW_MAKE_THUMBNAILS) {
            $thumb = plugin_card_make_thumbnail($pagename, $eyecatch, $match_thumb);
        } else {
            $thumb = plugin_card_get_thumbnail($uri, $pagename, $eyecatch, $match_thumb);
        }
        $card = <<<EOD
    <div class="plugin-card-box" style="width:{$cols['width']}px;">
        <div class="plugin-card-img">
            <img src="$thumb" alt="$pagename" >
        </div>
        <div class="plugin-card-title">$pagename</div>
        <p class="plugin-card-description">$description</p>
        <div class="plugin-card-date"><i class="fas fa-history"></i> $date</div>
        <div class="plugin-card-date long"><i class="fas fa-history"></i> $date_long</div>
        <a class ="plugin-card-link" href="$url"></a>
    </div>

EOD;
        $card_list .= $card . "\n";
    }

    $card_wrap = <<<EOD
<div class="plugin-card{$cols['class']}"$fix_width>
$card_list
</div>
EOD;
    return $card_wrap;
}

/**
 * ページ内容からプラグインやPukiWiki構文をある程度除いた200文字を抜き出す
 * @param string $pagename
 */
function plugin_card_make_description ($pagename) {
    $source = get_source($pagename);
    $source = preg_replace('/^\#(.*?)$/u', '', $source);
    $source = preg_replace('/^RIGHT:|LEFT:|CENTER:|SIZE\(.*?\):|COLOR\(.*?\):/u', '', $source);
    $source = preg_replace('/^\}(.*?)$/u', '', $source);
    $source = preg_replace('/\&null\{(.*?)\};/u', '', $source);
    $source = preg_replace('/\&(.*?)\{(.*?)\};/u', '$2', $source);
    $source = preg_replace('/\&(.*?)\((.*?)\);/u', '', $source);
    $source = preg_replace('/\&([a-zA-Z0-9]*?);/u', '', $source);
    $source = preg_replace('/\&(.*?);/u', '$1', $source);
    $source = preg_replace('/^\|(.*?)$/u', '', $source);
    $source = preg_replace('/^\*(.*?)$/u', '', $source);
    $source = preg_replace('/^[\-\+]{1,3}(.*?)$/u', '$1', $source);
    $source = preg_replace('/\[\[(.*?)>(.*?)\]\]/u', '$1', $source);
    $source = preg_replace('/\[\[(.*?)\]\]/u', '$1', $source);
    $source = preg_replace('/\'\'\'(.*?)\'\'\'/u', '$1', $source);
    $source = preg_replace('/\'\'(.*?)\'\'/u', '$1', $source);
    $source = preg_replace('/%%%(.*?)%%%/u', '$1', $source);
    $source = preg_replace('/%%(.*?)%%/u', '$1', $source);
    $source = preg_replace('/\/\/(.*?)/u', '', $source);
    $source = htmlsc(mb_substr(implode($source),0 ,200));
    if (empty(trim($source))) {
        $source = 'クリック or タップでこのページに移動します。';
    }
    return $source;
}

/**
 * サムネイルの取得
 * @param string $uri
 * @param string $pagename
 * @param string $eyecatch
 * @param array $match_thumb
 */
function plugin_card_get_thumbnail ($uri, $pagename, $eyecatch, $match_thumb) {
    if (isset($match_thumb[1])) {
        // refプラグインが呼び出されている場合
        if (strpos($match_thumb[1], '/') === false) {
            // そのページに添付されている場合
            $attachfile = UPLOAD_DIR . strtoupper(bin2hex($pagename)) . '_' . strtoupper(bin2hex($match_thumb[1]));
            $ref_url = $uri . '?plugin=ref&page=' . urlencode($pagename) . '&src=' . $match_thumb[1];
        } else {
            // 他のページに添付されている場合
            list($refer, $src) = explode('/', $match_thumb[1]);
            $attachfile = UPLOAD_DIR . strtoupper(bin2hex($refer)) . '_' . strtoupper(bin2hex($src));
            $ref_url = $uri . '?plugin=ref&page=' . urlencode($refer) . '&src=' . $src;
        }
        $thumb_src = file_exists($attachfile) ? $ref_url : $eyecatch;
    } else {
        // refプラグインが呼び出されてない場合
        $thumb_src = $eyecatch;
    }

    return $thumb_src;
}

/**
 * サムネイルの取得とキャッシュの生成
 * @param string $pagename
 * @param string $eyecatch
 * @param array $match_thumb
 */
function plugin_card_make_thumbnail($pagename, $eyecatch, $match_thumb)
{
    if (!file_exists(THUMB_DIR)) {
        // ディレクトリの確認と作成
        mkdir(THUMB_DIR, 0755);
        chmod(THUMB_DIR, 0755);
    }
    
    $thumb_path = THUMB_DIR . strtoupper(bin2hex($pagename));
    $thumb_cache = $thumb_path . '.jpg';

    // 拡張子の違うキャッシュファイルを修正する
    if (file_exists($thumb_path . '.png')) {
        rename($thumb_path . '.png', $thumb_cache);
    } elseif (file_exists($thumb_path . '.gif')) {
        rename($thumb_path . '.gif', $thumb_cache);
    }

    //キャッシュがある場合、ページとキャッシュの更新日を確認する
    if (file_exists($thumb_cache) && plugin_card_check_updates($pagename, $thumb_cache)) {
        // キャッシュのほうが新しければキャッシュを返す
        return $thumb_cache;
    } else {
        // キャッシュがないか古い場合は新たに取得する
        if (isset($match_thumb[1])) {
            // refプラグインが呼び出されている場合
            if (strpos($match_thumb[1], '/') === false) {
                // そのページに添付されている場合
                $thumb_src = UPLOAD_DIR . strtoupper(bin2hex($pagename)) . '_' . strtoupper(bin2hex($match_thumb[1]));
            } else {
                // 他のページに添付されている場合
                list($refer, $src) = explode('/', $match_thumb[1]);
                $thumb_src = UPLOAD_DIR . strtoupper(bin2hex($refer)) . '_' . strtoupper(bin2hex($src));
            }
        } else {
            // refプラグインが呼び出されてない場合
            $thumb_src = $eyecatch;
            $match_thumb[2] = '.jpg';
        }
        if (filesize($thumb_src) == 0) {
            // refプラグインは呼び出されているがファイルはない場合
            $thumb_src = $eyecatch;
            $match_thumb[2] = '.jpg';
        }

        // サムネイルフォルダにコピーを作成
        $thumb_data = file_get_contents($thumb_src);
        $thumb_src = $thumb_path . $match_thumb[2];
        file_put_contents($thumb_src, $thumb_data);

        // コピーをリサイズして保存
        make_thumbnail($thumb_src, $thumb_src, 320, 180);
        return $thumb_path . $match_thumb[2];
    }
}

/**
 * サムネイルキャッシュの作成日とリンク先ページの更新日を比較する
 * @param string $page
 * @param string $cache
 */
function plugin_card_check_updates($page, $cache) {
    $time_cache = filemtime($cache);
    $time_page = filemtime(get_filename($page));
    $is_new = ($time_cache > $time_page);
    return $is_new;
}

?>