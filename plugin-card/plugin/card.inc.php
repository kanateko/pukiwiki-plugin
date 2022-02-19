<?php
/**
 * ブログカード風にページを並べるプラグイン
 *
 * @version 2.3
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * -- Updates --
 * 2021-10-04 v2.3 figプラグインに対応
 * 2021-08-23 v2.2 ページ名からファイル名への変換で使用する関数を変更 (strtoupper, bix2hex -> encode)
 *                 キャッシュを生成しない場合の階層化されたページにある画像の取得方法を修正
 *                 キャッシュを生成する場合、ページにinfoboxプラグインが使われていた場合の対応を強化
 *                 キャッシュ保存時に拡張子が正常に付与されない問題を修正
 * 2021-08-04 v2.1 infoboxプラグインとの兼ね合いで一部正規表現を変更・追加
 * 2021-07-28 v2.0 定数を整理、よりわかりやすい名前に変更
 *                 カード表示エリアの幅を固定化。幅はCSSで制御するように
 *                 上記に伴って幅の固定化をON/OFFするオプションを廃止
 *                 カードを縦長表示するカラム数の閾値の設定を追加
 *                 各カードの幅をCSSのgridで制御するように変更
 *                 指定した見出しをデスクリプション代わりに表示する機能を追加
 * 2021-07-26 v1.5 webpに対応
 *                 他のページの画像を呼び出している場合のサムネイル取得処理を修正
 * 2021-06-29 v1.4 プラグインの呼び出し毎に個別のIDを割り振る機能を追加
 * 2021-06-20 v1.3 画像が縦長の場合はサムネイルの切り抜きを画像の上端に合わせるよう変更
 *                 存在しないページが含まれている場合にエラーを出すかどうかを設定できるように変更
 *                 デスクリプション作成時の正規表現を修正
 * 2021-04-07 v1.2 ベースネーム表示機能を追加
 * 　　　　　　     上記に付随してカラム数を複数回指定した場合にエラーを返すよう変更
 *                 デスクリプション作成時の正規表現を修正
 *                 更新日アイコンの表示に関するバグを修正
 * 2021-04-04 v1.1 カラム数指定時に確実にintを取得できるよう修正
 *                 カードの高さをプラグイン側で調整するよう変更
 *                 カラム数2以下の場合はスマホでも横長のカードになるよう変更 (cssの変更のみ)
 * 2021-04-01 v1.0 短縮URLライブラリ未導入でも動くよう修正
 *                 エイリアスに対応
 *                 キャッシュ更新機能追加
 *                 未改造状態のPukiWiki向けにいくつかの設定を追加
 * 2021-03-31 v0.6 カラム数指定機能追加
 *                 サムネイルキャッシュ機能追加
 * 2021-03-30 v0.2 初版作成
 */

// カードを縦長表示にするカラム数
define('CARD_DISPLAY_VERTICAL_THRESHOLD', 3);
// デスクリプションを非表示にするカラム数
define('CARD_HIDE_DESCRIPTION_THRESHOLD', 4);
// サムネイル作成
define('CARD_ALLOW_CHACHE_THUMBNAILS', true);
define('CARD_THUMB_DIR', IMAGE_DIR . 'thumb/');
// 短縮URLの使用/未使用
define('CARD_USE_SHORT_URL', false);
// FontAwesomeの使用/未使用 (更新日のアイコン)
define('CARD_USE_FONTAWESOME_ICON', false);
// ベースネーム表示の強制
define('CARD_FORCE_BASENAME', false);
// 存在しないページが含まれている場合にエラーを出すかどうか
define('CARD_ERROR_ON_NO_EXISTS', false);
// 縦長画像の場合にサムネイルの切り抜きを上端に合わせるかどうか
define('CARD_IMAGE_BASELINE_TOP', true);

// 画像圧縮ライブラリの読込
require_once(PLUGIN_DIR . 'resize.php');

