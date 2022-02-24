<?php
/**
 * tippy.jsを使用するプラグイン向けのクラス
 *
 * @version 0.2
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * -- Update --
 * 2022-02-25 v0.2 デフォルト設定を変更する機能を追加
 * 2021-11-24 v0.1 初版作成
 */

// デフォルト設定: 配列以外 = disable, 連想配列で追加
define('TIPPY_ADD_DEFAULT_SETTINGS', array(
    'animation'   => 'shift-toward',
    'allowHTML'   => 'true',
    'interactive' => 'true',
));

function tippy_init($arr = null) {
    global $head_tags, $tippy_loaded;

    $default = $arr ?? TIPPY_ADD_DEFAULT_SETTINGS;

    if (! $tippy_loaded) {
        // 必要なライブラリの読み込み
        $head_tags[] = '<script src="https://unpkg.com/@popperjs/core@2"></script>';
        $head_tags[] = '<script src="https://unpkg.com/tippy.js@6"></script>';
        if (isset($default['animation'])) {
            $head_tags[] = '<link rel="stylesheet" href="https://unpkg.com/tippy.js@6/animations/' . TIPPY_ADD_DEFAULT_SETTINGS['animation'] . '.css">';
        }
        if (isset($default['theme'])) {
            $head_tags[] = '<link rel="stylesheet" href="https://unpkg.com/tippy.js@6/themes/' . TIPPY_ADD_DEFAULT_SETTINGS['theme'] . '.css">';
        }
        // tippy.jsのデフォルト設定を出力
        $cfg = Tippy::const_tippy_props($default);
        $head_tags[] = <<<EOD
        <script>
            tippy.setDefaultProps({
                $cfg
            });
        </script>
        EOD;

        $tippy_loaded = true;
    }
}

/**
 * ツールチップの作成
 */
class Tippy
{
    public $error;
    public $options;

    /**
     * オプション判別
     */
    public function set_tippy_options($args)
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
                        $this->error = $this->msg['unknown'] . $arg . ' ';
                    }
                    break;
                default:
                    $this->error = $this->msg['unknown'] . $arg . ' ';
            }
        }
    }

    /**
     * tippyの設定
     */
    public static function const_tippy_props($props)
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

?>