<?php
/**
 * テンプレートを使ってページ内容の一部を表示するプラグイン
 *
 * @version 1.0.1
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * -- Updates --
 * 2023-09-25 v1.0.1 出力時の余分な改行を削除
 * 2023-07-19 v1.0.0 初版作成
 */

// テンプレートページ
define('PLUGIN_INCTMP_PAGE', ':config/plugin/inctmp/');
// テンプレートの編集制限チェック
define('PLUGIN_INCTMP_SAFEMODE', false);
// キーの書式
define('PLUGIN_INCTMP_KEY_FORMAT', '{{{%s}}}');

/**
 * 初期化
 *
 * @return void
 */
function plugin_inctmp_init(): void
{
    $msg['_inctmp_messages'] = [
        'msg_usage' => '#inctmp(&lt;template&gt;){{ &lt;key = val&gt; }}',
        'err_notemplate' => '#inctmp Error: The template does not exist. ($1)',
        'err_nopermission' => '#inctmp Error: You are not allowed to access the template page. ($1)',
        'err_loop' => '#inctmp Error: Loop detected. ($1)',
        'err_unknown' => '#inctmp Error: Unknown argument. ($1)'
    ];
    set_plugin_messages($msg);
}

/**
 * ブロック型
 *
 * @param string ...$args
 * @return string
 */
function plugin_inctmp_convert(string ...$args): string
{
    $inctmp = new PluginIncTmp($args);

    if ($inctmp->has_error()) return $inctmp->show_msg();

    $html = $inctmp->convert();

    return $html;
}

/**
 * テンプレートの読み込みと出力の作成
 */
class PluginIncTmp
{
    private $err;
    private $template;
    private $options;
    private $map;
    private static $included;

    /**
     * コンストラクタ
     *
     * @param array $args
     */
    public function __construct(array $args)
    {
        self::$included ??= [];

        $num = count($args);

        if ($num < 1) {
            $this->err = 'msg_usage';
        } else {
            $this->template = PLUGIN_INCTMP_PAGE . array_shift($args);

            // エラーチェック
            if (! is_page($this->template)) $this->err = ["err_notemplate" => $this->template];
            elseif (! $this->has_permission()) $this->err = ["err_nopermission" => $this->template];
            elseif (! empty($args)) $this->breakdown($args);
        }
    }

    /**
     * コンテンツの出力
     *
     * @return string
     */
    public function convert(): string
    {
        $contents = $this->get_contents();

        if ($this->has_error()) return $this->show_msg();

        $class = $this->options['class'] ? ' ' . htmlsc($this->options['class']) : '';
        $id = substr(md5($this->template), 0, 10);

        $html = <<<EOD
        <div class="plugin-inctmp$class" data-id="$id">
        $contents
        </div>
        EOD;

        return $html;
    }

    /**
     * テンプレートの置き換えとコンテンツの取得
     *
     * @return string
     */
    private function get_contents(): string
    {
        $contents = '';

        if (self::$included[$this->template]) {
            // ループチェック
            $this->err = ['err_loop' => $this->template];
        } else {
            self::$included[$this->template] = true;
            $source = get_source($this->template);

            foreach($source as $line) {
                if (preg_match('/#(author|freeze)/', $line)) {
                    continue;
                } else {
                    if (preg_match('/(?:^|\|)#inctmp\(([^,]+)\)/', $line, $m)) {
                        // ループチェック
                        if (self::$included[$m[1]]) {
                            $this->err = ['err_loop', $m[1]];
                            break;
                        } else {
                            self::$included[$m[1]] = true;
                        }
                    }

                    $contents .= $line;
                }
            }
        }

        if (! $this->has_error()) {
            $contents = $this->replace_by_key($contents);
            $contents = $this->strip($contents);
            $contents = convert_html($contents);
        }

        self::$included = [];

        return $contents;
    }

    /**
     * キーから値への置き換え
     *
     * @param string $contents
     * @return string
     */
    private function replace_by_key(string $contents): string
    {
        foreach($this->map as $key => $val) {
            $key = preg_quote($key, '/');
            $format = preg_quote(PLUGIN_INCTMP_KEY_FORMAT, '/');
            $pattern = '/' . str_replace('%s', $key, $format) . '/';
            $contents = preg_replace($pattern, $val, $contents);
        }

        return $contents;
    }

    /**
     * 不要な部分の削除
     *
     * @param string $contents
     * @return string
     */
    private function strip(string $contents): string
    {
        $format = preg_quote(PLUGIN_INCTMP_KEY_FORMAT, '/');
        $pattern = '/.*' . str_replace('%s', '.+?', $format) . '.*/';
        $contents = preg_replace($pattern, '', $contents);
        $contents = preg_replace('/==noinclude==[\s\S]+?==\/noinclude==/', '', $contents);

        return $contents;
    }

    /**
     * エラー確認
     *
     * @return boolean
     */
    public function has_error(): bool
    {
        return $this->err !== null;
    }

    /**
     * メッセージ表示
     *
     * @return string
     */
    public function show_msg(): string
    {
        global $_inctmp_messages;

        $msg = '';

        if (is_string($this->err)) {
            $msg = $_inctmp_messages[$this->err];
        } else {
            foreach ($this->err as $key => $rpl) {
                $rpl = htmlsc($rpl);
                $msg = $_inctmp_messages[$key];
                $msg = str_replace('$1', $rpl, $msg);
            }
        }

        return "<p>$msg</p>";
    }

    /**
     * テンプレートページが利用可能かチェック
     *
     * @return boolean
     */
    private function has_permission(): bool
    {
        $is_safe = PLUGIN_INCTMP_SAFEMODE ? ! check_editable($this->template, true, false) : true;
        $is_readable = check_readable($this->template, true, false);

        return $is_readable && $is_safe;
    }

    /**
     * 引数の分解
     *
     * @param array $args
     * @return void
     */
    private function breakdown(array $args): void
    {
        $multiline = null;

        foreach ($args as $arg) {
            if (str_contains($arg, "\r")) {
                $multiline = $arg;
            } elseif (preg_match('/^(class)\s*=\s*(.+)/', $arg, $m)) {
                // オプション
                $this->options[$m[1]] = $m[2];
            } else {
                // 不明
                $this->err = ['err_unknown' => $arg];
                break;
            }
        }

        if ($multiline !== null) {
            // キーワードと値
            $lines = explode("\r", $multiline);

            foreach ($lines as $line) {
                preg_match('/(.+?)\s*=\s*(.+)/', $line, $m);

                if ($m[1] && $m[2]) {
                    $this->map[$m[1]] = $m[2];
                }
            }
        }
    }
}