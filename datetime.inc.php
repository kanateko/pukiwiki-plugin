<?php
/**
 * 指定したフォーマットで日付や時間を表示するプラグイン
 *
 * @version 1.0.0
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * -- Updates --
 * 2025-08-06 v1.0.0 初版作成
 */

/**
 * インライン型
 *
 * @param string ...$args
 * @return string
 */
function plugin_datetime_inline(string ...$args): string
{
    array_pop($args);

    if (empty($args)) return format_date(UTIME);

    $format = match($args[0]) {
        'DATE_ATOM'    => DATE_ATOM,
        'DATE_COOKIE'  => DATE_COOKIE,
        'DATE_ISO8601' => DATE_ISO8601,
        'DATE_RFC822'  => DATE_RFC822,
        'DATE_RFC850'  => DATE_RFC850,
        'DATE_RFC1036' => DATE_RFC1036,
        'DATE_RFC1123' => DATE_RFC1123,
        'DATE_RFC7231' => DATE_RFC7231,
        'DATE_RFC2822' => DATE_RFC2822,
        'DATE_RFC3339' => DATE_RFC3339,
        'DATE_RSS'     => DATE_RSS,
        'DATE_W3C'     => DATE_W3C,
        default        => htmlsc($args[0])
    };

    $time = $args[1] ? (int)$args[1] : UTIME;

    return date($format, $time);
}