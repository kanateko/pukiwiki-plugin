<?php
/**
 * ホバーorタップでツールチップを表示するプラグイン
 *
 * @version 0.1
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license http://www.gnu.org/licenses/gpl.ja.html GPL
 * -- Update --
 * 2021-08-18 初版作成
 */

// 用語集ページ
define('TOOLTIP_GLOSSARY_PAGE', ':config/plugin/tooltip');

// デフォルト設定: 0 = disable, 連想配列で追加
define('TOOLTIP_ADD_DEFAULT_SETTINGS', array(
    'placement'   => 'auto',
    'allowHTML'   => 'true',
    'interactive' => 'true',
));

function plugin_tooltip_init()
{
    global $head_tags;
    $head_tags[] = '<script src="https://unpkg.com/@popperjs/core@2"></script>';
    $head_tags[] = '<script src="https://unpkg.com/tippy.js@6"></script>';
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
        'usage'   => '&amp;tooltip(&lt;term&gt;){&lt;definition&gt;};' . "\n",
        'unknown' => '&amp;tooltip Error: Unknown argument. ->',
        'def'     => '&amp;tooltip Error: Undefined term. ->',
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
            if ($this->def === false) $this->error = $this->msg['def'] . $this->term;
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
                case 'placement':
                    $regexp = $regexp ?: '/^((top|bottom|left|right|auto)(-(start|end))?)$/';
                    if (preg_match($regexp, $val)) {
                        $this->options['props'][$key] = $val;
                    } else {
                        $this->error = $this->msg['unknown'] . $arg;
                    }
                    break;
                default:
                    $this->error = $this->msg['unknown'] . $arg;
            }
        }
    }

    /**
     * ツールチップの作成
     */
    public function convert_tooltip()
    {
        $tag = substr(md5($this->term), 0, 10);
        $cfg = $this->const_tippy_props();

        // ツールチップ表示用スクリプト
        if (isset(self::$loaded[$tag])) {
            // 重複を無くす
            $script = '';
        } else {
            $script = <<<EOD
                document.addEventListener('DOMContentLoaded', () => {
                    tippy('.tooltip-$tag', {
                        content: '$this->def',
                        $cfg
                    });
                });
            EOD;
            self::$loaded[$tag] = true;
        }

        $html = '<span class="plugin-tooltip tooltip-' . $tag . '"
         id="tooltip' . self::$id++ . '">' . $this->term . '</span>';
        if ($script) {
            $html .= '<script>' . $script .'</script>';
        }
        return $html;
    }

    /**
     * tippyの設定
     */
    private function const_tippy_props()
    {
        $add = array();
        $cfg = '';

        // デフォルト設定の読み込み
        if (is_array(TOOLTIP_ADD_DEFAULT_SETTINGS)) {
            foreach (TOOLTIP_ADD_DEFAULT_SETTINGS as $key => $val) {
                $add[$key] = $val;
            }
        }

        // 引数による追加設定の読み込み
        if (! empty($this->options['props'])) {
            foreach ($this->options['props'] as $key => $val) {
                $add[$key] = $val;
            }
        }

        // 設定の整形
        foreach ($add as $key => $val) {
            if (! preg_match('/\d+|true|false/', $val)) $val = '\'' . $val . '\'';
            $cfg .= $key . ': ' . $val . ',' . "\n";
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
        preg_match_all('/:([^\|:]+?)\|(.*?)\n/', $this->source, $defs);

        foreach ($defs[1] as $i => $val) {
            $defs[2][$i] = preg_replace("/<\/?p>\n?/", '', convert_html($defs[2][$i]));
            self::$defs[$val] = $defs[2][$i];
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
