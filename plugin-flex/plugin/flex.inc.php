<?php
/**
* flexレイアウト用プラグイン (配布版)
*
* @version 1.5.0
* @author kanateko
* @link https://jpngamerswiki.com/?f51cd63681
* @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
* -- Updates --
* 2024-08-21 v1.5.0 align-itemsを指定するオプションを追加
*                   各アイテムのmax-widthとmin-widthを指定するオプションを追加
*                   gapを指定するオプションを追加
* 2023-03-17 v1.4.0 scrollオプションを追加
* 2022-07-31 v1.3.0 マルチラインプラグインの入れ子に対応
*                   公開用にコードを整理
* 2022-04-13 v1.2.0 クラスを追加する機能を実装
*                   要素幅の指定方法を拡張
* 2021-07-12 v1.1.0 menuオプションの画像の切り抜きをやめてmenu-squareとして分離
* 2021-07-03 v1.0.0 初版作成
*/

define('PLUGIN_FLEX_CSS', SKIN_DIR . 'css/flex.min.css');

/**
 * 初期化
 *
 * @return void
 */
function plugin_flex_init():void
{
    global $head_tags;

    $msg['_flex_messages'] = [
        'err_unknown' => '#flex Error: Unknown argument. ($1)'
    ];
    set_plugin_messages($msg);

    $head_tags[] = '<link rel="stylesheet" href="' . PLUGIN_FLEX_CSS . '?t=' . filemtime(PLUGIN_FLEX_CSS) . '">';
}

/**
 * ブロック型
 *
 * @param array $args
 * @return string
 */
function plugin_flex_convert(string ...$args):string
{
    $flex = new PluginFlex($args);

    return $flex->convert();
}

/**
 * flexレイアウト作成用クラス
 */
class PluginFlex
{
    private const SPLIT_TAG = '#-';
    private array $err = [];
    private array $options = [];
    private string $type = 'block';
    private string $multiline = '';

    /**
     * コンストラクタ
     *
     * @param array $args
     */
    public function __construct(array $args)
    {
        $this->multiline = preg_replace("/\r|\r\n/", "\n", array_pop($args));
        if (! empty($args)) $this->parse_options($args);
    }

    /**
     * flexコンテナと各要素の組み立て
     *
     * @return string $html
     */
    public function convert():string
    {
        $boxes = $this->get_boxes();
        $style = $this->options['style'] != null ? ' style="' . implode(';', $this->options['style']) . '"' : '';
        $class = $this->options['auto'] ? 'flex' : 'plugin-flex';
        if ($this->options['class'] != null) $class .= ' ' . implode(' ', $this->options['class']);

        $html = <<<EOD
        <div class="$class"$style>
            $boxes
        </div>
        EOD;

        return $html;
    }

    /**
     * オプションの判別
     *
     * @param array $args
     * @return void
     */
    private function parse_options($args):void
    {
        foreach ($args as $arg) {
            $arg = htmlsc($arg);
            [$key, $val] = array_map('trim', explode('=', $arg));

            if ($val != null) {
                if (($key == 'class')) {
                    // クラス追加
                    $this->options[$key][] = $val;
                } elseif ($key == 'justify') {
                    // justify-content
                    $this->options['style'][] = 'justify-content:' . $val;
                } else if ($key == 'align') {
                    // align-items
                    $this->options['style'][] = 'align-items:' . $val;
                } elseif ($key == 'gap') {
                    // gap
                    $val = is_numeric($val) && $val !== '0' ? $val . 'px' : $val;
                    $this->options['style'][] = $key . ':' . $val;
                } elseif (preg_match('/(min-|max-)?width/', $key)) {
                    // width, min-width, max-width
                    $val = is_numeric($val) && $val !== '0' ? $val . 'px' : $val;
                    $this->options['box_style'][] = $key . ':' . $val;
                }
            } elseif (preg_match('/^\d+(px|%|em|rem|vw|vh)?$/', $key, $m)) {
                $this->options['box_style'][] = $m[1] ? 'width:' . $arg : 'width:' . $arg . 'px';
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
                        // justify-content
                        $this->options['style'][] = 'justify-content:' . $arg;
                        break;
                    case 'nogap':
                    case 'nowrap':
                    case 'border':
                    case 'scroll':
                        // プリメイドのクラス追加
                        $this->options['class'][] = 'flex-' . $arg;
                        break;
                    default:
                        $this->err = ['err_unknown' => $arg];
                }
            }
        }
    }

    /**
     * 分割表示する各要素の組み立て
     *
     * @return string $boxes
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
        $style = $this->options['box_style'] != null ? ' style="' . implode(';', $this->options['box_style']) . '"' : '';

        foreach ($boxes_raw as $box) {
            $box = convert_html($box);
            $boxes .= <<<EOD
            <div class="flex-box"$style>
                $box
            </div>
            EOD;
        }

        return $boxes;
    }

    /**
     * エラーの確認
     *
     * @return boolean
     */
    public function has_error(): bool
    {
        return $this->err != [];
    }

    /**
     * メッセージ表示
     *
     * @return string
     */
    public function show_msg(): string
    {
        global $_flex_messages;

        $msg = '';
        $type = $this->type ?? 'block';

        if ($this->options["noerror"] == null) {
            if (array_values($this->err) === $this->err) {
                $msg = $_flex_messages[$this->err[0]];
            } else {
                foreach ($this->err as $key => $val) {
                    $msg = $_flex_messages[$key];
                    $msg = str_replace('%s', htmlsc($val), $msg);
                }
            }

            if ($type == 'block') $msg = "<p>$msg</p>";
        }

        return $msg;
    }
}

