<?php
/**
 * ネタバレ防止用プラグイン
 *
 * @version 1.1.0
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * -- Updates --
 * 2025-09-10 1.1.0 JavaScriptで制御を行うよう変更
 *                  ぼかしの上に表示するテキストを変更する機能を追加
 *                  ぼかし強度を変更する機能を追加
 *                  ぼかす範囲のコントラストを変更する機能を追加
 *                  テキストの色とサイズを変更する機能を追加
 *                  任意のクラスを追加する機能を追加
 * 2024-02-14 1.0.1 hover指定時の構造を改善
 * 2024-02-07 1.0.0 初版作成
 */

// css
define('PLUGIN_SPOILER_CSS', SKIN_DIR . 'css/spoiler.min.css');
// デフォルトの解除設定：hover / click
define('PLUGIN_SPOILER_DEFAULT_MODE', 'click');
// デフォルトの表示テキスト
define('PLUGIN_SPOILER_DEFAULT_TEXT', 'ネタバレ');
// デフォルトのぼかし強度
define('PLUGIN_SPOILER_DEFAULT_BLUR', '4px');
// デフォルトの背景コントラスト
define('PLUGIN_SPOILER_DEFAULT_CONTRAST', '.9');

Class PluginSpoiler
{
    private array $err;
    private array $options;
    private string $type;
    private string $body;
    private static int $id;

    /**
     * コンストラクタ
     *
     * @param array $args プラグインの引数
     */
    public function __construct(array $args, string $type)
    {
        $this->err = [];
        self::$id ??= 0;

        if (count($args) < 1) {
            $this->err = ['msg_empty'];
        } else {
            // オプションと本文
            $this->options = $this->parse_options($args);
            $this->type = $type;
        }
    }

    /**
     * HTMLに変換
     *
     * @return string
     */
    public function convert(): string
    {
        if ($this->has_error()) return $this->msg(...$this->err);

        $body = $this->type === 'inline' ? $this->body : convert_html($this->body);
        $text = $this->options['text'];
        $mode = $this->options['mode'];
        $style = $this->style();
        $class = $this->options['class'] ?: '';
        $tag = $this->type === 'inline' ? 'span' : 'div';
        $id = self::$id++;
        $script = $id !== 1 ? '' : <<<EOD
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (typeof spoilers === 'undefined') {
                    const spoilers = document.querySelectorAll('.plugin-spoiler[data-mode=click]');

                    for (const spoiler of spoilers) {
                        spoiler.addEventListener('click', () => {
                            spoiler.classList.add('open');
                        });
                    }
                }
            });
        </script>
        EOD;

        $html = "<$tag class=\"plugin-spoiler$class\" style=\"$style\" data-mode=\"$mode\" data-text=\"$text\">$body</$tag>$script";

        return $html;
    }

    /**
     * styleの構築
     *
     * @return string
     */
    private function style(): string
    {
        $style = '';

        foreach ($this->options['style'] as $key => $val) {
            $style .= "--spoiler-$key:$val;";
        }

        return $style;
    }

    /**
     * 引数の判別
     *
     * @param array $args 引数
     * @return array $options オプションの配列
     */
    private function parse_options(array $args): array
    {
        // 本文
        $options = [];
        $body = str_replace(["\r", "\r\n"], "\n", array_pop($args));

        if (empty($body)) $this->err = ['err_empty'];
        else $this->body = $body;

        // 引数の判別
        foreach($args as $arg) {
            $arg = htmlsc($arg);
            [$key, $val] = array_map('trim', explode('=', $arg, 2));

            if ($val !== null) {
                if ($key === 'blur' && is_numeric($val)) {
                    // ぼかしの強さ
                    $options['style']['blur'] = $val . 'px';
                } elseif ($key === 'contrast' && is_numeric($val)) {
                    // コントラスト
                    $options['style']['contrast'] = $val;
                } elseif ($key === 'size' && is_numeric($val)) {
                    // テキストのサイズ
                    $options['style']['text-size'] = $val . 'px';
                } elseif ($key === 'color') {
                    // テキストの色
                    $options['style']['text-color'] = str_starts_with($val, '--') ? "var($val)" : $val;
                } elseif ($key === 'text') {
                    // 表示するテキスト
                    $options['text'] = $val;
                } elseif ($key === 'class') {
                    // 追加のクラス
                    $options['class'] = ' ' . $val;
                } else {
                    // 不明な引数
                    $this->err = ['err_unknown', $arg];
                }
            } else {
                if ($key === 'hover' || $key === 'click') {
                    // 解除方式
                    $options['mode'] = $key;
                } elseif ($key === 'noerror') {
                    // エラー非表示
                    $options['noerror'] = true;
                } else {
                    // 表示するテキスト
                    $options['text'] = $key;
                }
            }
        }

        // デフォルト値の設定
        $options['mode'] ??= PLUGIN_SPOILER_DEFAULT_MODE;
        $options['text'] ??= PLUGIN_SPOILER_DEFAULT_TEXT;
        $options['style']['blur'] ??= PLUGIN_SPOILER_DEFAULT_BLUR;
        $options['style']['contrast'] ??= PLUGIN_SPOILER_DEFAULT_CONTRAST;
        $options['style']['text-size'] ??= 'inherit';
        $options['style']['text-color'] ??= 'inherit';

        return $options;
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
     * @param string $key エラーメッセージのキー
     * @param string ...$replaces 置き換える文字列
     * @return string
     */
    private function msg(string $key, string ...$replaces): string
    {
        global $_spoiler_messages;

        $msg = '';
        if ($this->options['noerror'] === true) return $msg;

        $msg = $_spoiler_messages[$key] ?? '';
        $num_replaces = count($replaces);

        if ($replaces !== null) {
            for ($i = 0; $i < $num_replaces; $i++) {
                $replace = $replaces[$i];
                $msg = str_replace('$' . $i, $replace, $msg);
            }
        }

        return $msg;
    }
}

/**
 * 初期化
 *
 * @return void
 */
function plugin_spoiler_init()
{
    global $head_tags;

    $msg['_spoiler_messages'] = [
        'err_empty' => '',
        'err_unknown' => '#spoiler Error: Unknown argument ($0)'
    ];
    set_plugin_messages($msg);

    $head_tags[] = '<link rel="stylesheet" href="' . PLUGIN_SPOILER_CSS . '?t=' . filemtime(PLUGIN_SPOILER_CSS) . '">';
}

/**
 * インライン型
 *
 * @param string ...$args
 * @return string
 */
function plugin_spoiler_inline(string ...$args): string
{
    $spoiler = new PluginSpoiler($args, 'inline');

    return $spoiler->convert();
}

/**
 * ブロック型
 *
 * @param string ...$args
 * @return string
 */
function plugin_spoiler_convert(string ...$args): string
{
    $spoiler = new PluginSpoiler($args, 'block');

    return $spoiler->convert();
}