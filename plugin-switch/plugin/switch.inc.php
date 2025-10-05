<?php
/**
* プルダウンやスライダーと連動して表示内容を切り替えるプラグイン
*
* @version 1.2.1
* @author kanateko
* @link https://jpngamerswiki.com/?f51cd63681
* @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
* -- Updates --
* 2025-10-05 v1.2.1 numberでminを指定した場合にindexがズレる問題を修正
*                   rangeやnumberに0以下のstepを設定できないように変更
* 2025-10-03 v1.2.0 新たに数値入力式のnumberを追加
*                   従来のnumber→linearに変更
*                   rangeやlinearで初期表示される数字のフォーマットを改善
*                   range以外もinput-widthで幅を指定できるよう改善
* 2025-09-15 v1.1.2 numberで負の値のステップに対応
*                   numberで最小値が未指定の場合の処理を追加 (-INF)
* 2025-09-11 v1.1.1 numberで最大値が未指定の場合の処理を追加 (INF)
*                   表示する数字を千の位ごとに区切るように調整
*            v1.1.0 numberタイプを追加
* 2025-09-01 v1.0.2 オプションが空の場合に、表示要素があってもエラーが出る問題を修正
* 2024-09-09 v1.0.1 マルチライン内のテーブルが正しく変換されない問題を修正
* 2024-08-25 v1.0.0 初版作成
*/

define('PLUGIN_SWITCH_CSS', SKIN_DIR . 'css/switch.min.css');
define('PLUGIN_SWITCH_JS', SKIN_DIR . 'js/switch.min.js');

/**
 * 初期化
 *
 * @return void
 */
function plugin_switch_init()
{
    global $head_tags;

    $head_tags[] = '<link rel="stylesheet" href="' . PLUGIN_SWITCH_CSS . '?t=' . filemtime(PLUGIN_SWITCH_CSS) . '">';

    $messages['_switch_messages'] = [
        'err_unknown' => '#switch Error: Unknown Argument (%s)',
        'err_empty'   => '#switch Error: No Contents',
        'err_invalid' => '#switch Error: Invalid Value (%s)',
        'err_step' => '#switch Error: Step must be 1 or higher'
    ];

    set_plugin_messages($messages);
}

/**
 * インライン型
 *
 * @return string $html
 */
function plugin_switch_inline(string ...$args): string
{
    $switch = new PluginSwitchBase($args);
    $html = $switch->convert();

    return $html;
}

/**
 * ブロック型
 *
 * @return string $html
 */
function plugin_switch_convert(string ...$args): string
{
    $switch = new PluginSwitchML($args);
    $html = $switch->convert();

    return $html;
}

/**
 * インライン用クラス
 */
class PluginSwitchBase
{
    protected const DEFAULT_CLASS = 'plugin-switch';
    protected const DEFAULT_GROUP = 'default';
    protected const DEFAULT_HTML_TAG = 'span';
    protected const DEFAULT_SEPARATOR = ':';
    protected const DEFAULT_TYPE = 'default';
    protected const DEFAULT_RANGE_ATTRS = [1, 10, 1]; // min, max, step
    protected string $class;
    protected string $group;
    protected string $tag_type;
    protected ?string $separator;
    protected array $options = [];
    protected array $err = [];
    protected ?array $items;
    protected static $counts = 0;
    protected static $start_index = [];

