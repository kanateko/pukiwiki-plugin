<?php
/**
 * 折りたたみ可能な見出しを作成するプラグイン
 *
 * @version 1.5
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license http://www.gnu.org/licenses/gpl.ja.html GPL
 * -- Updates --
 * 2021-07-26 プラグインの呼び出し毎に挿入されていたスクリプトを大幅に削減
 * 2021-07-07 全開閉ボタンにも状態に合わせてクラスを切り替える機能を追加
 *            全開閉ボタンが連打できたバグを修正
 *            全開閉ボタンの制御範囲の終了位置を作成する機能を追加
 * 2021-07-06 全開閉ボタン作成機能を追加。設置位置以降の同階層にある全てのアコーディオンが対象となる
 * 2021-07-05 ヘッダーに見出しを指定する場合はオプションで明示するように変更
 * 2021-07-04 インライン型を追加
 * 2021-04-02 初版作成
 */

// 折りたたみ時に変わりに表示するメッセージ
define('PLUGIN_AC_ALT_MESSAGE', '&#9652; クリック or タップで詳細を表示');
// オプションリスト
define('PLUGIN_AC_OPTION_LIST', '/^(end|all|h|open|alt)$/');

/**
 * ブロック型
 */
function plugin_ac_convert()
{
    $ac = new PluginAc;

    if (func_num_args() == 0) {
        return $ac->msg['usage'];
    }

    $args = func_get_args();

    // ヘッダーとコンテンツ
    if (func_num_args() > 1 && ! preg_match(PLUGIN_AC_OPTION_LIST, $args[0])) {
        $header = convert_html(array_shift($args));
        $ac->set_header($header);
    }
    if(!preg_match('/^(end|all)$/', $args[0])) {
        $contents = preg_replace("/\r|\r\n/", "\n", array_pop($args));
        $contents = convert_html($contents);
    }

    // オプション判別
    if ($args) {
        foreach ($args as $arg) {
            $arg = htmlsc($arg);
            switch ($arg) {
                case 'end':
                    return $ac->build_control_end();
                case 'all':
                    return $ac->build_control_button();
                    break;
                case 'h':
                    // no break
                case 'open':
                    // no break
                case 'alt':
                    $ac->set_options($arg);
                    break;
                default:
                    return $ac->msg['unknown'] . $arg;
            }
        }
    }

    // 本文とスクリプトを作成
    return $ac->build_accordion($contents);
}

/**
 * インライン型
 */
function plugin_ac_inline()
{
    $ac = new PluginAc;

    if (func_num_args() == 0) {
        return $ac->msg['usage'];
    }

    $args = func_get_args();

    // ヘッダーとコンテンツ
    if (func_num_args() > 1 && ! preg_match(PLUGIN_AC_OPTION_LIST, $args[0])) {
        $header = convert_html(array_shift($args));
        $ac->set_header($header);
    }
    $contents = array_pop($args);

    if (empty($contents)) {
        // コンテンツが空の場合はエラー
        return $ac->msg['empty'];
    }

    // オプション判別
    if ($args) {
        foreach ($args as $arg) {
            $arg = htmlsc($arg);
            switch ($arg) {
                case 'end':
                    // no break
                case 'all':
                    // no break
                case 'h':
                    return $ac->msg['incorrect'] . $arg;
                    break;
                case 'open':
                    // no break
                case 'alt':
                    $ac->set_options($arg);
                    break;
                default:
                    return $ac->msg['unknown'] . $arg;
            }
        }
    }

    // 本文とスクリプトを作成
    return $ac->build_accordion($contents);
}

/**
 * アコーディオン作成用クラス
 */
