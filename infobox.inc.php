<?php
/**
 * テンプレートを読み込んでインフォボックスを設置するプラグイン
 *
 * @version 1.1.0
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * -- Update --
 * 2024-09-13 v1.1.0 他プラグインとの互換性を改善
 *            v1.0.1 複数行の値でコメントアウトに対応
 * 2024-09-01 v1.0.0 コードを整理
 *                   デフォルト値を設定する機能を追加
 *                   値を複数行にわたって書けるように改善
 *                   値が空白のキーは非表示扱いに変更
 * 2023-07-25 v0.6.0 =前後の空白に対応
 * 2022-03-19 v0.5.0 同ページ内に同じテンプレートを使用したインフォボックスを複数設置できるよう改善
 * 2021-08-04 v0.4.0 指定した文字列を含む行をinclude時に除外する機能を追加
 *            v0.3.0 テンプレートページの凍結が必要かどうかを設定できる機能を追加
 *            v0.2.0 テンプレートの読み込みがループする場合はエラーを表示する機能を追加
 * 2021-08-03 v0.1.0 初版作成
 */

/**
 * 初期化
 *
 * @return void
 */
function plugin_infobox_init(): void
{
    $msg['_infobox_messages'] = [
        'msg_usage'    => '#infobox([template][,nozoom][,class=xxx][,except=xxx]){{<br>&lt;key&gt; = xxx<br>...<br>}}<br>',
        'err_notpl'    => '#infobox Error: The template does not exist. (%s)',
        'err_loop'     => '#infobox Error: The template is already included. (%s)',
        'err_self'     => '#infobox Error: It is not allowed to load current page as template.',
        'err_freeze'   => '#infobox Error: Safe mode is enabled. Please freeze template pages.',
        'err_map'      => '#infobox Error: Could not find multiline argument.'
    ];

    set_plugin_messages($msg);
}

/**
 * Undocumented function
 *
 * @param string ...$args
 * @return string
 */
function plugin_infobox_convert(string ...$args): string
{
    $infobox = new PluginInfobox($args);

    return $infobox->convert();
}

/**
 * インフォボックスの作成
 */
class PluginInfobox {
    private const KEY_FORMAT = '{{{%s}}}';
    private const KEY_REGEXP = '([^{}]+)';
    private const DEFAULT_VALUE_SEPARATOR = ':';
    private const TEMPLATE_PATH = ':config/plugin/infobox/';
    private const ENABLE_SAFE_MODE = false;
    private string $template_page;
    private array $template;
    private array $map;
    private array $options = [];
    private array $err = [];
    private static ?array $included;

    public function __construct($args)
    {
        self::$included ??= [];
        $num = count($args);

        // 引数の分解とエラー処理
        if ($num < 2) {
            $this->err = ['msg_usage'];
        } else {
            $template_page = self::TEMPLATE_PATH . array_shift($args);
            $multiline = array_pop($args);

            if (! str_contains($multiline ,"\r")) {
                // マルチライン無し
                $this->err = ['err_map'];
            } elseif (! is_page($template_page)) {
                // テンプレート無し
                $this->err = ['err_notpl' => $template_page];
            } else {
                $this->template_page = $template_page;
                $this->options = $this->parse_options($args);
                $this->template = $this->parse_template($template_page);
                $this->map = [...$this->map, ...self::parse_keyvals($multiline)];
            }
        }
    }

    /**
     * HTMLへの変換
     *
     * @return string
     */
    public function convert(): string
    {
        if ($this->has_error()) return $this->show_msg();

        $html = '';
        $source = $this->template;
        $class = $this->options['class'] ?? '';

        foreach($this->map as $key => $val) {
            $search = str_replace('%s', $key, self::KEY_FORMAT);
            $source = str_replace($search, $val, $source);
        }

        // HTMLを整形
        $html = $this->strip_lines($source);
        $html = convert_html($html);
        $html = <<<EOD
        <div class="infobox$class">
        $html
        </div>
        EOD;

        self::$included[$this->template_page] = null;

        return $html;
    }

    /**
     * 不要な行の削除
     *
     * @param array $source
     * @return string
     */
    public function strip_lines(array $source): string
    {
        // 未使用のキー削除
        foreach ($source as $i => $line) {
            $pattern = '/' . str_replace('%s', self::KEY_REGEXP, self::KEY_FORMAT) . '/';
            if (preg_match($pattern, $line)) {
                unset($source[$i]);
            }
        }

        $html = implode($source);

        // noinlude部分を削除
        $html = preg_replace('/==noinclude==[\s\S]*?==\/noinclude==\s/', '', $html);

        return $html;
    }

