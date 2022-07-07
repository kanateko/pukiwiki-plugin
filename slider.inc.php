<?php
/**
* slickを利用したスライダー作成プラグイン
*
* @version 1.2.2
* @author kanateko
* @link https://jpngamerswiki.com/?f51cd63681
* @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
* -- Updates --
* 2022-07-07 v1.2.2 galleryプラグインのバージョンアップに対応
* 2021-07-28 v1.2.1 cardプラグインのバージョンアップに対応
* 2021-06-29 v1.2.0 card, galleryプラグインとの連携機能を追加
*            v1.1.0 クラス追加機能を実装
* 2021-06-28 v1.0.0 初版作成
*/

// スライドアイテム分割用タグ
define('PLUGIN_SLIDER_SPLIT_TAG', '#-');
// json_encode用オプション
define('PLUGIN_SLIDER_JSON_ENCODE_OPTIONS', JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
// レスポンシブ設定のブレークポイント (px)
define('PLUGIN_SLIDER_BREAKPOINT', 768);

function plugin_slider_init()
{
    global $head_tags;
    //jsとcssを読み込む
    $head_tags[] = '<script type="text/javascript" src="' . SKIN_DIR . 'slick/slick.min.js"></script>';
    $head_tags[] = '<link rel="stylesheet" type="text/css" href="' . SKIN_DIR . 'slick/slick.css" />';
    $head_tags[] = '<link rel="stylesheet" type="text/css" href="' . SKIN_DIR . 'slick/slick-theme.css" />';
}

function plugin_slider_convert()
{
    static $slider_counts = 0;

    // メッセージ
    $_msg = array(
        'usage'   => '#slider([options]){{<br />
                        slide 1<br />' . PLUGIN_SLIDER_SPLIT_TAG . '<br />
                        slide 2<br />' . PLUGIN_SLIDER_SPLIT_TAG . '<br />
                        ...<br />}}<br />',
        'unknown' => '#slider Error: Unknown argument. -> ',
        'type'    => '#slider Error: The type of value is incorrect. -> ',
        'missing' => '#slider Error: Following plugin is required to enable some options. -> '
    );

    // slick用オプション
    $list_slick_options = array(
        'adaptiveHeight'  => false,
        'arrows'          => true,
        'autoplay'        => false,
        'autoplaySpeed'   => 3000,
        'centerMode'      => false,
        'centerPadding'   => '50px',
        'cssEase'         => 'ease',
        'dots'            => true,
        'infinite'        => true,
        'lazyLoad'        => 'progressive',
        'pauseOnFocus'    => true,
        'pauseOnHover'    => true,
        'slidesToShow'    => 1,
        'slidesToScroll'  => 1,
        'speed'           => 500,
        'variableWidth'   => false,
        'vertical'        => false,
        'verticalSwiping' => false,
        'waitForAnimate'  => true,
        'responsive'      => [array(
            'breakpoint'    => PLUGIN_SLIDER_BREAKPOINT,
            'settings'      => array(
                'arrows'         => false,
                'centerMode'     => true,
                'centerPadding'  => '20px',
                'slidesToShow'   => 1,
                'slidesToScroll' => 1
            )
        )]
    );

    // 連携プラグイン
    $p_link = array (
        'card'    => false,
        'gallery' => false
    );

    if (func_num_args() == 0) return $_msg['usage'];
    $args = func_get_args();
    $s_items = array_pop($args);
    $items = explode(PLUGIN_SLIDER_SPLIT_TAG, $s_items);
    $slider_no = 'slider-' . $slider_counts;
    $slider_target = $slider_no;
    $slick_init = '';
    $add_class = '';

    // オプションの判別と調整
    if ($args > 0) {
        foreach ($args as $arg) {
            $arg = htmlsc($arg);
            if (strpos($arg, '=') !== false) {
                list($key, $val) = explode('=', $arg);
                if (array_key_exists($key, $list_slick_options)) {
                    // 型の判別
                    switch ($key) {
                        case 'adaptiveHeight':
                        case 'arrows':
                        case 'autoplay':
                        case 'centerMode':
                        case 'dots':
                        case 'infinite':
                        case 'pauseOnFocus':
                        case 'pauseOnHover':
                        case 'variableWidth':
                        case 'vertical':
                        case 'verticalSwiping':
                        case 'waitForAnimate':
                            // boolean
                            if ($val === 'true' || $val === 'false') $val = ($val === 'true');
                            else return $_msg['type'] . $arg;
                            break;
                        case 'autoplaySpeed':
                        case 'slidesToShow':
                        case 'slidesToScroll':
                        case 'speed':
                            // integer
                            if (is_numeric($val)) $val = (int)$val;
                            else return $_msg['type'] . $arg;
                        default:
                            // string
                            break;
                    }
                    $list_slick_options[$key] = $val;
                } else {
                    switch ($key) {
                        case 'class':
                            $add_class = ' ' . $val;
                            break;
                        default:
                            return $_msg['unknown'] . $arg;
                            break;
                    }
                }
            } else {
                $plugin = $arg . '.inc.php';
                switch ($arg) {
                    case 'card':
                    case 'gallery':
                        if (file_exists(PLUGIN_DIR . $plugin)) $p_link[$arg] = true;
                        else return $_msg['missing'] . $plugin;
                        break;
                    default:
                        return $_msg['unknown'] . $arg;
                        break;
                }
            }
            if ($list_slick_options['slidesToScroll'] > $list_slick_options['slidesToShow']) {
                // 表示するスライド数よりもスクロール数が大きく設定されている場合
                $list_slick_options['slidesToShow'] = $list_slick_options['slidesToScroll'];
            }
        }
    }

    // スライダーの中身を作成
    $slider_items = '';
    foreach ($items as $item) {
        $item = str_replace("\r", "\n", str_replace("\r\n", "\n", $item));
        $item = convert_html($item);
        if ($list_slick_options['lazyLoad'] == 'ondemand' || $list_slick_options['lazyLoad'] == 'progressive') {
            // 画像の遅延読み込みが有効な場合は属性を置き換える
            $item = preg_replace('/(<img.*?)src="/', '$1data-lazy="', $item);
        }
        if ($p_link['card'] || $p_link['gallery']) {
            // 他のプラグインと連携している場合はターゲットを書き換える
            preg_match('/id="((cardContainer|gallery-?)\d+)/', $item, $match);
            $slider_target = $match[1];
            // オプションを書き換える
            $list_slick_options['lazyLoad'] = 'progressive';
            $list_slick_options['variableWidth'] = true;
            // 読み込み完了時にスライダーを表示させる用
            $slick_init = <<<EOD
            $('#$slider_target').on('init', function(event, slick) {
                $('#$slider_no').addClass("slick-initialized");
            });
            EOD;
        }
        $slider_items .= <<<EOD
        <div class="slider-items">
        $item
        </div>
        EOD;
    }

    // slickのオプション用配列をJSON形式で整形
    $slick_options = json_encode($list_slick_options, PLUGIN_SLIDER_JSON_ENCODE_OPTIONS);

    // スライダー全体の作成
    $slider_body = <<<EOD
<div class="slider-container$add_class" id="$slider_no">
$slider_items
</div>
<script>
$(function(){
    $slick_init
    $('#$slider_target').slick($slick_options);
});
</script>
EOD;

    $slider_counts++;
    return $slider_body;
}

