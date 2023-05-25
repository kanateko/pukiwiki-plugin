<?php
/**
* swiper.jsを利用したスライダー作成プラグイン
*
* @version 1.1.3
* @author kanateko
* @link https://jpngamerswiki.com/?f51cd63681
* @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
* @todo スライドの (半) 動的追加、エフェクト系の詳細設定
* -- Updates --
* 2023-05-26 v1.1.3 containerWidthが効かない問題を修正
* 2023-03-31 v1.1.2 幅や高さ指定の処理を変更
*                   縦スライドやグリッド表示の処理を改善
* 2023-03-30 v1.1.1 重複していたオプションを削除
*                   反映されていなかったオプションを修正
*            v1.1.0 cardプラグインとの連携機能を追加
*                   slidesPerGroup関連オプションを追加
*                   breakpoints関連オプションを追加
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
        'msg_usage'                => '#swiper([options]){{<br>slide 1<br>#-<br>slide2 ...<br>}}',
        'err_unknown'              => '#swiper Error: Unknown argument. ($1)',
        'err_plugin_not_exist'     => '#swiper Error: "$1" plugin does not exist.',
        'err_plugin_not_found'     => '#swiper Error: "$1" plugin is not found in the multiline section.',
        'err_plugin_not_supported' => '#swiper Error: "$1" plugin is not supported.',
        'label_empty'              => 'スライドがありません'
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
        // スライド
        if (preg_match("/\r|\n|\r\n/", end($args))) $this->contents = preg_replace("/\r|\r\n/", "\n", array_pop($args));
        else $this->contents = SwiperUtil::get_msg('label_empty', null, false);
        // オプション
        $this->cfg = new SwiperConfig($this->contents);
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
        $class = $this->options['class'] ? ' ' . $this->options['class'] : '';
        $plugin = $this->options['plugin'];

        // 個々のスライドを作成
        if ($plugin !== null) {
            // 別プラグインとの連携
            switch ($plugin) {
                case 'card':
                    if (! file_exists(PLUGIN_DIR . $plugin . '.inc.php')) return SwiperUtil::get_msg('err_plugin_not_exist', $plugin);
                    $slides = convert_html($this->contents);
                    break;
                default:
                    return SwiperUtil::get_msg('err_plugin_not_supported', $plugin);
            }
        } else {
            // 通常のスライド
            $slides = $this->get_slides($id);
        }
        // ナビゲーション関連
        $nav = $this->get_navs();
        // 初期化用スクリプト
        $script = $this->get_scripts($id);

        // 最終的なHTML
        $html = <<<EOD
        <div$rtl class="plugin-swiper swiper$class" id="swiper_$id">
            $slides
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

        // 入れ子を一時退避
        preg_match_all("/#[^\s]+?({{2,})\n/", $src, $m);
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
        // ラッパー
        $slides_merged = <<<EOD
        <div class="swiper-wrapper">
            $slides_merged
        </div>
        EOD;

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
        $plugin = $this->options['plugin'];
        $height = $this->options['height'];
        $width = $this->options['width'];
        $_height = $this->options['containerHeight'];
        $_width = $this->options['containerWidth'];
        $_size = ['width', 'height', 'containerHeight', 'containerWidth'];


        // プラグイン連携用
        $override = '';
        if ($plugin !== null) {
            switch ($plugin) {
                case 'card':
                    // cardプラグイン
                    $override = <<<EOD
                    const plugin$id = document.querySelector('#swiper_$id .plugin-$plugin');
                    const slides$id = plugin$id.querySelectorAll('.card-item');
                    plugin$id.classList.add('swiper-wrapper');
                    for (const item of slides$id) {
                        item.classList.add('swiper-slide');
                    }
                    EOD;
                    break;
                default:
                    $override = '';
            }
        }

        // スタイル関連
        $style = '';
        foreach ($_size as $size) {
            if ($this->options[$size] !== null) {
                switch ($size) {
                    case 'width':
                    case 'height':
                        $target = ' .swiper-slide';
                        break;
                    case 'containerWidth':
                    case 'containerHeight':
                        $target = '';
                        break;
                    default:
                        break 2;
                }
                switch ($size) {
                    case 'width':
                    case 'containerWidth':
                        $property = 'width';
                        break;
                    case 'height':
                    case 'containerHeight':
                        $property = 'height';
                        break;
                    default:
                        break 2;
                }
                $style .= <<<EOD
                const swiper_{$size}_{$id} = document.querySelectorAll('#swiper_$id$target');
                for (const t of swiper_{$size}_{$id}) {
                    t.style.$property = '{$this->options[$size]}';
                }
                EOD;
            }
        }

        // 最終的なスクリプト
        $script = <<<EOD
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                $override
                const swiper$id = new Swiper ('#swiper_$id', {$this->options['params']});
                $style
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
 * @var int BP_LARGER ブレイクポイント (大) (cardとの連携用)
 * @var int BP_SMALLER ブレイクポイント (小) (cardとの連携用)
 * @var string DIRECTION デフォルトのスライド方向 (horizontal, vertical)
 * @var string EFFECT デフォルトのスライドエフェクト (false, fade, cube, coverflow, flip, cards, creative)
 * @var string PAGINATION デフォルトの現在位置表示 (false, bullets, dynamic, progressbar, fraction)
 * @var string LOOP_PRIORITY ループ系オプションが競合している場合の優先度 (loop, rewind)
 * @property string $err エラーメッセージ
 * @property string $contents 引数のマルチライン部分
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
    const ENABLE_WHEEL = false;
    const BP_LARGER = 720;
    const BP_SMALLER = 460;
    const DIRECTION = 'horizontal';
    const EFFECT = 'false';
    const PAGINATION = 'bullets';
    const LOOP_PRIORITY = 'rewind';

    public $err;
    private $contents;
    private $availables;
    private $abbr;

    /**
     * コンストラクタ
     */
    public function __construct($contents)
    {
        $this->contents = $contents;
        // 1 = 簡略指定 (値不要) 可, 2 =不可
        $this->availables = [
            'autoplay'           => 1,
            'autoHeight'         => 1,
            'breakpoints'        => 2,
            'breakpointsBase'    => 2,
            'centeredSlides'     => 1,
            'class'              => 2,
            'containerHeight'    => 2,
            'containerWidth'     => 2,
            'direction'          => 2,
            'effect'             => 2,
            'freeMode'           => 1,
            'grabCursor'         => 1,
            'grid'               => 2,
            'height'             => 2,
            'initialSlide'       => 2,
            'loop'               => 1,
            'mousewheel'         => 1,
            'navigation'         => 1,
            'pagination'         => 2,
            'plugin'             => 2,
            'rewind'             => 1,
            'rtl'                => 1,
            'scrollbar'          => 1,
            'slidesPerGroup'     => 2,
            'slidesPerGroupAuto' => 1,
            'slidesPerGroupSkip' => 2,
            'slidesPerView'      => 2,
            'spaceBetween'       => 2,
            'speed'              => 2,
            'width'              => 2,
        ];
        // 略称との対応
        $this->abbr = [
            '_height' => 'containerHeight',
            '_width'  => 'containerwdith',
            'auto'    => 'autoplay',
            'base'    => 'breakpointsBase',
            'bp'      => 'breakpoints',
            'center'  => 'centeredSlides',
            'free'    => 'freeMode',
            'grab'    => 'grabCursor',
            'group'   => 'slidesPerGroup',
            'skip'    => 'slidesPerGroupSkip',
            'start'   => 'initialSlide',
            'nav'     => 'navigation',
            'wheel'   => 'mousewheel',
            'paging'  => 'pagination',
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
        // デフォルト設定の取得
        $options = $this->get_default_options();

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
        $options = $this->adjust_options($options);
        // オプションを整形する
        if ($this-> err === null) $options['params'] = $this->format_options($options);

        return $options;
    }

    /**
     * デフォルト設定の取得
     *
     * @return array $options デフォルト設定の配列
     */
    private function get_default_options(): array
    {
        $options = [];

        if (self::ENABLE_AUTO) $options['autoplay'] = true;
        if (self::ENABLE_GRAB) $options['grabCursor'] = true;
        if (self::ENABLE_LOOP) $options['loop'] = true;
        if (self::ENABLE_NAV) $options['navigation'] =true;
        if (self::ENABLE_REWIND) $options['rewind'] = true;
        if (self::ENABLE_WHEEL) $options['mousewheel'] = true;
        if (self::PAGINATION !== 'false') $options['pagination'] = self::PAGINATION;
        if (self::DIRECTION === 'vertical') $options['direction'] = self::DIRECTION;
        if (self::EFFECT !== 'false') $options['effect'] = self::EFFECT;

        return $options;
    }

    /**
     * 競合の解消や各オプションの調整
     *
     * @param array $options オプションの配列
     * @return array $options 調整後のオプションの配列
     */
    private function adjust_options(array $options): array
    {
        // プラグイン連携
        if ($options['plugin'] == 'card') {
            switch ($options['plugin']) {
                case 'card':
                    // カードプラグイン
                    $spv = $this->get_card_num();
                    if ($options['slidesPerGroup'] !== null) {
                        $spg = $options['slidesPerGroup'] === 'auto' ? $spv : $options['slidesPerGroup'];
                        $options['slidesPerGroup'] = null;
                    } else {
                        $spg = 1;
                    }
                    $bp_larger = self::BP_LARGER . '-' . $spv . '-' . $spg;
                    $bp_smaller = self::BP_SMALLER . '-' . ceil($spv / 2) . '-' . ceil($spg / 2);
                    $options['breakpoints'] = $bp_larger . '|' . $bp_smaller;
                    $options['breakpointsBase'] ??= 'container';
                    $options['spaceBetween'] ??= 8;
                    break;
                default:
                    break;
            }
        }
        // ループ系の競合解消
        if ($options['loop'] && $options['rewind']) {
            if (self::LOOP_PRIORITY === 'loop') $options['rewind'] = null;
            else $options['loop'] = null;
        }
        // 幅や高さの指定に単位を付与
        $_size = ['width', 'height', 'containerWidth', 'containerHeight'];
        foreach ($_size as $size) {
            if (is_numeric($options[$size])) $options[$size] .= 'px';
        }
        // スライドグループ関連
        if ($options['slidesPerGroup'] === 'auto') {
            $options['slidesPerGroup'] = null;
            $options['slidesPerGroupAuto'] = true;
        }
        if ($options['slidesPerGroup'] && is_numeric($options['slidesPerView'])) {
            if ($options['slidesPerGroup'] > $options['slidesPerView'])
                $options['slidesPerView'] = $options['slidesPerGroup'];
        }
        if ($options['slidesPerGroupAuto'] === true) {
            $options['slidesPerView'] = 'auto';
            $options['slidesPerGroup'] = null;
        }

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
                case 'slidesPerGroupAuto':
                    // 値不要系
                    if ($val === true) $params[$key] = true;
                    break;
                case 'breakpoints':
                    // ブレイクポイント
                    $params[$key] = $this->get_breakpoints($val);
                    break;
                case 'breakpointsBase':
                    // ブレイクポイントの基準
                    $params[$key] = $val;
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
                    if (preg_match('/(row|col)?(\d+)/', $val, $m)) {
                        if ($m[1] === 'row') {
                            $params[$key] = [
                                'fill' => 'row',
                                'rows' => $m[2]
                            ];
                        } else {
                            $params[$key] = [
                                'rows' => $m[2]
                            ];
                        }
                    }
                    break;
                case 'initialSlide':
                case 'slidesPerGroup':
                case 'slidesPerGroupSkip':
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

    /**
     * カードプラグインの列数設定を取得する
     *
     * @return int 列数の設定
     */
    private function get_card_num(): int
    {
        if (preg_match('/#card(\((\d)[,\)])?/', $this->contents, $m)) {
            $num = $m[1] === '' ? 1 : (int)$m[2];
            return $num;
        } else {
            $this->err = SwiperUtil::get_msg('err_plugin_not_found', 'card');
        }

        return 1;
    }

    /**
     * 引数からブレイクポイントを設定する
     *
     * @param string $val 引数
     * @return array $breakpoints ブレイクポイントの設定
     */
    private function get_breakpoints(string $val): array
    {
        $bps = explode('|', $val);
        $breakpoints = [];

        foreach ($bps as $bp) {
            [$width, $spv, $spg] = explode('-', $bp);
            $spv = $spv === 'auto' ? $spv : (int)$spv;
            if ($spg === 'auto') {
                $auto = 'Auto';
                $spg = true;
            } else {
                $auto = '';
                $spg = (int)$spg;
            }
            $breakpoints[$width] = [
                'slidesPerView' => $spv,
                'slidesPerGroup' . $auto => $spg,
            ];
        }

        return $breakpoints;
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