    /**
     * テンプレートを取得
     *
     * @param string $template_page
     * @return array
     */
    public function parse_template(string $template_page): array
    {
        global $vars;

        $template = [];
        $current_page = $vars['page'];

        // エラーチェック
        if (self::$included[$template_page] === true) {
            // ループ
            $this->err = ['err_loop' => $template_page];
        } elseif ($current_page === $template_page) {
            // ループ (現ページ)
            $this->err = ['err_self'];
        } elseif (self::ENABLE_SAFE_MODE && ! is_freeze($template_page)) {
            // セーフモード
            $this->err = ['err_freeze'];
        }

        // 問題なければテンプレートを取得
        if (! $this->has_error()) {
            self::$included[$template_page] = true;
            $template = get_source($template_page);

            // 不要な行を削除
            foreach ($template as $i => $line) {
                // 不要なプラグイン
                if (str_starts_with($line, '#author') || str_starts_with($line, '#freeze')) {
                    unset($template[$i]);
                }

                // 除外設定
                if ($this->options['except'] !== null) {
                    if (preg_match($this->options['except'], $line)) {
                        unset($template[$i]);
                    }
                }
            }

            // デフォルト値の取得と置き換え
            $template = $this->get_default_values($template);
        }

        return $template;
    }

    /**
     * デフォルト値の取得と置き換え
     *
     * @param array $template
     * @return array
     */
    public function get_default_values(array $template): array
    {
        $key_regexp = self::KEY_REGEXP;
        $separator = self::DEFAULT_VALUE_SEPARATOR;
        $regexp = '/' . str_replace('%s', $key_regexp .  $separator . $key_regexp, self::KEY_FORMAT) . '/';
        $map = [];

        // デフォルト値の取得
        foreach ($template as $i => $line) {
            if (preg_match($regexp, $line, $m)) {
                $map[$m[1]]= $m[2];
                $replace = str_replace('%s', $m[1], self::KEY_FORMAT);
                $template[$i] = str_replace($m[0], $replace, $line);
            }
        }

        $this->map = $map;

        return $template;
    }

    /**
     * マルチライン引数からキーと値を取得する
     *
     * @param string $multiline
     * @return array
     */
    public static function parse_keyvals(string $multiline): array
    {
        $map = [];
        $lines = explode("\r", trim($multiline));
        $current_key = '';

        foreach ($lines as $line) {
            if (preg_match('/(.+?)\s*(?<!\\\)=\s*(.+)?/', $line, $m)) {
                $current_key = '';

                if ($m[2] !== null) {
                    $map[$m[1]] = $m[2];
                } else {
                    // 複数行のキーを設定
                    $current_key = $m[1];
                }
            } elseif ($current_key !== '') {
                // 複数行での値指定
                if (str_starts_with($line, '//')) continue;

                $map[$current_key] = $map[$current_key] === null ? '' : $map[$current_key] . "&br;";
                $map[$current_key] .= str_replace('\=', '=', $line);
            }
        }

        return $map;
    }

    /**
     * オプションの判別
     *
     * @param array $args
     * @return array
     */
    public function parse_options(array $args): array
    {
        $options = [];
        $class_array = [];

        foreach($args as $arg) {
            [$key, $val] = array_map('trim', explode('=', $arg));

            if ($val !== null) {
                if ($key === 'except') {
                    // 正規表現での除外設定
                    $options['except'] = '/' . str_replace('/', '\/', $val) . '/';
                } elseif ($key === 'class') {
                    // 追加クラス
                    $class_array[] = $val;
                }
            } elseif ($key === 'nozoom') {
                // 追加クラス
                $class_array[] = $key;
            }
        }

        // クラスの整形
        if ($class_array !== []) {
            $options['class'] = ' ' . htmlsc(implode(' ', $class_array));
        }

        return $options;
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
        global $_infobox_messages;

        $msg = '';

        if (array_values($this->err) === $this->err) {
            $msg = $_infobox_messages[$this->err[0]];
        } else {
            foreach ($this->err as $key => $val) {
                $msg = $_infobox_messages[$key];
                $msg = str_replace('%s', htmlsc($val), $msg);
            }
        }

        return "<p>$msg</p>";
    }
}
