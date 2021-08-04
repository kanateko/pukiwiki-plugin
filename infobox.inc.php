<?php
/**
 * テンプレートを読み込んでインフォボックスを設置するプラグイン
 *
 * @version 0.4
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license http://www.gnu.org/licenses/gpl.ja.html GPL
 * -- Update --
 * 2021-08-04 テンプレートの読み込みがループする場合はエラーを表示する機能を追加
 *            テンプレートページの凍結が必要かどうかを設定できる機能を追加
 *            指定した文字列を含む行をinclude時に除外する機能を追加
 * 2021-08-03 初版作成
 */

// テンプレートページ
define('INFOBOX_TEMPLATE_LOCATION', ':config/plugin/infobox');
// 使用するのにテンプレートページが凍結されてる必要があるかどうか
define('INFOBOX_NEED_TO_FREEZE', false);

function plugin_infobox_convert()
{
    $args = func_get_args();
    if (func_num_args() > 0 && strpos(end($args), "\r") !== false) {
        // 引数が1個以上で最後の引数がマルチラインならインフォボックスを作成
        $infobox = new Infobox($args);
    } else {
        return Infobox::$msg['usage'];
    }

    return $infobox->convert_infobox();
}

/**
 * インフォボックスの作成
 */
class Infobox {
    public static $msg = array(
        'usage'    => '#infobox([template][,nozoom][,class=xxx][,except=xxx]){{<br>&lt;key&gt; = xxx<br>...<br>}}<br>',
        'notfound' => '#infobox Error: The template you specified does not exist. -> ',
        'loop'     => '#infobox Error: The template you specified is already included. -> ',
        'self'     => '#infobox Error: It is not allowed that loading self as a template.',
        'freeze'   => '#infobox Error: According to the setting, you should freeze templates before use this plugin.',
    );
    private $options = array(
        'class'  => '',
        'except' => '',
    );
    private $lines;
    private $template;

    public function __construct($args)
    {
        $this->lines = array_pop($args);
        // オプション判別
        foreach($args as $i => $arg) {
            $arg = htmlsc($arg);
            if ($arg == 'nozoom') {
                $this->options['class'] .= ' ' . $arg;
                unset($args[$i]);
            } else if (strpos($arg, 'class=') !== false) {
                $this->options['class'] .= ' ' . explode('=', $arg, 2)[1];
                unset($args[$i]);
            } else if (strpos($arg, 'except=') !== false) {
                $this->options['except'] = $this->convert_regexp(explode('=', $arg, 2)[1]);
                unset($args[$i]);
            }
        }
        // テンプレートページの指定
        $this->template = new IncludeTemplate(array_shift($args));
    }

    /**
     * テンプレートの置き換え
     */
    public function convert_infobox()
    {
        $add_class = $this->options['class'];
        $keywords = $this->get_keywords($this->lines);
        $template = $this->template->get_template();
        if (! is_array($template)) {
            // 配列以外ならエラーとして出力
            return $template;
        }

        // キーを値に置き換え
        foreach($keywords as $key => $val) {
            $template = str_replace('{{{' . $key . '}}}', $val, $template);
        }

        // 不要部分の削除
        $infobox = $this->trim_disused($template);

        $html = <<<EOD
        <div class="infobox$add_class">
            $infobox
        </div>
        EOD;

        return $html;
    }

    /**
     * マルチライン部分をキーと値に分離して配列に格納する
     */
    private function get_keywords($lines)
    {
        $keywords = array();
        $lines = explode("\r", $lines);

        foreach ($lines as $str) {
            list($key, $val) = explode('=', $str, 2);
            $keywords[$key] = $val;
        }

        return $keywords;

    }

    /**
     * 除外用の正規表現を組み立てる
     */
    private function convert_regexp($str)
    {
        $str = str_replace('/', '\/', $str);
        if (preg_match('/^\^/', $str)) {
            return '/^(' . preg_replace('/^\^/', '', $str) . ')[\s\S]*$/';
        } else if (preg_match('/[^\\]\$$/', $str)) {
            return '/^[\s\S]*(' . preg_replace('/\$$/', '', $str) . ')$/';
        } else {
            return '/^[\s\S]*(' . $str . ')[\s\S]*$/';
        }
    }

    /**
     * テンプレートの不要な部分を削除
     */
    private function trim_disused($infobox)
    {
        $infobox = preg_replace("/^[^{]*{{{[^}]*}}}[^}]*$/", '', $infobox);
        $infobox = preg_replace('/^#author[\s\S]*$/', '', $infobox);
        if ($regexp = $this->options['except']) {
            $infobox = preg_replace($regexp, '', $infobox);
        }
        $infobox = implode($infobox);
        $infobox = preg_replace('/==noinclude==[\s\S]*?==\/noinclude==\s/', '', $infobox);
        $infobox = convert_html($infobox);

        return $infobox;
    }
}

/**
 * テンプレートページの読み込み
 */
class IncludeTemplate
{
    private static $included = array();
    private $template_page;

    public function __construct($template)
    {
        $this->template_page = $template ? INFOBOX_TEMPLATE_LOCATION . '/' . $template : INFOBOX_TEMPLATE_LOCATION;
    }

    /**
     * テンプレートページのソースを取得
     */
    public function get_template()
    {
        global $vars;
        $page = $this->template_page;

        // エラーチェック
        if (! is_page($page)) {
            // テンプレートが存在しなければエラー
            return Infobox::$msg['notfound'] . htmlsc($page);
        } else if (INFOBOX_NEED_TO_FREEZE && ! is_freeze($page)) {
            // テンプレートの凍結が必要な設定で凍結されてなければエラー
            return Infobox::$msg['freeze'];
        } else if (isset(self::$included[$page])) {
            // テンプレートが既に1回読み込まれていればエラー
            return Infobox::$msg['loop'] . htmlsc($page);
        } else if ($page == $vars['page']) {
            // テンプレートページ内で自分自身が呼び出されていればエラー
            return Infobox::$msg['self'];
        } else {
            self::$included[$page] = true;
            $template = get_source($page);
            return $template;
        }
    }
}
?>
