<?php
/**
 * ネタバレ防止用プラグイン
 *
 * @version 1.0.0
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * -- Updates --
 * 2024-02-07 v1.0.0 初版作成
 */

// css
define('PLUGIN_SPOILER_CSS', SKIN_DIR . 'css/spoiler.css');
// デフォルトの解除設定：hover / click
define('PLUGIN_SPOILER_DEFAULT_MODE', 'click');

/**
 * 初期化
 *
 * @return void
 */
function plugin_spoiler_init()
{
    global $head_tags;

    $msg['_spoiler_messages'] = [
        'err_empty' => '',
        'err_unknown' => '#spoiler Error: Unknown argument (%s)'
    ];
    set_plugin_messages($msg);

    $head_tags[] = '<link rel="stylesheet" href="' . PLUGIN_SPOILER_CSS . '?t=' . filemtime(PLUGIN_SPOILER_CSS) . '">';
}

/**
 * インライン型
 *
 * @param string ...$args
 * @return string
 */
function plugin_spoiler_inline(string ...$args): string
{
    return plugin_spoiler_main($args, 'inline');
}

/**
 * ブロック型
 *
 * @param string ...$args
 * @return string
 */
function plugin_spoiler_convert(string ...$args): string
{
    return plugin_spoiler_main($args, 'block');
}

/**
 * メイン処理
 *
 * @param array $args
 * @param string $type
 * @return string
 */
function plugin_spoiler_main(array $args, string $type): string
{
    $spoiler = new PluginSpoiler($args, $type);

    if ($spoiler->has_error()) return $spoiler->show_msg();
    else return $spoiler->convert();
}

Class PluginSpoiler
{
    public array $err;
    private string $type;
    private string $mode;
    private string $body;
    private static int $id;

    /**
     * コンストラクタ
     *
     * @param array $args プラグインの引数
     */
    public function __construct(array $args, string $type)
    {
        $this-> err = [];
        self::$id ??= 0;

        if (count($args) < 1) {
            $this->err = ['msg_empty'];
        } else {
            // オプションと本文
            $this->parse_options($args);
        }

        $this->type = $type;
        $this->mode ??= PLUGIN_SPOILER_DEFAULT_MODE;
    }

    public function convert(): string
    {
        $body = $this->type === 'inline' ? $this->body : convert_html($this->body);
        $mode = $this->mode;
        $tag = $this->type === 'inline' ? 'span' : 'div';
        $id = self::$id++;

        $html = <<<EOD
        <$tag class="plugin-spoiler">
            <input class="spoiler-check" type="checkbox" id="spoiler$id" style="display:none">
            <label class="spoiler-top" for="spoiler$id" data-mode="$mode"></label>
            $body
        </$tag>
        EOD;

        return $html;
    }

    /**
     * エラーの確認
     *
     * @return boolean
     */
    public function has_error(): bool
    {
        return $this->err !== [];
    }

    /**
     * メッセージ表示
     *
     * @param string $type
     * @return string
     */
    public function show_msg(): string
    {
        global $_spoiler_messages;

        $msg = '';
        $type = $this->type;

        if (array_values($this->err) === $this->err) {
            $msg = $_spoiler_messages[$this->err[0]];
        } else {
            foreach ($this->err as $key => $val) {
                $msg = $_spoiler_messages[$key];
                $msg = str_replace('%s', htmlsc($val), $msg);
            }
        }

        if ($type === 'block') $msg = "<p>$msg</p>";

        return $msg;
    }

    private function parse_options(array $args): void
    {
        // 本文
        $body = array_pop($args);

        if (empty($body)) $this->err = ['err_empty'];
        else $this->body = $body;

        // 解除方法指定
        foreach($args as $arg) {
            if ($arg === 'hover' || $arg === 'click') $this->mode = $arg;
            else $this->err = ['err_unknown' => $arg];
        }
    }
}