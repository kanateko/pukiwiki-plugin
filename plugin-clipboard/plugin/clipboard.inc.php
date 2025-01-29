<?php
/**
* 指定したテキストをワンクリックでコピーさせるプラグイン
*
* @version 1.0.1
* @author kanateko
* @link https://jpngamerswiki.com/?f51cd63681
* @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
* -- Updates --
* 2-25-01-29 1.0.1 ソースコピー時に不要な改行が入らないよう修正
* 2025-01-22 1.0.0 初版作成
*/

// コピー時にメッセージを表示する
define('PLUGIN_CLIPBOARD_SHOW_MSG', true);
// CSS
define('PLUGIN_CLIPBOARD_CSS', SKIN_DIR . 'css/clipboard.min.css');
// JS
define('PLUGIN_CLIPBOARD_JS', SKIN_DIR . 'js/clipboard.min.js');


/**
 * 初期化
 *
 * @return void
 */
function plugin_clipboard_init(): void
{
    global $head_tags;

    $msg['_clipboard_messages'] = [
        'label_copied'    => 'コピーしました',
        'label_copy_text' => 'コピーする',
        'label_copy_src'  => 'ソースをコピーする',
        'err_arg_invalid' => '#clipboard: Invalid Argument. ($0)'
    ];
    set_plugin_messages($msg);

    $head_tags[] = '<link rel="stylesheet" href="' . PLUGIN_CLIPBOARD_CSS . '?t=' . filemtime(PLUGIN_CLIPBOARD_CSS) . '">';
}

/**
 * インライン型
 *
 * @param string ...$args
 * @return string
 */
function plugin_clipboard_inline(string ...$args): string
{
    $clipboard = new PluginClipboardInline($args);
    return $clipboard->convert();
}

/**
 * ブロック型
 *
 * @param string ...$args
 * @return string
 */
function plugin_clipboard_convert(string ...$args): string
{
    $clipboard = new PluginClipboardBlock($args);
    return $clipboard->convert();
}

/**
 * メイン処理
 */
class PluginClipboard
{
    protected string $content;
    protected array $options;
    protected array $err;
    protected static int $id;
    protected static bool $is_first_call;

    /**
     * コンストラクタ
     *
     * @param array $args
     */
    public function __construct(array $args)
    {
        self::$id ??= 0;
        self::$is_first_call ??= true;
        $this->err ??= [];
        $this->content = str_replace(["\r", "\r\n"], "\n", array_pop($args));
        $this->options = $this->parse_options($args);
    }

    /**
     * HTMLへの変換
     *
     * @return string
     */
    public function convert(): string
    {
        $html = '';
        if ($this->has_error()) return $this->get_msg(...$this->err);

        $content = $this->get_content();
        $html = $this->get_html($content);

        if (self::$is_first_call) {
            self::$is_first_call = false;
            $html = $this->add_script($html);
            $html = $this->add_svg($html);
        }

        return $html;
    }

    /**
     * アイコンの取得
     *
     * @return string
     */
    public function get_icon(): string
    {
        return '<svg height="18px" viewBox="0 -960 960 960" width="18px" fill="#e8eaed" class="clipboard-icon"><use xlink:href="#copy"></use></svg>';
    }

    /**
     * 変換前のコンテンツの取得
     *
     * @param string $tag
     * @return string
     */
    public function get_source_text(string $tag): string
    {
        $source = htmlsc($this->content);

        return "<$tag class=\"clipboard-source\">$source</$tag>";
    }

    /**
     * コピー用スクリプトの追加
     *
     * @param string $html
     * @return string
     */
    public function add_script(string $html): string
    {
        $src = './' . PLUGIN_CLIPBOARD_JS . '?t=' . filemtime(PLUGIN_CLIPBOARD_JS);
        $html = <<<EOD
        $html
        <script defer type="module">
            import { pluginClipboard } from '$src';
            pluginClipboard();
        </script>
        EOD;

        return $html;
    }

