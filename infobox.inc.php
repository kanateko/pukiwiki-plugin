<?php
/**
 * テンプレートを読み込んでインフォボックスを設置するプラグイン
 *
 * @version 0.1
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license http://www.gnu.org/licenses/gpl.ja.html GPL
 * -- Update --
 * 2021-08-03 初版作成
 */

// テンプレートページ
define('INFOBOX_TEMPLATE_LOCATION', ':config/plugin/infobox');

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
        'usage'    => '#infobox([template][,nozoom][,class=xxx]){{<br>&lt;key&gt; = xxx<br>...<br>}}<br>',
        'notfound' => '#infobox Error: The template you specified does not exist. -> ',
    );

    private $options = array('nozoom');
    private $add_class;
    private $source;
    private $template;

    public function __construct($args)
    {
        $this->source = array_pop($args);
        // オプション判別
        foreach($args as $arg) {
            $arg = htmlsc($arg);
            if (in_array($arg, $this->options)) {
                $this->add_class .= ' ' . $arg;
                unset($args[$arg]);
            } else if (strpos($arg, 'class=') !== false) {
                $this->add_class .= ' ' . explode('=', $arg)[1];
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
        $add_class = $this->add_class;
        $keywords = $this->get_keywords($this->source);
        $template = $this->template->get_template();
        if (! is_array($template)) {
            // 配列以外ならエラーとして出力
            return $template;
        }

        // キーを値に置き換え
        $template = implode($template);
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
    private function get_keywords($source)
    {
        $keywords = array();
        $source = explode("\r", $source);

        foreach ($source as $str) {
            list($key, $val) = explode('=', $str, 2);
            $keywords[$key] = $val;
        }

        return $keywords;

    }

    /**
     * テンプレートの不要な部分を削除
     */
    private function trim_disused($infobox)
    {
        $infobox = preg_replace("/[^{\n]*{{{[^}\n]+}}}[^}\n]*\n/", '', $infobox);
        $infobox = preg_replace('/#author\(.+?\)/', '', $infobox);
        $infobox = preg_replace('/==noinclude==[\s\S]*?==\/noinclude==/', '', $infobox);
        $infobox = convert_html($infobox);

        return $infobox;
    }
}

/**
 * テンプレートページの読み込み
 */
class IncludeTemplate
{
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
        $page = $this->template_page;
        if (! is_page($page)) {
            return Infobox::$msg['notfound'] . htmlsc($this->template_page);
        }
        $template = get_source($page);

        return $template;
    }
}
?>