    /**
     * コンストラクタ
     *
     * @param string
     */
    public function __construct(array $args)
    {
        $this->class = static::DEFAULT_CLASS;
        $this->group = static::DEFAULT_GROUP;
        $this->tag_type = static::DEFAULT_TYPE;

        if (count($args) > 0) {
            $items_str = array_pop($args);
            $this->parse_options($args);
            $this->parse_items($items_str);
        } else {
            $this->err = ['err_empty'];
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

        $id = 'switch' . self::$counts++;
        $html = match($this->tag_type) {
            'select' => $this->convert_select($id),
            'range'  => $this->convert_range($id),
            'linear' => $this->convert_linear($id),
            'number' => $this->convert_number($id),
            default  => $this->convert_default($id)
        };

        // 最初の呼び出しでスクリプトを挿入
        if (self::$counts == 1) {
            $src = './' . PLUGIN_SWITCH_JS . '?t=' . filemtime(PLUGIN_SWITCH_JS);
            $html = <<<EOD
            $html
            <script type="module" defer>
                import { pluginSwitch } from '$src';
                pluginSwitch();
            </script>
            EOD;
        }

        return $html;
    }

    /**
     * selectタイプの作成
     *
     * @param string $id
     * @return string
     */
    public function convert_select(string $id): string
    {
        $html = '';
        $label = $this->get_label($id);
        $class = $this->class . ' switch-select switch-controller';
        $data_attr = '';
        $data_attr .= $this->options['transparent'] != null ? ' data-transparent' : '';
        $data_attr .= $this->options['disable'] != null ? ' data-disable' : '';
        $data_attr .= $this->options['rtl'] != null ? ' data-rtl' : '';
        $style = $this->options['input-width'] != null ? ' style="' . $this->options['input-width'] . '"' : '';
        $items = '';

        foreach ($this->items as $i => $item) {
            $selected = self::$start_index[$this->group] == $i ? ' selected' : '';
            $items .= $items == '' ? '' : "\n";
            $items .= '<option value="item' . $i . '" class="switch-item"' . $selected . '>' . $item . '</option>';
        }

        if ($items != '') {
            $html = <<<EOD
            $label
            <select name="$id" id="$id" class="{$class}"$style data-group="{$this->group}"$data_attr>
            $items
            </select>
            EOD;
        }

        return $html;
    }

    /**
     * rangeタイプの作成
     *
     * @param string $id
     * @return string
     */
    public function convert_range(string $id): string
    {
        $html = '';
        $label = $this->get_label($id);
        $index = self::$start_index[$this->group];
        [$min, $max, $step] = $this->get_range_attributes();

        if (! $step > 0) {
            $this->err = ['err_step'];
            return $this->show_msg();
        }

        $initial_value =  $index * $step + $min;

        // 最小最大の検証
        if (! $this->is_valid_minmax($min, $max, $initial_value)) return $this->show_msg();

        $decimals = $this->get_decimals($initial_value);
        $value =  number_format($initial_value, $decimals);
        $class = $this->class . ' switch-range switch-controller';
        $style = $this->options['input-width'] != null ? ' style="' . $this->options['input-width'] . '"' : '';
        $attr = '';
        $attr .= ' min="' . $min . '" data-min="'. $min . '"';
        $attr .= ' max="' . $max . '" data-max="'. $max . '"';
        $attr .= ' step="' . $step . '" data-step="'. $step . '"';

        $html = <<<EOD
        $label
        <input type="range" name="$id" id="$id" class="$class"$style$attr value="$initial_value" data-group="{$this->group}" /><output>$value</output>
        EOD;

        return $html;
    }

    /**
     * linearタイプの作成
     *
     * @param string $id
     * @return string
     */
    public function convert_linear(string $id): string
    {
        $html = '';
        $index = self::$start_index[$this->group];
        [$min, $max, $step] = $this->get_range_attributes(true);
        $initial_value = $this->calcurate_initial_value($index, $min, $max, $step);

        // 最小最大の検証
        if (! $this->is_valid_minmax($min, $max, $initial_value)) return $this->show_msg();

        $decimals = $this->get_decimals($initial_value);
        $initial_value =  number_format($initial_value, $decimals);
        $tag = static::DEFAULT_HTML_TAG;
        $class = $this->class . ' switch-linear';
        $attr = 'data-min="'. $min . '" data-max="'. $max . '" data-step="'. $step . '" data-initval="' . $initial_value . '"';
        $html = "<$tag id=\"$id\" class=\"$class\" data-group=\"{$this->group}\"$attr>$initial_value</$tag>";

        return $html;
    }

    /**
     * numberタイプの作成
     *
     * @param string $id
     * @return string
     */
    public function convert_number(string $id): string
    {
        $html = '';
        $label = $this->get_label($id);
        $index = self::$start_index[$this->group];
        [$min, $max, $step] = $this->get_range_attributes(true);

        if (! $step > 0) {
            $this->err = ['err_step'];
            return $this->show_msg();
        }

        $initial_value = $this->calcurate_initial_value($index, $min, $max, $step);

        // 最小最大の検証
        if (! $this->is_valid_minmax($min, $max, $initial_value)) return $this->show_msg();

        $class = $this->class . ' switch-number switch-controller';
        $style = $this->options['input-width'] != null ? ' style="' . $this->options['input-width'] . '"' : '';
        $attr = '';
        $attr .= ' min="' . $min . '" data-min="'. $min . '"';
        $attr .= ' max="' . $max . '" data-max="'. $max . '"';
        $attr .= ' step="' . $step . '" data-step="'. $step . '"';
        $attr .= '" data-initval="'. $initial_value . '"';

        $html = <<<EOD
        $label
        <input type="number" name="$id" id="$id" class="$class"$style$attr value="$initial_value" data-group="{$this->group}" />
        EOD;

        return $html;
    }

    /**
     * 平文タイプの作成
     *
     * @param string $id
     * @return string
     */
    public function convert_default(string $id): string
    {
        $html = '';
        $tag = static::DEFAULT_HTML_TAG;
        $class = $this->class . ' switch-default';
        $items = '';

        foreach ($this->items as $i => $item) {
            $selected = self::$start_index[$this->group] == $i ? ' data-selected' : '';
            $items .= '<' . $tag . ' class="switch-item"' . $selected . '>' . $item . '</' . $tag . '>';
        }

        if ($items != '') {
            $html = '<' . $tag . ' id="' . $id . '" class="' . $class . '" data-group="' . $this->group . '">' . $items . '</' . $tag . '>';
        }

        return $html;
    }

    /**
     * 表示する初期値を計算する
     *
     * @param integer $index
     * @param float $min
     * @param float $max
     * @param float $step
     * @return float
     */
    public function calcurate_initial_value(int $index, float $min, float $max, float $step): float
    {
        $initial_value = $step > 0 ? $min + $index * $step : $max + $index * $step;
        $initial_value = is_infinite($initial_value) ? 0 + $index * $step : $initial_value;

        return $initial_value;
    }

    /**
     * 小数点以下の桁数を取得
     *
     * @param float $value
     * @return integer
     */
    public function get_decimals(float $value): int
    {
        $decimals = 0;

        $str_num = (string)$value;
        $place = strpos($str_num, '.');

        if ($place !== false) {
            $decimals = strlen($str_num) - $place - 1;
        }

        return $decimals;
    }

    /**
     * ラベル要素の作成
     *
     * @param string $id
     * @return string
     */
    public function get_label(string $id): string
    {
       return $this->options['label'] != null ? '<label for="' . $id . '" class="switch-label">' . $this->options['label'] . '</label>' : '';
    }

    /**
     * min,max,stepを取得
     *
     * @return array
     */
    public function get_range_attributes(bool $accept_inf = false): array
    {
        $min = $this->items[0] === '' && $accept_inf ? -INF : $this->items[0] ?? static::DEFAULT_RANGE_ATTRS[0];
        $max = $this->items[1] === '' && $accept_inf ? INF : $this->items[1] ?? static::DEFAULT_RANGE_ATTRS[1];
        $step = $this->items[2] ?? static::DEFAULT_RANGE_ATTRS[2];

        return [(float)$min, (float)$max, (float)$step];
    }

    /**
     * 最小最大の検証
     *
     * @param float $min
     * @param float $max
     * @param float $value
     * @return boolean
     */
    public function is_valid_minmax(float $min, float $max, float $initial_value): bool
    {
        $is_valid = true;

        if ($min > $max) {
            $this->err = ['err_invalid' => 'min:' . $min . ' > max:' . $max];
            $is_valid = false;
        } elseif ($initial_value < $min) {
            $this->err = ['err_invalid' => 'start:' . $initial_value . ' < min:' . $min];
            $is_valid = false;
        } elseif ($initial_value > $max) {
            $this->err = ['err_invalid' => 'start:' . $initial_value . ' > max:' . $max];
            $is_valid = false;
        }

        return $is_valid;
    }

    /**
     * オプションの判別
     *
     * @param array $args
     * @return void
     */
    public function parse_options(array $args): void
    {
        if (count($args) > 0) {
            // 引数の分解
            foreach ($args as $arg) {
                $arg = htmlsc($arg);
                [$key, $val] = array_map('trim', explode('=', $arg));

                if ($val != null) {
                    if ($key == 'group') {
                        // グループ名
                        $this->group = $val;
                    } elseif ($key == 'separator') {
                        // セパレータ
                        $this->separator = $val;
                    } elseif ($key == 'type' && ($val == 'select' || $val == 'range' || $val == 'default')) {
                        // 表示タイプ
                        $this->tag_type = $val;
                    } elseif ($key == 'start' && is_numeric($val)) {
                        // 開始番号
                        $index = (int)$val - 1;
                        $index = $index < 0 ? 0 : $index;
                        self::$start_index[$this->group] ??= $index;
                    } elseif ($key == 'label') {
                        // ラベル
                        $this->options['label'] = $val;
                    } elseif ($key == 'class') {
                        // クラス
                        $this->class .= ' ' . $val;
                    } elseif (($key == 'input-width' || $key == 'slider-width') && preg_match('/(\d+)(px|%|em|rem|[lsd]?v([wh]|min|max))?/', $val, $m)) {
                        // input要素の幅
                        $unit = $m[2] ?? 'px';
                        $this->options['input-width'] = 'width:' . $m[1] . $unit;
                    } else {
                        $this->err = ['err_unknown' => $arg];
                        break;
                    }
                } elseif ($key == 'select' || $key == 'range' || $key == 'linear' || $key === 'number' || $key == 'default') {
                    // 表示タイプ
                    $this->tag_type = $key;
                } elseif ($key == 'transparent' || $key == 'disable' || $key == 'rtl') {
                    // select用のオプション
                    $this->options[$key] = true;
                } else {
                    // グループ名
                    $this->group = preg_replace('/^~/', '', $key);
                }
            }
        }

        // デフォルト設定
        $this->set_default_options();
    }

    /**
     * 未設定のオプションにデフォルト設定を適用
     *
     * @return void
     */
    public function set_default_options(): void
    {
        $this->separator ??= static::DEFAULT_SEPARATOR;
        self::$start_index[$this->group] ??= 0;
    }

    /**
     * アイテムの分割
     *
     * @param string $items_str
     * @return void
     */
    public function parse_items(string $items_str): void
    {
        if ($items_str === '') {
            if ($this->tag_type === 'number' or $this->tag_type === 'linear') {
                $this->items =  ['', '', static::DEFAULT_RANGE_ATTRS[2]];
            } else {
                $this->err = ['err_empty'];
            }

            return;
        }

        $evac = [];

        // htmlタグを一時退避
        if (preg_match_all('/<.+?>/', $items_str, $m)) {
            foreach ($m[0] as $i => $tag) {
                $evac[$i] = $tag;
                $items_str = str_replace($tag, '{evac' . $i . '}', $items_str);
            }
        }

        // セパレータで分割
        $items_array = explode($this->separator, $items_str);

        // 退避させたタグをもとに戻す
        if (! empty($evac)) {
            foreach ($evac as $i => $tag) {
                foreach ($items_array as $j => $item) {
                    $items_array[$j] = str_replace('{evac' . $i . '}', $tag, $item);
                }
            }
        }

        if (! empty($items_array)) $this->items = $items_array;
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
        global $_switch_messages;

        $msg = '';

        if (array_values($this->err) === $this->err) {
            $msg = $_switch_messages[$this->err[0]];
        } else {
            foreach ($this->err as $key => $val) {
                $msg = $_switch_messages[$key];
                $msg = str_replace('%s', htmlsc($val), $msg);
            }
        }

        return "<p>$msg</p>";
    }
}

/**
 * マルチライン (ブロック) 用クラス
 */
class PluginSwitchML extends PluginSwitchBase
{
    protected const DEFAULT_HTML_TAG = 'div';
    protected const DEFAULT_SEPARATOR = '#-';