function plugin_card_convert()
{
    static $card_counts = 0;

    // メッセージ
    $msg = array (
        'usage'    =>    '#card([2-6]){{ internal links }}',
        'unknown'  =>    '#card Error: Unknown argument. -> ',
        'noexists' =>    '#card Error: There is no such page. -> ',
        'range'    =>    '#card Error: The number of columns must be set between 1 to 6',
        'doubled'  =>    '#card Error: The number of columns can be set only once.'
    );

    // カラム数関連
    $cols = array (
        'class'    =>    'card-cols-1',
        'isset'    =>    false
    );

    // 引数がなければ使い方表示
    if (func_num_args() == 0) return $msg['usage'];

    // 変数の初期設定
    $args = func_get_args();
    $list = convert_html(array_pop($args));
    $uri = get_base_uri();
    $card_list = '';
    $clock_icon = CARD_USE_FONTAWESOME_ICON ? '<i class="fas fa-history"></i>' : '<span>&#128339;</span>';
    $show_basename = CARD_FORCE_BASENAME;

    // オプション振り分け
    if (! empty($args)) {
        foreach ($args as $arg) {
            if ($arg == 'base') {
                // ベースネーム表示
                $show_basename = true;
            } else if (preg_match('/\*{1,3}=\d+/', $arg)) {
                // 見出しの抜き出し
                $headline = explode('=', $arg);
            } else if (is_numeric($arg)) {
                if ($cols['isset']) return $msg['doubled'];
                // 数字1の場合はカラム数設定
                $arg = intval($arg);
                if ($arg > 0 && $arg < 7) {
                    $cols['class'] = 'card-cols-' . $arg;
                    if ($arg >= CARD_DISPLAY_VERTICAL_THRESHOLD) $cols['class'] .= ' vertical';
                    if ($arg >= CARD_HIDE_DESCRIPTION_THRESHOLD) $cols['class'] .= ' minimal';
                } else {
                    // 1-6以外の数字場合はエラー
                    return $msg['range'];
                }
                $cols['isset'] = true;
            } else {
                // 不明な引数の場合はエラー
                return $msg['unknown'] . htmlsc($arg);
            }
        }
    }

    // ページ名の取得
    $pagenames = array();
    preg_match_all('/<a.*?href="\.\/\?([^\.]+?)".*?>(.+?)<\/a>/', $list, $matches);

    foreach ($matches[1] as $i => $href) {
        if (CARD_USE_SHORT_URL) {
            // URL短縮ライブラリを導入済みの場合
            $pagenames[$href] = get_pagename_from_short_url($href);
        } else {
            // URL短縮ライブラリを未導入の場合
            $pagenames[$href] = urldecode($href);
        }
        // 存在しないページが含まれている場合エラーを返す
        if (CARD_ERROR_ON_NO_EXISTS && ! file_exists(get_filename($pagenames[$href]))) {
            return $msg['noexists'] . $matches[2][$i];
        }
    }

    foreach($pagenames as $href => $pagename) {
        // 存在しないページをスキップ
        if (! file_exists(get_filename($pagename))) continue;
        // URLの作成
        $url = $uri . '?' . $href;
        //デスクリプションの取得
        if (isset($headline)) {
            // 指定した見出しを取得する
            $description = plugin_card_get_headline($headline[0], $headline[1], $pagename);
        } else {
            // 最初の200文字を抜き出す
            $description = plugin_card_make_description($pagename);
        }
        // 更新日の取得
        $filetime = get_filetime($pagename);
        $date = get_date('Y-m-d', $filetime);
        $date_long = format_date($filetime);

        // サムネイルの取得 (ページでrefプラグインが使われている必要あり)
        $eyecatch = IMAGE_DIR . 'eyecatch.jpg';
        $source = get_source($pagename,true,true);
        preg_match('/((?:ref|fig)\(|image=)([^,]+?(\.(?:jpg|png|gif|webp)))/', $source, $match_thumb);
        if (CARD_ALLOW_CHACHE_THUMBNAILS) {
            $thumb = plugin_card_make_thumbnail($pagename, $eyecatch, $match_thumb, $source);
        } else {
            $thumb = plugin_card_get_thumbnail($uri, $pagename, $eyecatch, $match_thumb);
        }

        // サムネイルが縦長か判定
        $tall_img = CARD_IMAGE_BASELINE_TOP ? plugin_card_get_image_size($thumb) : '';

        // ページタイトルのベースネーム表示化
        $card_title = $show_basename ? array_pop(explode('/', $pagename)) : $pagename;

        // カード作成
        $card = <<<EOD
        <a class ="card-link" title="$pagename" href="$url">
            <div class="card-box">
                <fig class="card-image$tall_img">
                    <img src="$thumb" alt="$pagename">
                </fig>
                <span class="card-title bold">$card_title</span>
                <span class="card-description">$description</span>
                <span class="card-date">$clock_icon $date</span>
                <span class="card-date long">$clock_icon $date_long</span>
            </div>
        </a>
        EOD;
        // カードをリストに追加
        $card_list .= $card . "\n";
    }

    $card_wrap = <<<EOD
<div class="plugin-card {$cols['class']}" id="cardContainer$card_counts">
$card_list
</div>
EOD;
    $card_counts++;
    return $card_wrap;
}

/**
 * ページ内容から指定番号の見出しを抜き出す
 * @param string $pagename エンコード前のページ名
 * @return string $headline 指定した見出しの文字列
 */
function plugin_card_get_headline($h_depth, $h_num, $pagename) {
    $source = get_source($pagename, true, true);
    $h_depth = '#[^\*]' . preg_quote($h_depth) . '([^\*]+?) \[#';
    preg_match_all($h_depth, $source, $headlines);
    $headline = plugin_card_get_raw_strings($headlines[1][$h_num - 1]);
    return $headline;
}

