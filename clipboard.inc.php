<?php
/**
* 指定したテキストをワンクリックでコピーさせるプラグイン
*
* @version 1.0.0
* @author kanateko
* @link https://jpngamerswiki.com/?f51cd63681
* @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
* -- Updates --
* 2022-08-14 v1.0.0 初版作成
*/

// FontAwesomeアイコンを使用する
define('PLUGIN_CLIPBOARD_USE_FA', true);

function plugin_clipboard_init()
{
    $msg['_clipboard_messages'] = [
        'msg_copied'      => 'コピーしました',
        'label_clipboard' => '📋'
    ];
    set_plugin_messages($msg);
}

function plugin_clipboard_inline(...$args)
{
    global $_clipboard_messages;
    static $loaded = false;

    $text = array_pop($args);
    if (empty($text)) return;

    if (PLUGIN_CLIPBOARD_USE_FA) $icon = '<i class="clipboard-button fas fa-copy"style="margin-left:4px"></i>';
    else $icon = '<i class="clipboard-button">' . $_clipboard_messages['label_clipboard'] . '</i>';

    $html = '<span class="plugin-clipboard"><span class="clipboard-target">' . $text . '</span>' . $icon . '</span>' . "\n";
    if (! $loaded) {
        $loaded = true;
        $html .= preg_replace('/\s{2,}/', '', <<<EOD
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const copyBtn = document.querySelectorAll('.clipboard-button');
                for (const button of copyBtn) {
                    const target = button.previousSibling;
                    const prevText = target.innerText;
                    button.addEventListener('click', () => {
                        target.style.pointerEvents = 'none';
                        navigator.clipboard.writeText(prevText);
                        target.innerText = '{$_clipboard_messages['msg_copied']}';
                        setTimeout( () => {
                            target.innerText = prevText;
                            target.style.pointerEvents = 'auto';
                        }, 1000);
                    });
                }
            });
        </script>
        EOD);
    }

    return $html;
}