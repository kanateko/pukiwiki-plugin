<?php
/**
* swiper.jsを利用したスライダー作成プラグイン
*
* @version 1.0.0
* @author kanateko
* @link https://jpngamerswiki.com/?f51cd63681
* @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
* @todo 他プラグインとの連携、スライドの (半) 動的追加
* -- Updates --
* 2023-03-23 v1.0.0 初版作成
*/

// 必要ファイル
define('PLUGIN_SWIPER_JS', 'https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js');
define('PLUGIN_SWIPER_CSS', 'https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css');
define('PLUGIN_SWIPER_WIKI_CSS', SKIN_DIR . 'css/swiper.css');
// スライド分割用のタグ
define('PLUGIN_SWIPER_SPLIT_TAG', '#-');
// デフォルト設定の変更は SwiperConfig クラスを参照

/**
 * 初期化
 *
 * @return void
 */
function plugin_swiper_init(): void
{
    global $head_tags;

    $msg['_swiper_messages'] = [
        'msg_usage'   => '#swiper([options]){{<br>slide 1<br>#-slide2 ...<br>}}',
        'err_unknown' => '#swiper Error: Unknown argument. ($1)',
        'label_empty' => 'スライドがありません'
    ];
    set_plugin_messages($msg);

    $head_tags[] = '<link rel="stylesheet" href="' . PLUGIN_SWIPER_CSS . '"/>';
    $head_tags[] = '<link rel="stylesheet" href="' . PLUGIN_SWIPER_WIKI_CSS . '?t=' . filemtime(PLUGIN_SWIPER_WIKI_CSS) . '"/>';
    $head_tags[] = '<script src="' . PLUGIN_SWIPER_JS . '"></script>';
}

/**
 * ブロック型
 *
 * @param string[] $args プラグインの引数
 * @return string $html HTML変換済みのコンテンツ
 */
function plugin_swiper_convert(string ...$args): string
{
    if (func_num_args() === 0) return SwiperUtil::get_msg('msg_usage');

    $swiper = new SwiperMain($args);
    if ($swiper->err !== null) return $swiper->err;
    $html = $swiper->convert();

    return $html;
}

/**
 * メイン処理
 *
 * @var string SPLIT_TAG 要素を分割するためのタグ
 * @property string $err エラーメッセージ
 * @property string $contents スライダーのコンテンツ部分
 * @property array $options オプション
 * @property object $cfg 設定関連
 * @property int $id 識別用のID
 */
