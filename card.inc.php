<?php
/**
 * ブログカード風にページを並べるプラグイン
 *
 * @version 0.3.0
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license http://www.gnu.org/licenses/gpl.ja.html GPL
 * -- Update --
 * 2021-03-31 列数指定機能追加
 * 2021-03-30 初版作成
 */

function plugin_card_convert()
{
    // メッセージ
    $msg = array (
        'usage'    =>    '#card([2-6]){{' . "\n" . 'links' . "\n" . '}}',
        'unknown'  =>    '#card Error: Unknown argument. -> ',
        'expired'  =>    '#card Error: The link is expired. -> ',
        'range'    =>    '#card Error: The number of columns must be set between 2 to 6'
    );

    // 列数関連
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
    $retval = '';
    

    // 列数設定
    if (!empty($args)) {
        $arg = htmlsc($args[0]);
        if(is_numeric($arg)) {
            if ($arg > 1 && $arg < 7) {
                $cols['width'] = (750 / $arg - (10 - ($arg - 1)));
                if ($arg > 2) {
                    $cols['class'] = $arg < 4 ? '-bigimg' : '-bigimg nodesc';
                }
            } else {
                return $msg['range'];
            }
        } else {
            return $msg['unknown'] . $arg;
        }
    }    

    // ページ名の取得
    preg_match_all('/<a.*?>(.+?)<\/a>/', $list, $matches);
    foreach($matches[1] as $pagename) {
        // ページ名からURL、デスクリプション、更新日を取得する
        // $url = $uri . '?' . urlencode($pagename);
        $url = $uri . get_short_url_from_pagename($pagename);
        $description = plugin_card_make_description($pagename);
        $date = date("Y-m-d", get_filetime($pagename));

        // サムネイルの取得 (ページでrefプラグインが使われている必要あり)
        if (!file_exists(get_filename($pagename))) return $msg['expired'] . $pagename;
        $source = get_source($pagename,true,true);
        preg_match('/ref\(([^,]+\.(?:jpg|png|gif))/', $source, $match_thumb);
        if (isset($match_thumb[1])) {
            if (strpos($match_thumb[1], '/') === false) {
                $thumb = $uri . '?plugin=ref&page=' . $pagename . '&src=' . $match_thumb[1];
            } else {
                list($refer, $src) = explode('/', $match_thumb[1]);
                $thumb = $uri . '?plugin=ref&page=' . $refer . '&src=' . $src;
            }
        } else {
            $thumb = $uri . IMAGE_DIR . 'eyecatch.jpg';
        }
        $card_body = <<<EOD
    <div class="plugin-card-box" style="width:{$cols['width']}px;">
        <div class="plugin-card-img">
            <img src="$thumb" alt="$pagename" >
        </div>
        <div class="plugin-card-title">$pagename</div>
        <p class="plugin-card-description">$description</p>
        <div class="plugin-card-date"><i class="fas fa-history"></i> $date</div>
        <a class ="plugin-card-link" href="$url"></a>
    </div>

EOD;
        $retval .= $card_body . "\n";
    }
    $retval = <<<EOD
<div class="plugin-card{$cols['class']}">
$retval
</div>
EOD;
    return $retval;
}

/**
 * ページ内容からプラグインやPukiWiki構文をある程度除いた200文字を抜き出す
 */
function plugin_card_make_description ($pagename) {
    $source = get_source($pagename);
    $source = preg_replace('/^\#(.*?)$/u', '', $source);
    $source = preg_replace('/^RIGHT:|LEFT:|CENTER:|SIZE\(.*?\):|COLOR\(.*?\):/u', '', $source);
    $source = preg_replace('/^\}(.*?)$/u', '', $source);
    $source = preg_replace('/&(.*?)\{(.*?)\};/u', '$2', $source);
    $source = preg_replace('/&(.*?);/u', '', $source);
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

?>