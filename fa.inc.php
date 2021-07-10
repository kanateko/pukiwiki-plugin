<?php
/**
 * Font Awesomeのアイコンを表示するプラグイン
 *
 * v5.15.3フリー版 https://fontawesome.com/v5.15/icons?d=gallery&p=2&m=free
 *
 * @version 1.2
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license http://www.gnu.org/licenses/gpl.ja.html GPL
 * -- Update --
 * 2021-07-11 アニメーションやサイズ変更といったのFAのクラスを引数で追加する機能を追加
 *            text-shadowとbackgroundオプションを追加
 *            アイコン同士を重ねる機能を追加
 * 2021-07-10 初版作成
 */

// 固定で追加するクラス
define('FA_ADD_CLASS', ' fa-fw');

function plugin_fa_inline()
{
    // メッセージ
    $msg = array(
        'empty'  => '&amp;fa; Error: The text area is empty.',
        'option' => '&amp;fa; Error: Invalid option. -> ',
    );

    // オプション関連
    $options = array(
        'fa'         => array('xs', 'sm', 'lg', '2x', '3x', '5x', '7x', '10x', 'spin', 'pulse', 'border', 'inverse'),
        'rotate'     => array('90', '180', '270'),
        'flip'       => array('horizontal', 'vertical', 'both'),
        'pulled'     => array('left', 'right'),
        'stack'      => array('1', '2'),
        'icon_style' => array(
            'fas' => array('solid', 'fas', 's'),
            'far' => array('regular', 'far', 'r'),
            'fab' => array('brand', 'fab', 'b'),
        ),
        'style'      => array('color', 'text-shadow', 'background'),
    );
    $add = array(
        'class'      => '',
        'icon_style' => 'fas',
        'style'      => '',
    );
    $is_stack = false;

    $args = func_get_args();
    if (empty(end($args))) return $msg['empty'];

    $class = array_pop($args);
    $args = array_unique($args);

    // オプション判別
    if($args) {
        foreach($args as $arg) {
            $arg = htmlsc($arg);
            if (strpos($arg, '=') !== false) {
                // text style
                list($key, $val) = explode('=', $arg);
                if (in_array($key, $options['style'])) {
                    $add['style'] .= $key . ':' . $val . ';';
                } else {
                    return $msg['option'] . $arg;
                }
            } else if (in_array($arg, $options['fa'])) {
                // size, animation, border
                $add['class'] .= ' fa-' . $arg;
            } else if (in_array($arg, $options['rotate'])) {
                // rotate
                $add['class'] .= ' fa-rotate-' . $arg;
            } else if (in_array($arg, $options['flip'])) {
                // flip
                $add['class'] .= ' fa-flip-' . $arg;
            } else if (in_array($arg, $options['pulled'])) {
                // float
                $add['class'] .= ' fa-pulled-' . $arg;
            } else if (in_array($arg, $options['stack'])) {
                // stack option
                $add['class'] .= ' fa-stack-' . $arg . 'x';
            } else if (in_array($arg, list_icon_style($options['icon_style']))) {
                // icon style
                foreach ($options['icon_style'] as $fa => $fa_style) {
                    if (in_array($arg, $fa_style)) {
                        $add['icon_style'] = $fa;
                    }
                }
            } else if ($arg == 'stack') {
                $is_stack = true;
            } else {
                return $msg['option'] . $arg;
            }
        }
        if (! empty($add['style'])) {
            $add['style'] = ' style="' . $add['style'] . '"';
        }
    }

    if (! $is_stack && ! preg_match('/^fa[srb]? /', $class)) {
        // アイコンのスタイルを指定 (デフォルト: solid)
        $class = $add['icon_style'] . ' fa-' . $class;
    }

    if ($is_stack) {
        // 2つのアイコンを重ねて表示
        return stack_icons($class, $add['class'], $add['style']);
    }

    return '<i class="' . $class . $add['class'] . FA_ADD_CLASS .  '"' . $add['style'] . '></i>';
}

/**
 * アイコンスタイルの検索用リストの作成
 */
function list_icon_style($styles) {
    $list_style = '';
    foreach ($styles as $style) {
        $list_style .= empty($list_style) ? implode(',', $style) : ',' . implode(',', $style);
    }
    $list_style = explode(',', $list_style);
    return $list_style;
}

/**
 * 2つのアイコンを重ねて表示
 */
function stack_icons($icons, $span_class, $span_style) {
    $html = <<<EOD
    <span class='fa-stack$span_class'$span_style>
        $icons
    </span>
    EOD;
    return $html;
}

?>