/**
 * ページ内容からプラグインやPukiWiki構文をある程度除いた200文字を抜き出す
 * @param string $pagename エンコード前のページ名
 * @return string $source ページから抜き出した200字
 */
function plugin_card_make_description($pagename) {
    $source = get_source($pagename);
    $source = plugin_card_get_raw_strings($source);
    $source = htmlsc(mb_substr(implode($source),0 ,200));
    if (empty(trim($source))) {
        $source = 'クリック or タップでこのページに移動します。';
    }
    return $source;
}

/**
 * @param string $str PukiWiki記法の混じった文字列
 * @return string $str PukiWiki記法を取り除いた文字列
 */
function plugin_card_get_raw_strings($str) {
    $str = preg_replace('/^RIGHT:|LEFT:|CENTER:|SIZE\(.*?\):|COLOR\(.*?\):/u', '', $str);
    $str = preg_replace('/^\#(.*?)$/u', '', $str);
    $str = preg_replace('/^\}(.*?)$/u', '', $str);
    $str = preg_replace('/\&null\{(.*?)\};/u', '', $str);
    $str = preg_replace('/\&([^;\{]*?)\{(.*?)\};/u', '$2', $str);
    $str = preg_replace('/\&([^;\(\{]*?)\((.*?)\);/u', '', $str);
    $str = preg_replace('/\&([a-zA-Z0-9]*?);/u', '', $str);
    $str = preg_replace('/\&([^;]*?);/u', '$1', $str);
    $str = preg_replace('/^\|(.*?)$/u', '', $str);
    $str = preg_replace('/^\*(.*?)$/u', '', $str);
    $str = preg_replace('/^[\-\+]{1,3}(.*?)$/u', '$1', $str);
    $str = preg_replace('/^>(.*?)$/u', '$1', $str);
    $str = preg_replace('/\[\[([^\]>]*?)>([^\]]*?)\]\]/u', '$1', $str);
    $str = preg_replace('/\[\[([^\]:]*?):([^\]]*?)\]\]/u', '$1', $str);
    $str = preg_replace('/\[\[([^\]]*?)\]\]/u', '$1', $str);
    $str = preg_replace('/\'\'\'(.*?)\'\'\'/u', '$1', $str);
    $str = preg_replace('/\'\'(.*?)\'\'/u', '$1', $str);
    $str = preg_replace('/%%%(.*?)%%%/u', '$1', $str);
    $str = preg_replace('/%%(.*?)%%/u', '$1', $str);
    $str = preg_replace('/\/\/(.*?)$/u', '', $str);
    $str = preg_replace('/^.+=.+$/', '', $str);
    return $str;
}

/**
 * 画像が縦長かどうかの判定
 * @param string $pagename エンコード前のページ名
 * @param array $match_thumb そのページで見つかった最初のrefプラグイン
 * @return string $tall_img サムネイル画像に付与する追加のクラス
 */
function plugin_card_get_image_size($thumb) {
    $tall_img = '';
    $info = getimagesize($thumb);
    $tall_img = $info[0] < $info[1] ? ' img-tall' : '';
    return $tall_img;
}

/**
 * サムネイルの取得 (infoboxプラグイン未対応)
 * @param string $uri get_base_uriで取得したuri
 * @param string $pagename エンコード前のページ名
 * @param string $eyecatch refプラグインが使われていないページ用のサムネイル画像
 * @param array $match_thumb そのページで見つかった最初のrefプラグイン
 * @return string $thumb_src 画像ファイルのパス (直リン)
 */