    /**
     * アイテムの分割
     *
     * @param string $items_str
     * @return void
     */
    public function parse_items(string $items_str): void
    {
        // 入れ子を一時的に退避させる
        $evac = [];

        if (preg_match_all('/#.+?({{2,})/', $items_str, $m)) {
            foreach ($m[0] as $i => $start) {
                $end = str_replace('{', '}', $m[1][$i]);

                preg_match('/' . preg_quote($start) . '[\s\S]+?' . $end . '/', $items_str, $m_evac);

                $evac[$i] = $m_evac[0];
                $items_str = str_replace($m_evac[0], '{evac' . $i . '}', $items_str);
            }
        }

        // セパレータで分割
        $items_array = explode($this->separator, $items_str);

        // 退避させた入れ子をもとに戻す
        if (! empty($evac)) {
            foreach ($items_array as $i => $item) {
                if (preg_match('/\{evac(\d+)\}/', $item, $m)) {
                    $items_array[$i] = str_replace($m[0], $evac[$m[1]], $item);
                }
            }
        }

        // 各アイテムをHTMLに変換する
        $items_array = str_replace(["\r", "\r\n"], "\n", $items_array);
        $items_array = array_map('trim', $items_array);
        $items_array = array_map('convert_html', $items_array);

        if (! empty($items_array)) $this->items = $items_array;
    }

}
