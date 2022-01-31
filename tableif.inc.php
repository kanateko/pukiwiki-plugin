<?php
/**
* 指定した文字列に一致するセルの書式を変更するプラグイン
*
* @version 0.1
* @author kanateko
* @link https://jpngamerswiki.com/?f51cd63681
* @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
* -- Update --
* 2021-10-18 v0.1 初版作成
*/

/**
 * ブロック型
 */
function plugin_tableif_convert()
{
    $msg = array (
        'usage'  => '#tableif(regexp=format,...){{tables}}',
        'format' => '#tableif Error: The argument "%ex%" is not defined.',
    );

    $args = func_get_args();
    if (func_num_args() < 2 || empty(end($args))) return $msg['usage'];
    $s_table = preg_replace("/\r|\r\n/", "\n", array_pop($args));
    $conds = args_to_conditions($args);
    if (! is_array($conds)) return str_replace('%ex%', $conds, $msg['format']);
    return insert_format($conds, $s_table);
}

/**
 * 引数を条件と書式に分解
 */
function args_to_conditions($args)
{
    $conds = array();
    foreach ($args as $arg) {
        if (preg_match('/(.+)=(.+)/', $arg, $match)) {
            $conds[$match[1]] = $match[2];
        } else {
            $conds = htmlsc($arg);
            break;
        }
    }
    return $conds;
}

/**
 * 条件に合うセルに書式を追加して出力
 */
function insert_format($conds, $s_table)
{
    $lines = explode("\n", $s_table);
    foreach ($conds as $regexp => $format) {
        $regexp = '/' . str_replace('/', '\/',$regexp) . '/';
        foreach ($lines as $i => $line) {
            if (preg_match('/^\|(.+)\|$/', $line, $match)) {
                $cells = explode('|', $match[1]);
                foreach($cells as $j => $cell) {
                    if (preg_match($regexp, $cell)) {
                        $cells[$j] = $format . ':' . $cell;
                    }
                }
                $lines[$i] = '|' . implode('|', $cells) . '|';
            } else {
                continue;
            }
        }
    }
    $lines = implode("\n", $lines);
    return convert_html($lines);
}
