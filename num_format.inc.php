<?php
/**
 * 数字を千の位毎にグループ化してフォーマットするプラグイン
 *
 * @version 1.0.0
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * -- Updates --
 * 2024-09-08 v1.0.0 初版作成
 */

function plugin_num_format_init(): void
{
    $msg['_num_format_messages'] = [
        'msg_usage'            => '&amp;num_format([options]){&lt;value&gt;};',
        'err_unknown'          => '#num_format Error: Unknown argument. (%s)',
        'err_empty'            => '#num_format Error: Need to enter value.',
        'err_invalid'          => '#num_format Error: Invalid value. (%s)',
    ];
    set_plugin_messages($msg);
}

function plugin_num_format_inline(string ...$args): string
{
    $num_format = new PluginNumFormat($args);
    $result = $num_format->convert();

    return $result;
}

/**
 * メイン処理
 */
class PluginNumFormat
{
    private float $value;
    private array $options = [];
    private array $err = [];

    /**
     * コンストラクタ
     *
     * @param array $args
     */
    public function __construct(array $args)
    {
        $val_str = str_replace(',', '', array_pop($args));

        if ($val_str === '') {
            $this->err = ['err_empty'];
        } elseif (! is_numeric($val_str)) {
            $this->err = ['err_invalid' => $val_str];
        } else {
            $this->value = (float)$val_str;
            $this->parse_options($args);
        }
    }

    /**
     * 変換と出力
     *
     * @return string
     */
    public function convert(): string
    {
        if ($this->has_error()) return $this->show_msg();

        $string = number_format($this->value, $this->options['decimals'], $this->options['decimal_separator'], $this->options['thousands_separator']);

        return $string;
    }

    /**
     * オプションの判別
     *
     * @param array $args
     * @return void
     */
    public function parse_options(array $args): void
    {
        $is_valid = fn ($v) => is_numeric($v) && $v >= 0;

        foreach ($args as $arg) {
            [$key, $val] = array_map('trim', explode('=', $arg));

            if (($key === 'decimals' && $is_valid($val)) || (is_numeric($key) && $is_valid($key))) {
                // 小数点以下の桁数
                $this->options['decimals'] = $val !== null ? (int)$val : (int)$key;
            } elseif ($key === 'decimal_separator' || $key === 'thousands_separator') {
                // 区切り文字
                $this->options[$key] = htmlsc($val);
            } else {
                $this->err = ['err_unknown' => $arg];
            }
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
        global $_num_format_messages;

        $msg = '';

        if (array_values($this->err) === $this->err) {
            $msg = $_num_format_messages[$this->err[0]];
        } else {
            foreach ($this->err as $key => $val) {
                $msg = $_num_format_messages[$key];
                $msg = str_replace('%s', htmlsc($val), $msg);
            }
        }

        return "<p>$msg</p>";
    }
}