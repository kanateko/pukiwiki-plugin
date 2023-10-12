<?php
/**
 * 与えた数式の答えを算出するプラグイン
 *
 * @version 1.0.0
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * -- Updates --
 * 2023-10-11 v1.0.0 初版作成
 */

// 数字をカンマ区切りで表示する
define('PLUGIN_CALC_FORMAT_AS_DEFAULT', false);
// カンマ区切り時のデフォルトの小数点以下の桁数
define('PLUGIN_CALC_DEFAULT_PRECISION', 2);

function plugin_calc_init(): void
{
    $msg['_calc_messages'] = [
        'msg_usage'            => '&calc([precision, true/false]){formula};',
        'err_unknown'          => '#calc Error: Unknown argument. (%s)',
        'err_invalid'          => '#calc Error: Invalid formula.',
    ];
    set_plugin_messages($msg);
}

function plugin_calc_inline(string ...$args): string
{
    $calc = new PluginCalc($args);
    $result = $calc->calc();

    return $result;
}

class PluginCalc
{
    public array $err;
    private string $formula;
    private array $options;

    /**
     * コンストラクタ
     *
     * @param array $args
     */
    public function __construct(array $args)
    {
        $this->err = [];
        $formula = array_pop($args);

        if ($formula === '') {
            $this->err = ['msg_usage'];
        } elseif ($this->is_valid($formula)) {
            $this->formula = $formula;
            $this->get_options($args);
        }

        // デフォルト設定
        $this->options['format'] ??= PLUGIN_CALC_FORMAT_AS_DEFAULT;
        $this->options['precision'] ??= $this->options['formant'] ? PLUGIN_CALC_DEFAULT_PRECISION : null;
    }

    /**
     * 計算
     *
     * @return string
     */
    public function calc(): string
    {
        if ($this->has_error()) return $this->show_msg();

        $format = $this->options['format'];
        $precision = $this->options['precision'];
        $result = '';

        try {
            $result = eval("return {$this->formula};");

            if ($precision !== null) $result = round($result, $precision);
            if ($format) $result = number_format($result, $precision);
        } catch (ParseError $e) {
            $this->err = ['err_invalid'];
            $result = $this->show_msg();
        }

        return $result;
    }

    /**
     * 計算式以外の文字がないか確認
     *
     * @param string $formula
     * @return boolean
     */
    private function is_valid(string $formula): bool
    {
        if (preg_match('/[^\s\d+\-*\/%=().]/', $formula)) {
            $this->err = ['err_invalid'];

            return false;
        }

        return true;
    }

    private function get_options(array $args): void
    {
        $args = array_map('trim', $args);

        foreach($args as $i => $arg) if ($arg === '') $args[$i] = null;

        // 小数点以下の桁数
        if (is_numeric($args[0])) $this->options['precision'] = $args[0];
        elseif ($args[0] !== null) $this->err = ['err_unknown' => $args[0]];

        // カンマ区切り
        if ($args[1] === 'false') $this->options['format'] = false;
        elseif ($args[1] === 'true') $this->options['format'] = true;
        elseif ($args[1] !== null) $this->err = ['err_unknown' => $args[1]];
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
        global $_calc_messages;

        $msg = '';

        if (array_values($this->err) === $this->err) {
            $msg = $_calc_messages[$this->err[0]];
        } else {
            foreach ($this->err as $key => $val) {
                $msg = $_calc_messages[$key];
                $msg = str_replace('%s', htmlsc($val), $msg);
            }
        }

        return "<p>$msg</p>";
    }
}