function plugin_card_get_thumbnail($uri, $pagename, $eyecatch, $match_thumb) {
    if (isset($match_thumb[2])) {
        // refプラグインが呼び出されている場合
        if (strpos($match_thumb[2], '/') === false) {
            // そのページに添付されている場合
            $attachfile = UPLOAD_DIR . encode($pagename) . '_' . encode($match_thumb[2]);
            $ref_url = $uri . '?plugin=ref&amp;page=' . urlencode($pagename) . '&amp;src=' . $match_thumb[2];
        } else {
            // 他のページに添付されている場合
            preg_match('/(.+)\/(.+)/', $match_thumb[2], $matches);
            $attachfile = UPLOAD_DIR . encode($matches[1]) . '_' . encode($matches[2]);
            $ref_url = $uri . '?plugin=ref&amp;page=' . urlencode($matches[1]) . '&amp;src=' . $matches[2];
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
 * @param string $pagename エンコード前のページ名
 * @param string $eyecatch refプラグインが使われていないページ用のサムネイル画像
 * @param array $match_thumb そのページで見つかった最初のrefプラグイン
 * @param string $source ページのソース
 * @return string $thumb_cache | $thumb_path . $match_thumb[2] サムネイルのパス (キャッシュ)
 */
function plugin_card_make_thumbnail($pagename, $eyecatch, $match_thumb, $source)
{
    global $vars;

    if (!file_exists(CARD_THUMB_DIR)) {
        // ディレクトリの確認と作成
        mkdir(CARD_THUMB_DIR, 0755);
        chmod(CARD_THUMB_DIR, 0755);
    }

    $thumb_path = CARD_THUMB_DIR . encode($pagename);
    $thumb_cache = $thumb_path . '.jpg';

    // 拡張子の違うキャッシュファイルを修正する
    if (file_exists($thumb_path . '.png')) {
        rename($thumb_path . '.png', $thumb_cache);
    } elseif (file_exists($thumb_path . '.gif')) {
        rename($thumb_path . '.gif', $thumb_cache);
    } elseif (file_exists($thumb_path . '.webp')) {
        rename($thumb_path . '.webp', $thumb_cache);
    }

    //キャッシュがある場合、ページとキャッシュの更新日を確認する
    if (file_exists($thumb_cache) && plugin_card_check_updates($pagename, $thumb_cache)) {
        // キャッシュのほうが新しければキャッシュを返す
        return $thumb_cache;
    } else {
        // キャッシュがないか古い場合は新たに取得する
        if (isset($match_thumb[2])) {
            switch ($match_thumb[1]) {
                case 'fig(':
                case 'ref(':
                    // refプラグインが呼び出されている場合
                    if (strpos($match_thumb[2], '/') === false) {
                        // そのページに添付されている場合
                        $thumb_src = UPLOAD_DIR . encode($pagename) . '_' . encode($match_thumb[2]);
                    } else {
                        // 他のページに添付されている場合
                        if (strpos($match_thumb[2], './') !== false) {
                            // 相対パスを絶対パスに変換
                            $match_thumb[2] = get_fullname($match_thumb[2], $vars['page']);
                        }
                        preg_match('/(.+)\/(.+)/', $match_thumb[2], $matches);
                        $thumb_src = UPLOAD_DIR . encode($matches[1]) . '_' . encode($matches[2]);
                    }
                    break;
                case 'image=':
                    // infoboxプラグインが呼び出されている場合
                    if (strpos($match_thumb[2], '/') === false) {
                        $info_image = UPLOAD_DIR . encode($pagename) . '_' . encode($match_thumb[2]);
                    } else {
                        preg_match('/(.+)\/(.+)/', $match_thumb[2], $matches);
                        $info_image = UPLOAD_DIR . encode($matches[1]) . '_' . encode($matches[2]);
                    }

                    if (file_exists(($info_image))) {
                        $thumb_src = $info_image;
                    } else {
                        // ページの記述だけではファイル名にならない場合、テンプレートを読み込みに行く
                        preg_match('/#infobox\(([^,\)]+)/', $source, $template);
                        $s_info = get_source(':config/plugin/infobox/' . $template[1], true, true);
                        preg_match('/\&ref\(([^,\)]+)/', $s_info, $ref);
                        $ref[1] = preg_replace('/{{{.+?}}}/', $match_thumb[2], $ref[1]);
                        if (strpos($ref[1], '/') === false) {
                            $thumb_src = UPLOAD_DIR . encode($pagename) . '_' . encode($ref[1]);
                        } else {
                            preg_match('/(.+)\/(.+)/', $ref[1], $matches);
                            $thumb_src = UPLOAD_DIR . encode($matches[1]) . '_' . encode($matches[2]);
                        }
                    }
            }
        } else {
            // refプラグインが呼び出されてない場合
            $thumb_src = $eyecatch;
            $match_thumb[3] = '.jpg';
        }

        if (! file_exists($thumb_src)) {
            // refプラグインは呼び出されているがファイルはない場合
            $thumb_src = $eyecatch;
            $match_thumb[3] = '.jpg';
        }

        // サムネイルフォルダにコピーを作成
        $thumb_data = file_get_contents($thumb_src);
        $thumb_src = $thumb_path . $match_thumb[3];
        file_put_contents($thumb_src, $thumb_data);

        // コピーをリサイズして保存
        make_thumbnail($thumb_src, $thumb_src, 320, 180);
        return $thumb_path . $match_thumb[3];
    }
}

/**
 * サムネイルキャッシュの作成日とリンク先ページの更新日を比較する
 * @param string $page エンコード前のページ名
 * @param string $cache キャッシュされたサムネイル画像のパス
 * @return bool  $is_new キャッシュの作成日がページの更新日よりも新しいかどうか
 */
function plugin_card_check_updates($page, $cache) {
    $time_cache = filemtime($cache);
    $time_page = filemtime(get_filename($page));
    $is_new = ($time_cache > $time_page);
    return $is_new;
}

