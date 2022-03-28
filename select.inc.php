<?php
/**
* 連動可能なプルダウンを設置するプラグイン (配布版)
*
* @version 1.3
* @author kanateko
* @link https://jpngamerswiki.com/?f51cd63681
* @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
* -- Update --
* 2022-03-24 v1.3 スタイル要素の変更機能をいくつか追加
* 2022-03-23 v1.2 スクリプトを改善
*            v1.1 テーブル内でも使用できるように代替のセパレータの追加
*            v1.0 初版作成
*/

define('SELECT_ALT_SEPARATOR', '~');

/**
 * 初期化
 *
 * @return void
 */
function plugin_select_init()
{
    $messages['_wlparts_messages'] = [
        'msg_usage'   => '<p><i>&amp;select( &lt;list 1 | list 2 | list 3... &gt; [, name= , size= ] ) { [ group ] };</i></p>',
        'err_unknown' => '<p>&amp;select Error: The argument does not match any options. (%a)<p>',
        'err_empty'   => '<p>&amp;select Error: Could not find any contents.</p>',
    ];
    set_plugin_messages($messages);

}

/**
 * インライン型
 *
 * @return string $html プルダウン (select) or エラー
 */
function plugin_select_inline()
{
    global $_select_messages;

    $args = func_get_args();
    $group = array_pop($args);
    if (count($args) == 0) return $_select_messages['msg_usage'];

    $list = htmlsc(array_shift($args));
    if (empty($list)) return $_select_messages['err_empty'];

    $obj = new PluginSelect($list);
    if (! empty($group)) $obj->set_group($group);
    if (! empty($args)) $obj->set_options($args);
    $html = $obj->convert();

    return $html;

}

/**
 * プルダウン作成用クラス
 */
class PluginSelect
{
    private $list;
    private $group;
    private $options;
    private $err;
    private static $counts = 0;
    private static $loaded = false;

    /**
     * コンストラクタ
     *
     * @param string $list "|" で区切ったリスト項目
     */
    public function __construct($list)
    {
        if (strpos($list, '|') !== false) {
            $this->list = explode('|', $list);
        } else {
            $this->list = explode(SELECT_ALT_SEPARATOR, $list);
        }
    }

    /**
     * グループワードのセッター
     *
     * @param  string $group グループワード (コンバート済み)
     * @return void
     */
    public function set_group($group) {
        $this->group = $group;
    }

    /**
     * オプションの判別
     *
     * @param  array $args 残りの引数
     * @return void
     */
    public function set_options($args) {
        global $_select_messages;

        foreach ($args as $arg) {
            $arg = htmlsc($arg);
            if (strpos($arg, '=') !== false) {
                // とりあえず"="を挟んであればオプションとして格納
                list($key, $val) = explode('=', $arg);
                $this->options[$key] = $val;
            } else if ($arg == 'transparent') {
                $this->options[$arg] = true;
            } else {
                // 明らかに既定のオプションにマッチしていない場合はエラー
                $this->err = str_replace('%a', $arg, $_select_messages['err_unknown']);
            }
        }
    }

    /**
     * select要素の組み立て
     *
     * @return string プルダウン (select) or エラー
     */
    public function convert()
    {
        if (isset($this->err)) return $this->err;

        $name = $this->options['name'] ?? 'select' . self::$counts++;
        $group = isset($this->group) ? ' data-group="' . $this->group . '"' : '';
        $style = $this->merge_style();

        $pulldown = '';
        foreach ($this->list as $i => $item) {
            $pulldown .= '<option value="item' . $i . '">' . $item . '</option>' . "\n";
        }

        // グループワードが初出の場合は連動用のスクリプトを挿入
        if (self::$loaded == false && ! empty($this->group)) {
            self::$loaded= true;
            $selector = '.plugin-select[data-group]';
            $script = <<<EOD
<script>
document.addEventListener('DOMContentLoaded', () => {
    const pulldowns = document.querySelectorAll('$selector');

    for (const pulldown of pulldowns) {
        pulldown.addEventListener('change', (e) => {
            const ct = e.currentTarget;
            const index = ct.selectedIndex;
            const word = ct.dataset.group;
            const group = '.plugin-select[data-group="' + word + '"]';
            const targets = document.querySelectorAll(group);

            for (const t of targets) {
                t.selectedIndex = index;
            }
        });
    }
});
</script>
EOD;
        } else {
            $script = '';
        }

        $html = <<<EOD
<select class="plugin-select" name="$name" style="$style" $group>
    $pulldown
</select>
$script
EOD;

        return $html;
    }

    /**
     * オプションからスタイルを作成
     *
     * @return string スタイル
     */
    private function merge_style()
    {
        $op = $this->options;
        $style = '';

        foreach ($op as $key => $val) {
            switch ($key) {
                case 'size':
                    $style .= 'font-size:' . $op['size'] . ';';
                    break;
                case 'color':
                case 'border':
                    $style .= $key . ':' . $val . ';';
                    break;
                case 'bg':
                    $style .= 'background-color:' . $val . ';';
                    break;
                case 'transparent':
                    $style .= 'background-color:transparent;border:none;outline:none;';
                    break;
                default:
                    continue;
            }
        }

        return $style;
    }
}
