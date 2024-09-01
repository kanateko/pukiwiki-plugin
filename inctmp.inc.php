<?php
/**
 * テンプレートを使ってページ内容の一部を表示するプラグイン
 *
 * @version 1.1.0
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * -- Updates --
 * 2024-08-31 v1.1.0 各キーのデフォルト値を指定する機能を追加
 *                   値を複数行にわたってかけるように改善
 * 2023-09-25 v1.0.1 出力時の余分な改行を削除
 *                   不要な行を削除する際、改行が残る問題を修正
 * 2023-07-19 v1.0.0 初版作成
 */

// テンプレートページ
define('PLUGIN_INCTMP_PAGE', ':config/plugin/inctmp/');
// テンプレートの編集制限チェック
define('PLUGIN_INCTMP_SAFEMODE', false);
// キーの書式
define('PLUGIN_INCTMP_KEY_FORMAT', '{{{%s}}}');
// デフォルト値検索用の正規表現
// preg_match時に $matches[1] = キー $matches[2] = デフォルト値 になるようにする
define('PLUGIN_INCTMP_DEFAULT_VALUE_REGEXP', '([^{}]+):([^{}]+)');

/**
 * 初期化
 *
 * @return void
 */
function plugin_inctmp_init(): void
{
    $msg['_inctmp_messages'] = [
        'msg_usage' => '#inctmp(&lt;template&gt;){{ &lt;key = val&gt; }}',
        'err_notemplate' => '#inctmp Error: The template does not exist. (%s)',
        'err_nopermission' => '#inctmp Error: You are not allowed to access the template page. (%s)',
        'err_loop' => '#inctmp Error: Loop detected. (%s)',
        'err_unknown' => '#inctmp Error: Unknown argument. (%s)'
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
    private string $template;
    private array $err = [];
    private array $options = [];
    private array $map = [];
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
            $this->err = ['msg_usage'];
        } else {
            $this->template = PLUGIN_INCTMP_PAGE . array_shift($args);

            // エラーチェック
            if (! is_page($this->template)) $this->err = ["err_notemplate" => $this->template];
            elseif (! $this->has_permission()) $this->err = ["err_nopermission" => $this->template];
            elseif (! empty($args)) $this->parse_options($args);
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
    public function get_contents(): string
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
            $contents = $this->get_default_values($contents);
            $contents = $this->replace_by_key($contents);
            $contents = $this->strip($contents);
            $contents = convert_html($contents);
        }

        self::$included = [];

        return $contents;
    }

    /**
     * 各キーのデフォルトの値を取得する
     *
     * @param string $contents
     * @return string
     */
    public function get_default_values(string $contents): string
    {
        $pattern = str_replace('%s', PLUGIN_INCTMP_DEFAULT_VALUE_REGEXP, PLUGIN_INCTMP_KEY_FORMAT);

        if (preg_match_all('/' . $pattern . '/', $contents, $m)) {
            foreach ($m[2] as $i => $default_value) {
                $this->map[$m[1][$i]] ??= $default_value;
                $contents = str_replace($m[0][$i], str_replace('%s', $m[1][$i], PLUGIN_INCTMP_KEY_FORMAT), $contents);
            }
        }


        return $contents;
    }

    /**
     * キーから値への置き換え
     *
     * @param string $contents
     * @return string
     */
    public function replace_by_key(string $contents): string
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
    public function strip(string $contents): string
    {
        $format = preg_quote(PLUGIN_INCTMP_KEY_FORMAT, '/');
        $pattern = "/.*" . str_replace('%s', '.+?', $format) . ".*\n?/";
        $contents = preg_replace($pattern, '', $contents);
        $contents = preg_replace('/==noinclude==[\s\S]+?==\/noinclude==/', '', $contents);

        return $contents;
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
     * @return string
     */
    public function show_msg(): string
    {
        global $_inctmp_messages;

        $msg = '';

        if (array_values($this->err) === $this->err) {
            $msg = $_inctmp_messages[$this->err[0]];
        } else {
            foreach ($this->err as $key => $val) {
                $msg = $_inctmp_messages[$key];
                $msg = str_replace('%s', htmlsc($val), $msg);
            }
        }

        return "<p>$msg</p>";
    }

    /**
     * テンプレートページが利用可能かチェック
     *
     * @return boolean
     */
    public function has_permission(): bool
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
    public function parse_options(array $args): void
    {
        $multiline = null;

        foreach ($args as $arg) {
            if (str_contains($arg, "\r")) {
                $multiline = preg_replace("/\r$/", '', $arg);
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
            $multi_key = '';

            foreach ($lines as $line) {
                if (preg_match('/(.+?)\s*(?<!\\\)=\s*(.+)?/', $line, $m)) {
                    $multi_key = '';

                    if ($m[2] !== null) {
                        $this->map[$m[1]] = $m[2];
                    } else {
                        // 複数行のキーを設定
                        $multi_key = $m[1];
                    }
                } elseif ($multi_key !== '') {
                    // 複数行での値指定
                    $this->map[$multi_key] = $this->map[$multi_key] === null ? '' : $this->map[$multi_key] . "&br;";
                    $this->map[$multi_key] .= str_replace('\=', '=', $line);
                }
            }

        }
    }
}