Class SwiperMain
{
    public $err;
    private $contents;
    private $options;
    private $cfg;
    private static $id = 0;

    /**
     * コンストラクタ
     *
     * @param array $args プラグインの引数
     */
    public function __construct(array $args)
    {
        $this->cfg = new SwiperConfig;

        // スライド
        if (preg_match("/\r|\n|\r\n/", end($args))) $this->contents = preg_replace("/\r|\r\n/", "\n", array_pop($args));
        else $this->contents = SwiperUtil::get_msg('label_empty', null, false);
        // オプション
        $this->options = $this->cfg->parse_options($args);
        // オプション処理時のエラー
        if ($this->cfg->err) $this->err = $this->cfg->err;
    }

    /**
     * スライダーを作成する
     *
     * @return string $html HTML変換後のスライダー
     */
    public function convert(): string
    {
        $id = self::$id++;
        $rtl = $this->options['rtl'] ? ' dir="rtl"' : '';
        $height = $this->options['containerHeight'] ? ' style="height:' . $this->options['containerHeight'] . ';"' : '';
        $class = $this->options['class'] ? ' ' . $this->options['class'] : '';

        // 個々のスライドを作成
        $slides = $this->get_slides($id);
        // ナビゲーション関連
        $nav = $this->get_navs();
        // 初期化用スクリプト
        $script = $this->get_scripts($id);

        // 最終的なHTML
        $html = <<<EOD
        <div$rtl class="plugin-swiper swiper$class" id="swiper_$id"$height>
            <div class="swiper-wrapper">
                $slides
            </div>
            $nav
        </div>
        $script
        EOD;

        return $html;
    }

    /**
     * 個々のスライドをHTMLに変換して結合する
     *
     * @param int $id スライダーの識別用ID
     * @return string $slides_merged 全スライドのHTML
     */
    private function get_slides(int $id): string
    {
        $src = $this->contents;
        $evac = [];
        preg_match_all("/#[^\s]+?({{2,})\n/", $src, $m);

        // 入れ子を一時退避
        if (! empty($m)) {
            foreach ($m[1] as $i => $start) {
                $end = str_replace('{', '}', $start);
                $pattern = "/" . preg_quote($m[0][$i]) . "[\s\S]+?\n" . $end . "/";
                preg_match($pattern, $src, $nested);
                $evac[$i] = $nested[0];
                $src = str_replace($nested[0], '%swp' . $i . '%', $src);
            }
        }
        // 分割後に入れ子を戻す
        $slides = explode(PLUGIN_SWIPER_SPLIT_TAG . "\n", $src);
        if (! empty($evac)) {
            foreach ($slides as $i => $slide) {
                if (preg_match('/%swp(\d+)%/', $slide, $m)) {
                    $slides[$i] = str_replace($m[0], $evac[$m[1]], $slide);
                }
            }
        }
        // 変換後のスライドを一つにまとめる
        $slides_merged = '';
        foreach ($slides as $i => $slide) {
            $slide = convert_html($slide);
            $slides_merged .= <<<EOD
            <div class="swiper-slide" id="swiper_{$id}_$i">
                $slide
            </div>
            EOD;
        }

        return $slides_merged;
    }

    /**
     * ナビゲーション用のUIの作成
     *
     * @return string $nav ナビゲーション類のHMTL
     */
    private function get_navs(): string
    {
        $nav = '';

        // 前/次ボタン
        if ($this->options['navigation']) {
            $nav .= <<<EOD
            <div class="swiper-button-prev"></div>
            <div class="swiper-button-next"></div>
            EOD;
        }
        // 現在位置/総枚数表示 (ページネーション)
        if ($this->options['pagination'] && $this->options['pagination'] !== 'false') {
            $nav .= <<<EOD
            <div class="swiper-pagination"></div>
            EOD;
        }
        // スクロールバー
        if ($this->options['scrollbar'] && $this->options['scrollbar'] !== 'false') {
            $nav .= <<<EOD
            <div class="swiper-scrollbar"></div>
            EOD;
        }

        return $nav;
    }

    /**
     * 初期化用スクリプトの作成
     *
     * @param int $id スライダーの識別用ID
     * @return string $script 初期化用スクリプト
     */
    private function get_scripts(int $id): string
    {
        $script = '';

        $script = <<<EOD
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                var swiper$id = new Swiper ('#swiper_$id', {$this->options['params']});
            });
        </script>
        EOD;

        return $script;
    }
}

/**
 * デフォルト設定・オプション関連
 *
 * @var bool ENABLE_AUTO デフォルトで自動再生を有効にする
 * @var bool ENABLE_GRAB デフォルトでつかむ見た目のカーソルを有効にする
 * @var bool ENABLE_LOOP デフォルトでループを有効にする (rewindと排他)
 * @var bool ENABLE_NAV デフォルトで前/次の矢印を有効にする
 * @var bool ENABLE_REWIND デフォルトで巻き戻りを有効にする (loopと排他)
 * @var bool ENABLE_WHEEL デフォルトでマウスホイール操作を有効にする
 * @var string DEFAULT_HEIGHT 縦スライド時のデフォルトの高さ
 * @var string DIRECTION デフォルトのスライド方向 (horizontal, vertical)
 * @var string EFFECT デフォルトのスライドエフェクト (false, fade, cube, coverflow, flip, cards, creative)
 * @var string PAGINATION デフォルトの現在位置表示 (false, bullets, dynamic, progressbar, fraction)
 * @var string LOOP_PRIORITY ループ系オプションが競合している場合の優先度 (loop, rewind)
 * @property string $err エラーメッセージ
 * @property array $availables 利用可能なオプション
 * @property array $abbr オプションの略称系と正式名の対応
 */
