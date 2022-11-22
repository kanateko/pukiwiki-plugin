<?php
/**
 * ホバーorタップでツールチップを表示するプラグイン
 *
 * @version 0.6
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * -- Update --
 * 2022-11-22 v0.6 用語の定義方法にテーブルの書式を追加
 * 2021-11-24 v0.5 noteプラグインと併用できるようtippy.js関連部分を分離
 * 2021-08-21 v0.4 デフォルト設定の出力が1回で済むよう修正
 *                 デフォルト設定でのanimationやthemeの設定に対応
 *                 引数で設定可能なプロパティにmaxWidthを追加
 * 2021-08-20 v0.3 識別用文字列を使って、同じ語句でツールチップの内容を切り替える機能を追加
 *            v0.2 対象の語句がページとして存在している場合はツールチップにページリンクを挿入する機能を追加
 *                 エラー表示時に前後に改行を入れるよう変更
 *                 ツールチップの内容を取得する際に改行コードを排除するように修正
 * 2021-08-18 v0.1 初版作成
 */

// tippy.js関連クラスの読み込み
require_once('tippy.php');

// 用語集ページ
define('TOOLTIP_GLOSSARY_PAGE', ':config/plugin/tooltip');
// 語句がページとして存在する場合にリンクを追加するかどうか
define('TOOLTIP_ENABLE_AUTOLINK', true);
// 語句と識別用文字列のセパレータ
define('TOOLTIP_TERM_SEPARATOR', ':');
// テーブル形式の定義を許可する
define('TOOLTIP_ENABLE_TABLE_GLOSSARY', true);

function plugin_tooltip_init()
{
    tippy_init();
}

function plugin_tooltip_convert()
{
    return;
}

function plugin_tooltip_inline(...$args)
{
    $tooltip = new PluginTooltip($args);
    return $tooltip->error ?: $tooltip->convert_tooltip();
}

/**
 * ツールチップの作成
 */
class PluginTooltip extends Tippy
{
    private $term;
    private $def;
    private static $id = 0;
    private static $loaded = array();

    public $msg = array(
        'usage'   => ' &amp;tooltip(&lt;term&gt;){&lt;definition&gt;}; ',
        'unknown' => ' &amp;tooltip Error: Unknown argument. -> ',
        'def'     => ' &amp;tooltip Error: Undefined term. -> ',
    );

    public function __construct($args)
    {
        if (func_num_args() == 0) return $this->msg['usage'];
        $glossary = new GlossaryForTooltip;
        $this->term = htmlsc(array_shift($args));
        $this->def = array_pop($args);

        // 対象の定義を検索or設定
        if (empty($this->def)) {
            $this->def = $glossary->search_defs($this->term);
            if ($this->def === false) $this->error = $this->msg['def'] . $this->term . ' ';
        } else {
            $glossary->add_defs($this->term, $this->def);
        }

        // オプション判別
        if ($args) $this->set_tippy_options($args);
    }

    /**
     * ツールチップの作成
     */
    public function convert_tooltip()
    {
        $tag = substr(md5($this->term), 0, 10);
        $cfg = self::const_tippy_props($this->options['props']);
        $def = $this->def;
        $term = $this->term;

        // ページとして存在するかチェック
        if (TOOLTIP_ENABLE_AUTOLINK && is_page($term)) {
            $def .='<hr>詳細: ' . make_pagelink($term);
        }

        // 識別用文字列が含まれているかチェック
        if (strpos($term, TOOLTIP_TERM_SEPARATOR) !== false) {
            $term = explode(TOOLTIP_TERM_SEPARATOR, $term, 2)[0];
        }

        // ツールチップ表示用スクリプト
        if (isset(self::$loaded[$tag])) {
            // 重複を無くす
            $script = '';
        } else {
            $script = <<<EOD
                document.addEventListener('DOMContentLoaded', () => {
                    tippy('.tooltip-$tag', {
                        content: '$def',
                        $cfg
                    });
                });
            EOD;
            self::$loaded[$tag] = true;
        }

        $html = '<span class="plugin-tooltip tooltip-' . $tag . '"
         id="tooltip' . self::$id++ . '">' . $term . '</span>';
        if ($script) {
            $html .= '<script>' . preg_replace('/\s{2,}/', ' ', preg_replace('/\r|\n|\r\n/', ' ', $script)) .'</script>';
        }

        return $html;
    }
}

/**
 * 用語集ページの操作関連
 */
class GlossaryForTooltip
{
    private $source;
    private static $defs;

    public function __construct()
    {
        if (empty(self::$defs)) {
            $this->source = get_source(TOOLTIP_GLOSSARY_PAGE);
            $this->init_defs();
        }
    }

    /**
     * 用語集から定義を抽出
     */
    private function init_defs()
    {
        $pattern = TOOLTIP_ENABLE_TABLE_GLOSSARY ? '/^(\||:)(.+?)\|([^\|]+)\|?(?<!\|(h|c))$/' : '/^:(.+?)\|(.*)$/';
        foreach ($this->source as $line) {
            if (preg_match($pattern, $line, $m)) {
                $txt = convert_html($m[3]);
                $txt = preg_replace('/<script>[\s\S]+<\/script>/', '', $txt);
                self::$defs[$m[2]] = $txt;
            } else {
                continue;
            }
        }
    }

    /**
     * 定義を追加 (ページ単位)
     */
    public function add_defs($key, $val)
    {
        self::$defs[$key] = $val;
    }

    /**
     * 定義の検索
     */
    public function search_defs($term)
    {
        if (isset(self::$defs[$term])) {
            return self::$defs[$term];
        } else {
            return false;
        }
    }
}
