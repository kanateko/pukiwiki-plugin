<?php
/**
 * Font Awesomeのアイコンを表示するプラグイン
 *
 * v6.7.2フリー版 https://fontawesome.com/search?ic=free
 *
 * @version 2.0.0
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * -- Updates --
 * 2025-06-30 v2.0.0 全面改修
 *                   FontAwesomeのバージョンを6.7.2にアップデート
 *                   Beat, Fade, Beat-Fade, Bounce, Flip, Shakeなどのアニメーションに対応
 *                   animation関連のカスタムプロパティに対応
 *                   filter, margin, padding, transformの指定を追加
 *                   任意のクラスの追加に対応
 * 2021-07-26 v1.4.0 リスト用のスクリプトを少し変更
 *            v1.3.0 リスト用オプションを追加
 *            v1.2.0 アイコン同士を重ねる機能を追加
 * 2021-07-11 v1.1.0 アニメーションやサイズ変更といったのFAのクラスを引数で追加する機能を追加
 *                   text-shadowとbackgroundオプションを追加
 * 2021-07-10 v1.0.0 初版作成
 */


// プラグイン側でheaderに読み込むCSSを挿入する
define('PLUGIN_FA_INSERT_CSS', true);
// 読み込むファイル/CDN
define('PLUGIN_FA_CSS', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css');
// デフォルトの表示スタイル
define('PLUGIN_FA_DEFAULT_STYLE', 'fa-solid');
// 固定で追加するクラス
// 例：'plugin-fa fa-fw'
define('PLUGIN_FA_CLASS_FIXED', 'plugin-fa');


function plugin_fa_init(): void
{
    global $head_tags;

    $msg['_fa_messages'] = [
        'err_invalid_arg' => '#fa: Invalid Argument. ($1)',
        'err_empty_icon' => '#fa: Need to Set Icon.'
    ];
    set_plugin_messages($msg);

    if (PLUGIN_FA_INSERT_CSS) $head_tags[] = '<link rel="stylesheet" href="' . PLUGIN_FA_CSS . '">';
}

class FaInline
{
    private array $class_list = [
        'fa' => [
            'xs', 'sm', 'lg', '2x', '3x', '4x', '5x', '6x', '7x', '8x', '9x', '10x',
            'spin', 'pulse', 'spin-pulse', 'spin-reverse', 'beat', 'fade', 'beat-fade', 'bounce', 'flip', 'shake',
            'border', 'stack', 'inverse', 'fw', 'li'
        ],
        'fa-spin' => [
            'pulse', 'reverse'
        ],
        'fa-flip' => [
            'horizontal', 'vertical', 'both'
        ],
        'fa-rotate' => [
            '90', '180', '270'
        ],
        'fa-pull' => [
            'left', 'right'
        ],
        'fa-stack' => [
            '1', '2'
        ]
    ];
    private array $icon_style_list = [
        'fa-solid' => [
            'fas', 's', 'solid'
        ],
        'fa-regular' => [
            'far', 'r', 'regular'
        ],
        'fa-brands' => [
            'fab', 'b', 'brands'
        ]
    ];
    private array $style_list = [
        'background',
        'color',
        'filter',
        'margin',
        'padding',
        'text-shadow',
        'transform'
    ];
    private array $variable_list = [
        'animation-delay',
        'animation-direction',
        'animation-duration',
        'animation-iteration-count',
        'animation-timing',
        'beat-scale',
        'fade-opacity',
        'beat-fade-opacity',
        'beat-fade-scale',
        'bounce-height',
        'bounce-jump-scale-x',
        'bounce-jump-scale-y',
        'bounce-land-scale-x',
        'bounce-land-scale-y',
        'bounce-start-scale-x',
        'bounce-start-scale-y',
        'bounce-rebound',
        'flip-angle',
        'flip-x',
        'flip-y',
        'flip-z'
    ];
    private array $classes = [];
    private array $styles = [];
    private array $err = [];
    private bool $is_stack = false;
    private bool $is_list = false;
    private string $icon;
    private string $icon_style;
    private static bool $jscode_added;

    /**
     * コンストラクタ
     *
     * @param array $args
     */
    public function __construct(array $args)
    {
        if (empty(end($args))) {
            $this->err = ['err_empty_icon'];
        } else {
            $icon = array_pop($args);
            $this->classes[] = PLUGIN_FA_CLASS_FIXED;
            $this->parse_options($args);
            if (! $this->is_stack && ! preg_match('/^fa[srb\-]/', $icon)) $icon = 'fa-' . $icon;
            $this->icon = $icon;
            $this->icon_style ??= PLUGIN_FA_DEFAULT_STYLE;
            self::$jscode_added ??= false;
        }
    }

    /**
     * HTMLへの変換
     *
     * @return string
     */
    public function convert(): string
    {
        if ($this->has_error()) return $this->msg(...$this->err);

        $html = '';
        $class = implode(' ', $this->classes);
        $style = implode('', $this->styles);
        $style = $style ? ' style="' . $style . '"' : '';
        $icon_style = $this->icon_style;
        $icon = $this->icon;

        if ($this->is_stack) {
            $html = $this->make_stack_style($class, $style, $icon);
        } else {
            $html = $this->make_default_style($class, $style, $icon_style, $icon);
        }

        return $html;
    }

    /**
     * 通常/リスト形式用HTML作成
     *
     * @param string $class
     * @param string $style
     * @param string $icon_style
     * @param string $icon
     * @return string
     */
    public function make_default_style(string $class, string $style, string $icon_style, string $icon): string
    {
        $html = "<i class=\"$class $icon_style $icon\"$style></i>";

        // リスト形式用スクリプト
        if ($this->is_list && ! self::$jscode_added) {
            self::$jscode_added = true;
            $html .= <<<EOD
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const targetLists = document.querySelectorAll("ul");

                    for (const list of targetLists) {
                        const hasFaList = !! list.querySelector(".fa-li");

                        if (hasFaList) {
                            list.classList.add("fa-ul");
                        }
                    }
                });
            </script>
            EOD;
        }

        return $html;
    }

    /**
     * スタック形式用HTML作成
     *
     * @param string $class
     * @param string $style
     * @param string $icon
     * @return string
     */
    public function make_stack_style(string $class, string $style, string $icon): string
    {
        $html =<<<EOD
        <span class="$class"$style>
        $icon
        </span>
        EOD;

        return $html;
    }

    /**
     * オプション判別
     *
     * @param array $args
     * @return void
     */
    public function parse_options(array $args): void
    {
        foreach ($args as $arg) {
            $arg = htmlsc($arg);
            [$key, $val] = array_map('trim', explode('=', $arg, 2));

            if ($val !== null) {
                if (in_array($key, $this->variable_list)) {
                    // css variable
                    $this->styles[] = '--fa-' . $key . ':' . $val . ';';
                } elseif (in_array($key, $this->style_list)) {
                    // style
                    $this->styles[] = $key . ':' . $val . ';';
                } elseif ($key === 'class') {
                    // class
                    $this->classes[] = $val;
                } else {
                    // unknown
                    $this->err = ['err_unknown', $arg];
                }
            } else {
                 if (in_array($key, $this->variable_list)) {
                    // css variable
                    $this->styles[] = '--fa-' . $key . ':1;';
                } else {
                    $matched = false;

                    // class
                    foreach ($this->class_list as $prefix => $list) {
                        if (in_array($key, $list)) {
                            if ($prefix === 'fa-stack') $key = $key . 'x';
                            $this->classes[] = $prefix . '-' . $key;
                            $matched = true;
                        }
                    }

                    // icon style class
                    if (! $matched) {
                        foreach ($this->icon_style_list as $style => $list) {
                            if (in_array($key, $list)) {
                                $this->icon_style = $style;
                                $matched = true;
                            }
                        }
                    }

                    // unknown
                    if (! $matched) {
                        $this->err = ['err_unknown', $arg];
                    }
                }
            }

            if ($key === 'stack') $this->is_stack = true;
            elseif ($key === 'li') $this->is_list = true;
        }
    }

    /**
     * エラーの有無を確認
     *
     * @return boolean
     */
    public function has_error(): bool
    {
        return $this->err !== [];
    }

    /**
     * メッセージの取得
     *
     * @param string $key
     * @param string ...$replaces
     * @return string
     */
    public function msg(string $key, string ...$replaces): string
    {
        global $_fa_messages;

        $msg = '';
        //if ($this->options['noerror'] === true) return $msg;

        $msg = $_fa_messages[$key] ?? '';
        $num_replaces = ! empty($replaces) ? count($replaces) : 0;

        // 必要なら置き換え
        for ($i = 0; $i < $num_replaces; $i++) {
            $replace = $replaces[$i];
            $msg = str_replace('$' . $i + 1, $replace, $msg);
        }

        return $msg;
    }
}

function plugin_fa_inline(string ...$args): string
{
    $fa = new FaInline($args);

    return $fa->convert();
}
