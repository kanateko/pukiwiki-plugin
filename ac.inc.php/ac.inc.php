<?php
/**
 * 折りたたみ可能な見出しを作成するプラグイン
 *
 * @version 1.2
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license http://www.gnu.org/licenses/gpl.ja.html GPL
 * -- Updates --
 * 2021-07-05 ヘッダーに見出しを指定する場合はオプションで明示するように変更
 * 2021-07-04 インライン型を追加
 * 2021-04-02 初版作成
 */

define('PLUGIN_AC_ALT_MESSAGE', '&#9652; クリック or タップで詳細を表示');

/**
 * ブロック型
 */
function plugin_ac_convert()
{
    $ac = new PluginAc;

    if (func_num_args() == 0) {
        return $ac->msg['usage'];
    }

    $args = func_get_args();

    // ヘッダーとコンテンツ
    if (func_num_args() > 1 && !preg_match('/^(h|open|alt)$/', $args[0])) {
        $header = convert_html(array_shift($args));
        $ac->set_header($header);
    }
    $contents = preg_replace("/\r|\r\n/", "\n", array_pop($args));
    $contents = convert_html($contents);

    // オプション判別
    if ($args) {
        foreach ($args as $arg) {
            $arg = htmlsc($arg);
            switch ($arg) {
                case 'h':
                    // no break
                case 'open':
                    // no break
                case 'alt':
                    $ac->set_options($arg);
                    break;
                default:
                    return $ac->msg['unknown'] . $arg;
            }
        }
    }

    // 本文とスクリプトを作成
    return $ac->build_accordion($contents);
}

/**
 * インライン型
 */
function plugin_ac_inline()
{
    $ac = new PluginAc;

    if (func_num_args() == 0) {
        return $ac->msg['usage'];
    }

    $args = func_get_args();

    // ヘッダーとコンテンツ
    if (func_num_args() > 1 && !preg_match('/^(h|open|alt)$/', $args[0])) {
        $header = convert_html(array_shift($args));
        $ac->set_header($header);
    }
    $contents = array_pop($args);

    if (empty($contents)) {
        // コンテンツが空の場合はエラー
        return $ac->msg['empty'];
    }

    // オプション判別
    if ($args) {
        foreach ($args as $arg) {
            $arg = htmlsc($arg);
            switch ($arg) {
                case 'h':
                    return $ac->msg['incorrect'] . $arg;
                case 'open':
                    // no break
                case 'alt':
                    $ac->set_options($arg);
                    break;
                default:
                    return $ac->msg['unknown'] . $arg;
            }
        }
    }

    // 本文とスクリプトを作成
    return $ac->build_accordion($contents);
}

/**
 * アコーディオン作成用クラス
 */
Class PluginAc
{
    public static $ac_counts = 0;

    // メッセージ
    public $msg = array(
        'usage'     => '#ac Usage:<br />
                        &lt;title or header&gt;<br />
                        #ac([open,alt]){{<br />
                        &lt;ontents&gt;<br />
                        }}<br />
                        or<br />
                        &ac(&lt;title&gt;){&lt;contents&gt;};',
        'unknown'   => '#ac Error: Unknown argument. -> ',
        'empty'     => '&ac; Error: The text area is empty.',
        'incorrect' => '$ac; Error: The option is not available in inline version. -> '
    );

    // オプション
    private $options = array (
        'class'   => array(
            'header'   =>    'plugin-ac-header',
            'contents' =>    'plugin-ac'
        ),
        'display' => 'none',
        'alt'     => '',
        'header'  => '...'
    );

    /**
     * ヘッダーの指定
     */
    public function set_header($header)
    {
        $header = preg_replace('/<\/?p>/', '', $header);
        $this->options['header'] = $header;
    }

    /**
     * オプションの判別
     */
    public function set_options($arg)
    {
        $class =& $this->options['class'];
        switch ($arg) {
            case 'h':
                $this->set_header('');
            case 'open':
                // 初期状態を開いた状態にする
                $class['header'] .= ' open';
                $this->options['display'] = 'block';
                break;
            case 'alt':
                // 代わりの文章を表示する
                $this->options['alt'] = '<div class="plugin-ac-altmsg" style="display:none">' . PLUGIN_AC_ALT_MESSAGE . '</div>';
        }
    }

    /**
     * アコーディオンを作成する
     */
    public function build_accordion($contents)
    {
        $header = $this->options['header'];
        $class = $this->options['class'];
        if (!empty($header)) {
            $header = '<div>' . $header . '</div>';
        }
        $id = 'ac-' . self::$ac_counts++;

        $html = <<<EOD
        $header
        <div class="{$class['contents']}" id="$id" style="display:{$this->options['display']}">
            $contents
        </div>
        {$this->options['alt']}
        <script>
            var cancelFlag = 0;
            $(function(){
                $("#$id").prev().addClass("{$class['header']}");
                $("#$id").next(".plugin-ac-altmsg").show();
            });
            $("#$id").prev().click(function(e){
                if(e.target !== e.currentTarget) return;
                if (cancelFlag == 0) {
                    cancelFlag = 1;
                    $(this).toggleClass("open");
                    $("#$id").slideToggle(500);
                    setTimeout(function(){
                        cancelFlag = 0;
                    },500);
                }
            });
        </script>
        EOD;

        return $html;
    }
}

?>
