<?php
/**
 * ホバーorタップでツールチップを表示するプラグイン
 *
 * @version 0.4
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license http://www.gnu.org/licenses/gpl.ja.html GPL
 * -- Update --
 * 2021-08-21 v0.4 デフォルト設定の出力が1回で済むよう修正
 *                 デフォルト設定でのanimationやthemeの設定に対応
 *                 引数で設定可能なプロパティにmaxWidthを追加
 * 2021-08-20 v0.3 識別用文字列を使って、同じ語句でツールチップの内容を切り替える機能を追加
 *            v0.2 対象の語句がページとして存在している場合はツールチップにページリンクを挿入する機能を追加
 *                 エラー表示時に前後に改行を入れるよう変更
 *                 ツールチップの内容を取得する際に改行コードを排除するように修正
 * 2021-08-18 v0.1 初版作成
 */

// 用語集ページ
define('TOOLTIP_GLOSSARY_PAGE', ':config/plugin/tooltip');

// デフォルト設定: 配列以外 = disable, 連想配列で追加
define('TOOLTIP_ADD_DEFAULT_SETTINGS', array(
    'animation'   => 'shift-toward',
    'allowHTML'   => 'true',
    'interactive' => 'true',
));

// 語句がページとして存在する場合にリンクを追加するかどうか
define('TOOLTIP_ENABLE_AUTOLINK', true);

// 語句と識別用文字列のセパレータ
define('TOOLTIP_TERM_SEPARATOR', ':');

function plugin_tooltip_init()
{
    global $head_tags;
    $head_tags[] = '<script src="https://unpkg.com/@popperjs/core@2"></script>';
    $head_tags[] = '<script src="https://unpkg.com/tippy.js@6"></script>';
    if (isset(TOOLTIP_ADD_DEFAULT_SETTINGS['animation'])) {
        $head_tags[] = '<link rel="stylesheet" href="https://unpkg.com/tippy.js@6/animations/' . TOOLTIP_ADD_DEFAULT_SETTINGS['animation'] . '.css">';
    }
    if (isset(TOOLTIP_ADD_DEFAULT_SETTINGS['theme'])) {
        $head_tags[] = '<link rel="stylesheet" href="https://unpkg.com/tippy.js@6/themes/' . TOOLTIP_ADD_DEFAULT_SETTINGS['theme'] . '.css">';
    }
}

function plugin_tooltip_convert()
{
    return;
}

function plugin_tooltip_inline()
{
    $args = func_get_args();
    $tooltip = new Tooltip($args);
    return $tooltip->error ?: $tooltip->convert_tooltip();
}

/**
 * ツールチップの作成
 */
class Tooltip
{
    private static $id = 0;
    private static $loaded = array();
    private $term;
    private $def;
    public $error;

    public $msg = array(
        'usage'   => '<br>&amp;tooltip(&lt;term&gt;){&lt;definition&gt;};<br>',
        'unknown' => '<br>&amp;tooltip Error: Unknown argument. -> ',
        'def'     => '<br>&amp;tooltip Error: Undefined term. -> ',
    );

    private $options = array(
        'props'   => array(),
    );

    public function __construct($args)
    {
        if (func_num_args() == 0) return $this->msg['usage'];
        $glossary = new Glossary;
        $this->term = htmlsc(array_shift($args));
        $this->def = array_pop($args);

        // 対象の定義を検索or設定
        if (empty($this->def)) {
            $this->def = $glossary->search_defs($this->term);
            if ($this->def === false) $this->error = $this->msg['def'] . $this->term . '<br>';
        } else {
            $glossary->add_defs($this->term, $this->def);
        }

        // オプション判別
        if ($args) $this->set_options($args);
    }

    /**
     * オプション判別
     */
    private function set_options($args)
    {
        foreach ($args as $arg) {
            $arg = htmlsc($arg);
            $regexp = false;
            list($key, $val) = explode('=', $arg);
            switch ($key) {
                case 'arrow':
                    $regexp = '/^(true|false)$/';
                    // no break
                case 'maxWidth':
                    $regexp = $regexp ?: '/^\d+$/';
                    // no break
                case 'placement':
                    $regexp = $regexp ?: '/^((top|bottom|left|right|auto)(-(start|end))?)$/';
                    if (preg_match($regexp, $val)) {
                        $this->options['props'][$key] = $val;
                    } else {
                        $this->error = $this->msg['unknown'] . $arg . '<br>';
                    }
                    break;
                default:
                    $this->error = $this->msg['unknown'] . $arg . '<br>';
            }
        }
    }

    /**
     * ツールチップの作成
     */
    public function convert_tooltip()
    {
        $tag = substr(md5($this->term), 0, 10);
        $cfg = $this->const_tippy_props($this->options['props']);
        $def = $this->def;
        $term = $this->term;

        // デフォルト設定
        if (self::$id === 0) {
            $default = $this->const_tippy_props(TOOLTIP_ADD_DEFAULT_SETTINGS);
            if ($default) {
                $default = <<<EOD
                tippy.setDefaultProps({
                    $default
                });
                EOD;
            }
        } else {
            $default = '';
        }

        // ページとして存在するかチェック
        if (TOOLTIP_ENABLE_AUTOLINK && is_page($term)) {
            $def .='<hr>詳細: ' . make_pagelink($term);
        }

        // 識別用文字列が含まれているかチェック
        if (strpos($term, ':') !== false) {
            $term = explode(TOOLTIP_TERM_SEPARATOR, $term, 2)[0];
        }

        // ツールチップ表示用スクリプト
        if (isset(self::$loaded[$tag])) {
            // 重複を無くす
            $script = '';
        } else {
            $script = <<<EOD
                document.addEventListener('DOMContentLoaded', () => {
                    $default
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

    /**
     * tippyの設定
     */
    private function const_tippy_props($props)
    {
        $cfg = '';

        // プロパティの整形
        if (is_array($props) && ! empty($props)) {
            foreach ($props as $key => $val) {
                if (! preg_match('/^(|\d+|true|false)$|^\[([\d\s,]+)\]$/', $val)) {
                    $val = '\'' . $val . '\'';
                }
                $cfg .= $key . ': ' . $val . ',' . "\n";
            }
        }

        return $cfg;
    }
}

/**
 * 用語集ページの操作関連
 */
class Glossary
{
    private $source;
    private static $defs;

    public function __construct()
    {
        if (empty(self::$defs)) {
            $this->source = get_source(TOOLTIP_GLOSSARY_PAGE, true, true);
            $this->init_defs();
        }
    }

    /**
     * 用語集から定義を抽出
     */
    private function init_defs()
    {
        preg_match_all('/:(.+?)\|(.*)\n/', $this->source, $defs);

        foreach ($defs[1] as $i => $val) {
            $defs[2][$i] = preg_replace('/<\/?p>\n?/', '', convert_html($defs[2][$i]));
            self::$defs[$val] = preg_replace('/\r|\n|\r\n/', '', $defs[2][$i]);
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
        if (array_key_exists($term, self::$defs)) {
            return self::$defs[$term];
        } else {
            return false;
        }
    }
}
?>
