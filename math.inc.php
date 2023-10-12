<?php
/**
 * 数学関数を使って様々計算を行うプラグイン
 *
 * @version 1.0.0
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * -- Updates --
 * 2023-10-10 v1.0.0 初版作成
 */

// デフォルトのタイプ
define('PLUGIN_MATH_DEFAULT_TYPE', 'round');
// デフォルトの小数点以下の桁数 (round, 区切り表示時のみ適用)
define('PLUGIN_MATH_DEFAULT_PRECISION', 2);
// 数字をカンマ区切りで表示する
define('PLUGIN_MATH_FORMAT_AS_DEFAULT', false);

function plugin_math_init(): void
{
    $msg['_math_messages'] = [
        'msg_usage'            => '&math([type, precision, true/false]){values};',
        'err_unknown'          => '#math Error: Unknown argument. (%s)',
        'err_empty'            => '#math Error: Need to enter values.',
        'err_invalid'          => '#math Error: Invalid values.',
    ];
    set_plugin_messages($msg);
}

function plugin_math_inline(string ...$args): string
{
    $math = new PluginMath($args);
    $result = $math->calc();

    return $result;
}

class PluginMath
{
    const TYPES = [
        'abs' => 1,     // 絶対値
        'bindec' => 1,  // 2 進数 を 10 進数に変換する
        'ceil' => 1,    // 端数の切り上げ
        'cos' => 1,     // 余弦（コサイン）
        'decbin' => 1,  // 10 進数を 2 進数に変換する
        'dechex' => 1,  // 10 進数を 16 進数に変換する
        'decoct' => 1,  // 10 進数を 8 進数に変換する
        'deg2rad' => 1, // 度単位の数値をラジアン単位に変換する
        'fdiv' => 2,    // IEEE 754 に従い、数値の除算を行う
        'floor' => 1,   // 端数の切り捨て
        'fmod' => 2,    // 引数で除算をした際の剰余を返す
        'hypot' => 2,   // 直角三角形の斜辺の長さを計算する
        'intdiv' => 2,  // 整数値の除算
        'max' => 2,     // 最大値を返す
        'min' => 2,     // 最小値を返す
        'octdec' => 1,  // 8 進数を 10 進数に変換する
        'pi' => 0,      // 円周率の値を得る
        'pow' => 2,     // 指数表現
        'rad2deg' => 1, // ラジアン単位の数値を度単位に変換する
        'round' => 1,   // 浮動小数点数を丸める
        'sin' => 1,     // 正弦（サイン）
        'sqrt' => 1,    // 平方根
        'tan'  => 1,    // 正接（タンジェント）
    ];

    public array $err;
    private string $type;
    private array $vals;
    private array $options;

    /**
     * コンストラクタ
     *
     * @param array $args
     */
    public function __construct(array $args)
    {
        $this->err = [];
        $val_str = array_pop($args);

        if ($val_str === '') {
            $this->vals = [];
        } else {
            $this->get_vals($val_str);
        }

        if (! empty($args) && $this->vals !== null) {
            $this->get_options($args);
        }

        // デフォルト設定
        $this->type ??= PLUGIN_MATH_DEFAULT_TYPE;
        $this->options['format'] ??= PLUGIN_MATH_FORMAT_AS_DEFAULT;
        $this->options['precision'] ??= $this->options['formant'] || $this->type === 'round' ? PLUGIN_MATH_DEFAULT_PRECISION : null;

        if ($this->vals === [] && $this->type !== 'pi') $this->err = ['err_empty'];
    }

    /**
     * 計算
     *
     * @return string
     */
    public function calc(): string
    {
        if ($this->has_error()) return $this->show_msg();

        $result = '';
        $type = $this->type;
        $val1 = $this->vals[0];
        $val2 = $this->vals[1];
        $precision = $this->options['precision'];

        if ($this->type === 'round') {
            $result = $type($val1, $precision);
        } else {
            if ($val2 !== null) {
                $result = $type($val1, $val2);
            } elseif ($val1 !== null) {
                $result = $type($val1);
            } else {
                $result = $type();
            }

            if ($precision !== null) $result = round($result, $precision);
        }

        if ($this->options['format']) $result = number_format($result, $precision);

        return $result;
    }

    /**
     * 数値の格納
     *
     * @param string $val_str
     * @return void
     */
    private function get_vals(string $val_str): void
    {
        $vals = explode(',', $val_str);

        foreach ($vals as $val) {
            $val = trim($val);

            if (! is_numeric($val)) {
                $this->err = ['err_invalid' => $val];
                break;
            }

            $this->vals[] = $val;
        }
    }

    /**
     * タイプとオプションの格納
     *
     * @param array $args
     * @return void
     */
    private function get_options(array $args): void
    {
        $args = array_map('trim', $args);
        $type = array_shift($args) ?: PLUGIN_MATH_DEFAULT_TYPE;
        $val_num = self::TYPES[$type];

        if ($val_num !== null) {
            if ($val_num === count($this->vals)) {
                $this->type = $type;

                foreach($args as $i => $arg) if ($arg === '') $args[$i] = null;

                // 小数点以下の桁数
                if (is_numeric($args[0])) $this->options['precision'] = $args[0];
                elseif ($args[0] !== null) $this->err = ['err_unknown' => $args[0]];

                // カンマ区切り
                if ($args[1] === 'false') $this->options['format'] = false;
                elseif ($args[1] === 'true') $this->options['format'] = true;
                elseif ($args[1] !== null) $this->err = ['err_unknown' => $args[1]];
            } else {
                $this->err = ['err_invalid' => $type . ': ' . implode(', ', $this->vals)];
            }
        } else {
            $this->err = ['err_unknown' => $type];
        }
    }

    /**
     * エラーの確認
     *
     * @return boolean
     */
    private function has_error(): bool
    {
        return $this->err !== [];
    }

    /**
     * メッセージ表示
     *
     * @return string
     */
    private function show_msg(): string
    {
        global $_math_messages;

        $msg = '';

        if (array_values($this->err) === $this->err) {
            $msg = $_math_messages[$this->err[0]];
        } else {
            foreach ($this->err as $key => $val) {
                $msg = $_math_messages[$key];
                $msg = str_replace('%s', htmlsc($val), $msg);
            }
        }

        return "<p>$msg</p>";
    }
}