<?php
/**
* flexレイアウト用プラグイン (配布版)
*
* @version 1.4.0
* @author kanateko
* @link https://jpngamerswiki.com/?f51cd63681
* @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
* -- Updates --
* 2023-03-17 v1.4.0 scrollオプションを追加
* 2022-07-31 v1.3.0 マルチラインプラグインの入れ子に対応
*                   公開用にコードを整理
* 2022-04-13 v1.2.0 クラスを追加する機能を実装
*                   要素幅の指定方法を拡張
* 2021-07-12 v1.1.0 menuオプションの画像の切り抜きをやめてmenu-squareとして分離
* 2021-07-03 v1.0.0 初版作成
*/

function plugin_flex_init():void
{
    global $head_tags;

    $msg['_flex_messages'] = [
        'err_unknown' => '#flex Error: Unknown argument. ($1)'
    ];
    set_plugin_messages($msg);

    $head_tags[] = '<link rel="stylesheet" href="' . SKIN_DIR . 'css/flex.css">';
}

/**
 * ブロック型
 *
 * @param array $args プラグインの引数
 * @return string フレックスコンテナ
 */
function plugin_flex_convert(...$args):string
{
    $flex = new PluginFlex($args);
    if ($flex->err) return $flex->err;
    else return $flex->convert();
}

/**
 * flexレイアウト作成用クラス
 *
 * @var string SPLIT_TAG 要素を分割するためのタグ
 * @property string $err エラーメッセージ
 * @property string $multiline マルチライン部分
 * @property array $options オプション
 */
class PluginFlex
{
    const SPLIT_TAG = '#-';

    public $err;
    private $multiline;
    private $options;

    /**
     * コンストラクタ
     *
     * @param array $args プラグインの引数
     */
    public function __construct($args)
    {
        $this->multiline = preg_replace("/\r|\r\n/", "\n", array_pop($args));
        if (! empty($args)) $this->parse_options($args);
    }

    /**
     * flexコンテナと各要素の組み立て
     *
     * @return string $html HTMLに変換したコンテナと各要素
     */
    public function convert():string
    {
        $boxes = $this->get_boxes();
        $class = 'plugin-flex';
        if ($this->options['class']) $class .= ' ' . implode(' ', $this->options['class']);

        $html = <<<EOD
        <div class="$class" style="{$this->options['justify']}">
            $boxes
        </div>
        EOD;

        return $html;
    }

    /**
     * オプションの判別
     *
     * @param array $args マルチライン部分を取り除いた引数
     * @return void
     */
    private function parse_options($args):void
    {
        $op = &$this->options;
        foreach ($args as $arg) {
            $arg = htmlsc($arg);
            if (preg_match('/^class=(.+)$/', $arg, $m)) {
                $op['class'][] = $m[1];
            } elseif (preg_match('/^\d+(px|%|em|rem|vw|vh)?$/', $arg, $m)) {
                $op['box_width'] = $m[1] ? 'width:' . $arg : 'width:' . $arg . 'px';
            } else {
                switch ($arg) {
                    case 'flex-start':
                    case 'flex-end':
                    case 'start':
                    case 'end':
                    case 'left':
                    case 'right':
                    case 'center':
                    case 'space-around':
                    case 'space-between':
                    case 'space-evenly':
                    case 'stretch':
                        $op['justify'] = 'justify-content:' . $arg;
                        break;
                    case 'nogap':
                    case 'nowrap':
                    case 'border':
                    case 'scroll':
                        $op['class'][] = 'flex-' . $arg;
                        break;
                    default:
                        $this->err = $this->get_messages('err_unknown', $arg);
                }
            }
        }
    }

    /**
     * 分割表示する各要素の組み立て
     *
     * @return string $boxes HTMLに変換した各要素
     */
    private function get_boxes():string
    {
        // 入れ子を一時的に退避させる
        $nested = [];
        $src = $this->multiline;
        if (preg_match_all('/#.+?({{2,})/', $src, $m)) {
            foreach ($m[0] as $i => $start) {
                $end = str_replace('{', '}', $m[1][$i]);
                preg_match('/' . preg_quote($start) . '[\s\S]+?' . $end . '/', $src, $m_nested);
                $nested[$i] = $m_nested[0];
                $src = str_replace($m_nested[0], '%' . $i . '%', $src);
            }
        }

        // 分割後に退避させていた入れ子部分を元に戻す
        $boxes_raw = explode(self::SPLIT_TAG, $src);
        if ($nested) {
            foreach ($boxes_raw as $i => $box) {
                if (preg_match('/%(\d+)%/', $box, $m)) {
                    $boxes_raw[$i] = str_replace($m[0], $nested[$m[1]], $box);
                }
            }
        }

        // HTMLに変換
        $boxes = '';
        foreach ($boxes_raw as $box) {
            $box = convert_html($box);
            $boxes .= <<<EOD
            <div class="flex-box" style="{$this->options['box_width']}">
                $box
            </div>
            EOD;
        }

        return $boxes;
    }

    /**
     * エラーメッセージの取得
     *
     * @param string $key 表示するメッセージの指定
     * @param string|null $replace メッセージの置き換え用
     * @return string 表示するメッセージ
     */
    private function get_messages($key, $replace = null):string
    {
        global $_flex_messages;

        if ($replace !== null) {
            $msg = str_replace('$1', $replace, $_flex_messages[$key]);
        } else {
            $msg = $_flex_messages[$key];
        }

        return '<p>' . $msg . '</p>';
    }
}