    /**
     * アイコン用svgの設定
     *
     * @param string $html
     * @return string
     */
    public function add_svg(string $html): string
    {
        $html = <<<EOD
        <svg display="none" xmlns="http://www.w3.org/2000/svg">
            <symbol>
                <path id="copy" d="M360-240q-33 0-56.5-23.5T280-320v-480q0-33 23.5-56.5T360-880h360q33 0 56.5 23.5T800-800v480q0 33-23.5 56.5T720-240H360Zm0-80h360v-480H360v480ZM200-80q-33 0-56.5-23.5T120-160v-560h80v560h440v80H200Zm160-240v-480 480Z"/>
            </symbol>
        </svg>
        $html
        EOD;

        return $html;
    }

    /**
     * オプションの取得
     *
     * @param array $args
     * @return array
     */
    public function parse_options(array $args): array
    {
        $options = [];

        foreach ($args as $arg) {
            [$key, $val] = array_map('trim', explode('=', $arg, 2));

            if ($key === 'source') {
                // ソースをコピー
                $options[$key] = true;
            } elseif ($key === 'class') {
                // クラス追加
                $options[$key] = ' ' . htmlsc($val);
            } else {
                // 不明なオプション
                $this->err = ['err_arg_invalid', $arg];
            }
        }

        return $options;
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
    public function get_msg(string $key, string ...$replaces): string
    {
        global $_clipboard_messages;

        $msg = '';
        if ($this->options['noerror'] === true) return $msg;

        $msg = $_clipboard_messages[$key] ?? '';
        $num_replaces = count($replaces);

        if ($replaces !== null) {
            for ($i = 0; $i < $num_replaces; $i++) {
                $replace = $replaces[$i];
                $msg = str_replace('$' . $i, $replace, $msg);
            }
        }

        return $msg;
    }
}

/**
 * インライン型
 */
class PluginClipboardInline extends PluginClipboard
{
    protected static int $id = 0;

    /**
     * コピー内容の取得
     *
     * @return string
     */
    public function get_content(): string
    {
        return $this->content;
    }

    /**
     * HTMLの作成
     *
     * @param string $content
     * @return string
     */
    public function get_html(string $content): string
    {
        $icon = $this->get_icon('clipboard-button');
        $id = self::$id++;
        $msg = PLUGIN_CLIPBOARD_SHOW_MSG ? ' data-msg="' . $this->get_msg('label_copied') . '"' : '';
        $class = $this->options['class'] ?? '';
        $html = <<<EOD
        <span id="clipboardInline$id" class="plugin-clipboard clipboard-inline$class">
            <span class="clipboard-display">$content</span>
            <span class="clipboard-button"$msg>$icon</span>
        </span>
        EOD;

        return $html;
    }

}

/**
 * ブロック型
 */
class PluginClipboardBlock extends PluginClipboard
{
    protected static int $id = 0;

    /**
     * コピー内容の取得
     *
     * @return string
     */
    public function get_content(): string
    {
        return convert_html($this->content);
    }

    /**
     * HTMLの作成
     *
     * @param string $content
     * @return string
     */
    public function get_html(string $content): string
    {
        $label = $this->options['source'] ? $this->get_msg('label_copy_src') : $this->get_msg('label_copy_text');
        $icon = $this->get_icon();
        $id = self::$id++;
        $msg = PLUGIN_CLIPBOARD_SHOW_MSG ? ' data-msg="' . $this->get_msg('label_copied') . '"' : '';
        $class = $this->options['class'] ?? '';
        $data = $this->options['source'] ? ' data-src' : '';
        $source = $this->options['source'] ? $this->get_source_text('div') : '';
        $html = <<<EOD
        <div id="clipboardBlock$id" class="plugin-clipboard clipboard-block$class"$data>
            <div class="clipboard-display">
                $content
            </div>
            $source
            <div class="clipboard-button"$msg>
                $icon
                <span class="clipboard-label">$label</span>
            </div>
        </div>
        EOD;

        return $html;
    }
}
