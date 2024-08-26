<?php
/**
* 指定した領域のタブ切り替え表示を可能にするプラグイン
*
* @version 2.1.0
* @author kanateko
* @link https://jpngamerswiki.com/?f51cd63681
* @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
* -- Updates --
* 2024-08-17 v2.1.0 ラベルのPukiWiki記法対応
*                   classオプションが機能していなかった問題を修正
* 2023-07-21 v2.0.1 入れ子退避の正規表現を修正
* 2023-07-20 v2.0.0 コードを全体的に改善
*                   入れ子に対応
*                   classオプションを追加
* 2022-02-08 v1.3.0 別タイプの書式を追加
*            v1.2.0 コードを整理
*                   ラベルの数が足りない場合のエラーを追加
* 2020-10-16 v1.1.0 タブの最大数を3つ -> 無制限に変更
*            v1.0.0 全体的に作り直し
* 2019-06-30 v0.1.0 初版作成、タブは3つに限定
*/

// タブ分割用の文字列 (配列で複数指定可)
define('PLUGIN_TAB_SEPARATOR', ['#-', '#split']);
// CSSのパス
define('PLUGIN_TAB_CSS', SKIN_DIR . 'css/tab.min.css');

/**
 * 初期化
 */
function plugin_tab_init(): void
{
    global $head_tags;

    $css = PLUGIN_TAB_CSS . '?=' . filemtime(PLUGIN_TAB_CSS);
    $head_tags[] = '<link rel="stylesheet" href="' . $css . '"/>';
    $msg['_tab_messages'] = [
        'msg_usage'  => '#tab{{<br>
                         #:&lt; label 1 &gt;<br>
                         &lt; content 1 &gt;<br>...<br>}}',
        'err_exceed' => '#tab Error: The number of contents exceeds the number of labels.'
    ];

    set_plugin_messages($msg);
}

function plugin_tab_convert(string ...$args): string
{
    $tab = new PluginTab($args);
    $html = $tab->convert();

    return $html;
}

/**
 * コンテンツのHTML変換
 */
class PluginTab
{
    private array $err;
    private array $labels;
    private array $contents;
    private array $nested_plugins;
    private bool $is_legacy;
    private static int $id;

    /**
     * コンストラクタ
     *
     * @param array $args
     */
    public function __construct(array $args)
    {
        $this->err = $this->labels = $this->contents = $this->nested_plugins = [];
        $this->is_legacy = false;
        self::$id ??= 0;

        $multiline = array_pop($args);
        $multiline = preg_replace("/\r|\r\n/", "\n", $multiline);

        if (empty($multiline) || ! str_contains($multiline, "\n")) {
            $this->err = ['msg_usage'];
        } else {
            $this->get_options($args);
            $this->get_contents($multiline);
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

        // 入れ子を戻す
        $this->back_nested();

        $html = '';
        $id = self::$id++;
        $num = 0;

        foreach ($this->contents as $label => $content) {
            $label = make_link($label);
            $content = convert_html($content);
            $checked = $num == 0 ? ' checked' : '';

            $html .= <<<EOD
            <input id="tab{$id}_$num" type="radio" name="tab{$id}" class="tab-switch"$checked />
            <label class="tab-label" for="tab{$id}_$num">$label</label>
            <div id="content{$id}_$num" class="tab-content">
                $content
            </div>
            EOD;

            $num++;
        }

        $class = $this->options['class'] != null ? ' ' . $this->options['class'] : '';

        $html = <<<EOD
        <div id="tab$id" class="plugin-tab$class">
            $html
        </div>
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
     * @return string
     */
    public function show_msg(): string
    {
        global $_tab_messages;

        $msg = '';

        if (array_values($this->err) === $this->err) {
            $msg = $_tab_messages[$this->err[0]];
        } else {
            foreach ($this->err as $key => $val) {
                $msg = $_tab_messages[$key];
                $msg = str_replace('$1', htmlsc($val), $msg);
            }
        }

        return "<p>$msg</p>";
    }

    /**
     * オプションとラベル (旧記法) の取得
     *
     * @param array $args
     * @return void
     */
    public function get_options(array $args): void
    {
        foreach ($args as $arg) {
            [$key, $val] = array_map('trim', explode('=', $arg));

            if ($key == 'class')  {
                // オプション
                $this->options[$key] = htmlsc($val);
            } else {
                // ラベル
                $this->labels[] = $arg;
            }
        }

        if (! empty($this->labels)) $this->is_legacy = true;
    }

    /**
     * コンテンツの取得 (新書式)
     *
     * @param string $ml
     * @return void
     */
    public function get_contents(string $ml): void
    {
        // 入れ子退避
        $ml = $this->evac_nested($ml);

        if ($this->is_legacy) {
            // オプションで事前にラベルを指定するタイプ
            $this->get_contents_legacy($ml);
        } else {
            // マルチライン内でラベルを指定するタイプ
            $lines = explode("\n", $ml);
            $label = '';

            foreach ($lines as $line) {
                if (preg_match('/^#:(.+)/', $line, $m)) {
                    $label = $m[1];
                } else {
                    $this->contents[$label] .= "$line\n";
                }
            }
        }
    }

    /**
     * コンテンツの取得 (旧書式)
     *
     * @param string $ml
     * @return void
     */
    public function get_contents_legacy(string $ml): void
    {
        $tag = PLUGIN_TAB_SEPARATOR;
        $lines = explode("\n", $ml);
        $i = 0;

        foreach ($lines as $line) {
            if (in_array($line, $tag)) {
                $i++;
            } else {
                if ($this->labels[$i] == null) {
                    // ラベルが足りない場合はエラー
                    $this->err = ['err_exceed'];
                    break;
                }

                $this->contents[$this->labels[$i]] .= "$line\n";
            }
        }
    }

    /**
     * 入れ子プラグインの退避
     *
     * @param string $ml
     * @return string
     */
    public function evac_nested(string $ml): string
    {
        $i = 0;

        while(preg_match('/\n(#.+?(?:\(.+?\))?({{2,}))\n/', $ml, $m)) {
            $start = $m[2];
            $end = str_replace('{', '}', $start);
            $pattern = '/' . preg_quote($m[1], '/') . '[\s\S]+?' . "\n$end" . '/';

            preg_match($pattern ,$ml, $nested);
            $this->nested_plugins[$i] = $nested[0];
            $ml = preg_replace('/' . preg_quote($nested[0], '/') . '/', '%np' . $i++, $ml);
        }

        return $ml;
    }

    /**
     * 入れ子プラグインを元に戻す
     *
     * @return void
     */
    public function back_nested(): void
    {
        foreach($this->contents as $label => $content) {
            foreach($this->nested_plugins as $i => $replace) {
                $pattern = "%np$i";
                $content = str_replace($pattern, $replace, $content);
                $this->contents[$label] = $content;
            }
        }
    }
}