Class PluginAc
{
    private static $ac_counts = 0;
    private static $ac_ctrl_counts = 0;

    // メッセージ
    public $msg = array(
        'usage'     => '#ac Usage:<br>
                        &lt;title or header&gt;<br>
                        #ac([end,all,h,open,alt]){{<br>
                        &lt;ontents&gt;<br>
                        }}<br>
                        or<br>
                        &ac(&lt;title&gt;[,open,alt]){&lt;contents&gt;};',
        'unknown'   => '#ac Error: Unknown argument. -> ',
        'empty'     => '&ac; Error: The text area is empty.',
        'incorrect' => '$ac; Error: This option is not available for inline-type plugin. -> ',
    );

    // オプション
    private $options = array (
        'class'   => array(
            'header'   =>    'plugin-ac-header',
            'contents' =>    'plugin-ac',
            'alt'      =>    'plugin-ac-altmsg',
            'ctrl'     =>    'plugin-ac-ctrl',
        ),
        'display' => 'none',
        'alt'     => '',
        'header'  => '...',
        'open'    => false,
    );

    /**
     * ヘッダーの指定
     */
    public function set_header($header)
    {
        $header = preg_replace('/<\/?p>/', '', $header);
        $this->options['header'] = $header;
    }

    /**
     * オプションの判別
     */
    public function set_options($arg)
    {
        $op =& $this->options;
        switch ($arg) {
            case 'h':
                $this->set_header('');
                break;
            case 'open':
                // 初期状態を開いた状態にする
                $op['open'] .= true;
                $op['display'] = 'block';
                break;
            case 'alt':
                // 代わりの文章を表示する
                $op['alt'] = '<div class="' . $op['class']['alt'] . '" style="display:none">' . PLUGIN_AC_ALT_MESSAGE . '</div>';
        }
    }

    /**
     * アコーディオンを作成する
     */
    public function build_accordion($contents)
    {
        $header = $this->options['header'];
        $class = $this->options['class'];
        if (! empty($header)) {
            $header = '<div>' . $header . '</div>';
        }
        $id = 'ac-' . self::$ac_counts++;

        // 1回のみ挿入するスクリプト
        $script_once = '';
        $script_min = $this->build_minified_script(false);
        if (self::$ac_counts === 1) {
            $script_once = <<<EOD
            <script>
                $(function(){ $script_min });
            </script>
            EOD;
        }

        // オプションの有無によってによって挿入するスクリプト
        if ($this->options['open']) {
            $script_open = <<<EOD
            <script>
                $(function(){ $("#$id").prev().addClass("open"); });
            </script>
            EOD;
        }

        $html = <<<EOD
        $header
        <div class="{$class['contents']}" id="$id" style="display:{$this->options['display']}">
            $contents
        </div>
        {$this->options['alt']}
        $script_open
        $script_once
        EOD;

        return $html;
    }

    /**
     * 全開閉用ボタンの作成
     */
    public function build_control_button()
    {
        $id = 'ac-c-' . self::$ac_ctrl_counts++;
        $class_ctrl = $this->options['class']['ctrl'];

        // 1回のみ挿入するスクリプト
        $script_once = '';
        $script_min = $this->build_minified_script(true);
        if (self::$ac_ctrl_counts === 1) {
            $script_once = <<<EOD
            <script>
                $(function(){ $script_min });
            </script>
            EOD;
        }

        $html = <<<EOD
        <div class="$class_ctrl" id="$id"><span>全て開く</span></div>
        $script_once
        EOD;

        return $html;
    }

    /**
     * 全開閉ボタンの制御範囲の終了位置を作成
     */
    public function build_control_end()
    {
        $html = '<div class="' . $this->options['class']['ctrl'] . ' ctrl-end" style="display:none"></div>';
        return $html;
    }

    /**
     * 一度だけ挿入するスクリプトを作成
     * (元々jQueryのhtml関数を使っていて、そのためにスクリプトを1行にまとめる必要があった)
     */
    private function build_minified_script($is_ctrl)
    {
        // 各クラス名を変数に格納
        foreach ($this->options['class'] as $key => $val) {
            ${"class_" . $key} = $val;
        }

        if (! $is_ctrl) {
            // 通常の折りたたみ開閉用スクリプト
            $base_script = <<<EOD
            $('.$class_contents').prev().addClass('$class_header');
            $('.$class_contents').next('.$class_alt').show();
            $('body').on('click', '.$class_header', function() {
                $(this).toggleClass('open');
                $(this).next().stop().slideToggle(500);
            });
            EOD;
        } else {
            // 複数開閉用のスクリプト
            $base_script = <<<EOD
            $('.$class_ctrl').on('click', function() {
                var btnText = $('span', this);
                var allH = $(this).nextUntil('.$class_ctrl', '.$class_header');
                var allC = $(this).nextUntil('.$class_ctrl', '.$class_contents');
                $(this).toggleClass('open');
                if (btnText.text() === '全て開く') {
                    $(allH).not('.open').toggleClass('open');
                    $(allC).stop().slideDown(500);
                    $(btnText).text('全て閉じる');
                } else {
                    $(allH).filter('.open').toggleClass('open');
                    $(allC).stop().slideUp(500);
                    $(btnText).text('全て開く');
                }
            });
            EOD;
        }
        // スクリプトを一行にまとめる
        $minified_script = preg_replace('/\r{\n|\r\n/', '', $base_script);
        $minified_script = preg_replace('/\s+/', ' ', $minified_script);

        return $minified_script;
    }
}

?>