class SwiperConfig
{
    const ENABLE_AUTO = false;
    const ENABLE_GRAB = true;
    const ENABLE_LOOP = false;
    const ENABLE_NAV = false;
    const ENABLE_REWIND = false;
    const ENABLE_WHEEL = true;
    const DEFAULT_HEIGHT = '500px';
    const DIRECTION = 'horizontal';
    const EFFECT = 'false';
    const PAGINATION = 'bullets';
    const LOOP_PRIORITY = 'rewind';

    public $err;
    private $availables;
    private $abbr;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        // 1 = 簡略指定 (値不要) 可, 2 =不可
        $this->availables = [
            'autoplay'        => 1,
            'autoHeight'      => 1,
            'centeredSlides'  => 1,
            'class'           => 2,
            'containerHeight' => 2,
            'direction'       => 2,
            'effect'          => 2,
            'freeMode'        => 1,
            'grabCursor'      => 1,
            'grid'            => 2,
            'height'          => 2,
            'initialSlide'    => 2,
            'start'           => 2,
            'loop'            => 1,
            'mousewheel'      => 1,
            'navigation'      => 1,
            'pagination'      => 2,
            'rewind'          => 1,
            'rtl'             => 1,
            'scrollbar'       => 1,
            'slidesPerView'   => 2,
            'spaceBetween'    => 2,
            'speed'           => 2,
            'width'           => 2,
        ];
        // 略称との対応
        $this->abbr = [
            'center'  => 'centeredSlides',
            '_height' => 'containerHeight',
            'free'    => 'freeMode',
            'grab'    => 'grabCursor',
            'start'   => 'initialSlide',
            'nav'     => 'navigation',
            'wheel'   => 'mousewheel',
            'page'    => 'pagination',
            'num'     => 'slidesPerView',
            'gap'     => 'spaceBetween',
        ];
    }

    /**
     * 引数からオプションの指定を得る
     *
     * @param array $args プラグインの引数
     * @return array $options オプションの配列
     */
    public function parse_options(array $args): array
    {
        // デフォルト設定
        if (self::ENABLE_AUTO) $options['autoplay'] = true;
        if (self::ENABLE_GRAB) $options['grabCursor'] = true;
        if (self::ENABLE_LOOP) $options['loop'] = true;
        if (self::ENABLE_NAV) $options['navigation'] =true;
        if (self::ENABLE_REWIND) $options['rewind'] = true;
        if (self::ENABLE_WHEEL) $options['mousewheel'] = true;
        if (self::PAGINATION !== 'false') $options['pagination'] = self::PAGINATION;
        if (self::DIRECTION === 'vertical') $options['direction'] = self::DIRECTION;
        if (self::EFFECT !== 'false') $options['effect'] = self::EFFECT;

        foreach ($args as $arg) {
            $arg = htmlsc($arg);
            [$key, $val] = explode('=', $arg);
            // オプションの略称を元に戻す
            $key = $this->abbr[$key] ?? $key;
            if ($val === null && $this->availables[$key] === 1) {
                // 簡略指定可能なオプション
                $options[$key] = true;
            } else {
                // 値の入力が必要なオプション
                if ($this->availables[$key]) {
                    if ($val === 'true') $val = true;
                    elseif ($val === 'false') $val = false;
                    $options[$key] = $val;
                } else {
                    // 不明なオプション
                    $this->err = SwiperUtil::get_msg('err_unknown', $arg);
                    break;
                }
            }
        }
        // オプションの調整等
        if ($options['loop'] && $options['rewind']) {
            // ループ系の競合解消
            if (self::LOOP_PRIORITY === 'loop') $options['rewind'] = null;
            else $options['loop'] = null;
        }
        if ($options['direction'] === 'vertical') {
            // 縦スライド時の初期設定
            $options['navigation'] = null;
            $options['containerHeight'] ??= self::DEFAULT_HEIGHT;
        }
        if (is_numeric($options['containerHeight'])) {
            // コンテナの高さに単位を付与
            $options['containerHeight'] .= 'px';
        }
        // オプションを整形する
        if ($this-> err === null) $options['params'] = $this->format_options($options);

        return $options;
    }

    /**
     * オプションを整形する
     *
     * @param array $options オプションの配列
     * @return string $params 整形済みのオプション
     */
    private function format_options(array $options): string
    {
        $params = [];
        foreach ($options as $key => $val) {
            switch ($key) {
                case 'autoplay':
                    // 自動再生
                    if (is_numeric($val)) {
                        $params['autoplay'] = [
                            'delay' => (int)$val
                        ];
                    } elseif ($val === true) {
                        $params['autoplay'] = true;
                    }
                    break;
                case 'autoHeight':
                case 'centeredSlides':
                case 'freeMode':
                case 'grabCursor':
                case 'loop':
                case 'mousewheel':
                case 'rewind':
                    // 値不要系
                    if ($val === true) $params[$key] = true;
                    break;
                case 'direction':
                    // スライド方向
                    if ($val === 'vertical') $params[$key] = $val;
                    break;
                case 'effect':
                    // エフェクト
                    if (preg_match('/fade|cube|flip|coverflow|cards|creative/', $val)) {
                        $params[$key] = $val;
                        if ($val !== 'creative') {
                            $params[$val . 'Effect'] = [
                                'slideShadows' => false
                            ];
                        }
                    }
                    break;
                case 'grid':
                    // グリッド
                    if (preg_match('/(row|col)(\d+)/', $val, $m)) {
                        if ($m[1] === 'col') {
                            $params[$key] = [
                                'rows' => $m[2]
                            ];
                        } else {
                            $params[$key] = [
                                'fill' => 'row',
                                'rows' => $m[2]
                            ];
                        }
                    }
                    break;
                case 'height':
                case 'width':
                case 'initialSlide':
                case 'spaceBetween':
                case 'speed':
                    // 数値入力系
                    if (is_numeric($val)) $params[$key] = (int)$val;
                    break;
                case 'navigation':
                    // 前/次ボタン
                    if ($val === true) {
                        $params[$key] = [
                            'nextEl' => '.swiper-button-next',
                            'prevEl' => '.swiper-button-prev',
                        ];
                    }
                    break;
                case 'pagination':
                    // ページネーション
                    if ($val === 'bullets') {
                        $params[$key] = [
                            'el'        => '.swiper-pagination',
                            'type'      => $val,
                            'clickable' => true,
                        ];
                    } elseif ($val === 'dynamic') {
                        $params[$key] = [
                            'el'             => '.swiper-pagination',
                            'type'           => 'bullets',
                            'clickable'      => true,
                            'dynamicBullets' => true,
                        ];
                    } elseif ($val === 'progressbar' || $val === 'fraction') {
                        $params[$key] = [
                            'el'   => '.swiper-pagination',
                            'type' => $val
                        ];
                    }
                    break;
                case 'scrollbar':
                    // スクロールバー
                    if ($val === true) {
                        $params[$key] = [
                            'el'        => '.swiper-scrollbar',
                            'draggable' => true,
                            'hide'      => true,
                        ];
                    }
                case 'slidesPerView':
                    // 一度に表示するスライド枚数
                    if (is_numeric($val)) $params[$key] = (int)$val;
                    elseif ($val === 'auto') $params[$key] = $val;
                    break;
                default:
                    continue;
            }
        }
        $params = json_encode($params,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        // die_message(print_r($params, true));

        return $params;
    }
}

/**
 * 汎用
 */
class SwiperUtil
{
    /**
     * メッセージの取得
     *
     * @param string $key メッセージの種類
     * @param string $replace 置換用の文字列
     * @param boolean $is_block pタグで囲むかどうか
     * @return string $msg 表示するメッセージ
     */
    public static function get_msg(string $key, string $replace = null, bool $is_block = true): string
    {
        global $_swiper_messages;

        if ($replace !== null) {
            $msg = str_replace('$1', $replace, $_swiper_messages[$key]);
        } else {
            $msg = $_swiper_messages[$key];
        }
        if ($is_block === true) $msg = '<p>' . $msg . '</p>';

        return $msg;
    }
}