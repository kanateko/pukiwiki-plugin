<?php
/**
* キャプション付きの図表を表示するプラグイン
*
* @version 1.5
* @author kanateko
* @link https://jpngamerswiki.com/?f51cd63681
* @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
* -- Update --
* 2025-08-28 v1.5 AVIF画像に対応
* 2022-05-20 v1.4 定数のフォーマットを修正
*                 エラー関連の処理を変更
* 2021-09-30 v1.3 クラスを追加する機能を追加
*                 キャプションでPukiWiki構文が使えない問題を修正
*            v1.2 フローの有無を変更するオプションとプラグイン設定を追加
* 2021-09-29 v1.1 インライン型を追加
*            v1.0 初版作成
*/

// 許可するmime-type
define('FIG_FORMAT_REGEXP', '/image\/(jpeg|png|gif|webp|avif)/i');

// デフォルトの表示位置 (left, center, right)
define('FIG_DEFAULT_POSITION', 'right');

// デフォルトテーマ (dark or light)
define('FIG_DEFAULT_THEME', 'dark');

// デフォルトの縁取り有無 (wrap or nowrap)
define('FIG_DEFAULT_WRAP', 'wrap');

// デフォルトのリンク有無 (link or nolink)
define('FIG_DEFAULT_LINK', 'link');

// デフォルトのフロー有無 (float or nofloat)
define('FIG_DEFAULT_FLOAT', 'float');

// キャプションのスタイル
// bottom: 画像の下に表示
// overlay: オーバーレイ
// -left, -center, -right: 文字のアラインメント
define('FIG_CAPTION_STYLE', 'bottom-center');

function plugin_fig_init()
{
    $msg['_fig_messages'] = [
        'msg_usage'  => '#fig(filename[,options])',
        'err_exist'  => '#fig Error: The file does not exist.',
        'err_format' => '#fig Error: The file format is not supported.'
    ];
    set_plugin_messages($msg);
}

function plugin_fig_convert()
{
    return plugin_fig(func_get_args(), func_num_args(), 'convert');
}

function plugin_fig_inline()
{
    return plugin_fig(func_get_args(), func_num_args(), 'inline');
}

function plugin_fig($args, $num, $type)
{
    global $_fig_messages;

    if ($num < 1) {
        return $_fig_messages['msg_usage'];
    }

    $fig = new PluginFigure(array_shift($args));

    // ファイルを表示可能かチェック
    if (! $fig->get_file_existence()) {
        return $_fig_messages['err_exist'];
    } else if (! $fig->get_file_format()) {
        return $_fig_messages['err_format'];
    }

    // オプション判別
    $fig->init_options();
    foreach ($args as $arg) {
        $fig->set_option($arg);
    }

    // HTMLに変換して出力
    switch ($type) {
        case 'convert':
            return $fig->convert();
            break;
        case 'inline':
            return $fig->inline();
    }
}

Class PluginFigure
{
    private $source;
    private $page;
    private $image;
    private $file;
    private $imginfo;
    private $options;

    public function __construct($src)
    {
        $src = htmlsc($src);
        $this->source = $src;
        $this->split_source();
    }

    /**
     * 第一引数からページ名やファイル名などを取得
     *
     * @return void
     */
    private function split_source()
    {
        global $vars;
        $src = $this->source;

        if (strpos($src, './') !== false) {
            // 相対パスを絶対パスに変換
            $src = get_fullname($src, $vars['page']);
        }

        if (strpos($src, '/') !== false) {
            // 他ページに添付された画像
            preg_match('/(.+)\/([^\/]+)/', $src, $match);
            $this->page = $match[1];
            $this->image = $match[2];
        } else {
            // 同ページに添付された画像
            $this->page = $vars['page'];
            $this->image = $src;
        }

        $this->file = UPLOAD_DIR . encode($this->page) . '_' . encode($this->image);
        $this->imginfo = getimagesize($this->file);
    }

    /**
     * ファイルが存在しているかチェック
     *
     * @return bool
     */
    public function get_file_existence() {
        return file_exists($this->file);
    }

    /**
     * ファイルが許可されたフォーマットかどうかをチェック
     *
     * @return bool
     */
    public function get_file_format() {
        return preg_match(FIG_FORMAT_REGEXP, $this->imginfo['mime']);
    }

    /**
     * 各オプションの初期値を設定
     *
     * @return void
     */
    public function init_options()
    {
        $this->options = array(
            'cap'      => '',
            'class'    => '',
            'width'    => $this->imginfo[0],
            'height'   => $this->imginfo[1],
            'alt'      => $this->image,
            'title'    => $this->image,
            'float'    => FIG_DEFAULT_FLOAT,
            'link'     => FIG_DEFAULT_LINK,
            'wrap'     => FIG_DEFAULT_WRAP,
            'position' => FIG_DEFAULT_POSITION,
            'theme'    => FIG_DEFAULT_THEME,
            'style'    => FIG_CAPTION_STYLE,
        );
    }

    /**
     * オプションを判別、格納
     *
     * @return void
     */
    public function set_option($arg)
    {
        $opt =& $this->options;
        $width = $this->imginfo[0];
        $height = $this->imginfo[1];

        if (! empty($arg)) {
            if (preg_match('/[^=]+=.+/', $arg) && strpos($arg, "~") !== 0) {
                // キャプション, スタイル, その他
                list($key, $val) = explode('=', $arg, 2);
                if ($key != 'cap') {
                    $val = htmlsc($val);
                }
                $opt[$key] = $val;
            } else if (preg_match('/(\d+)x(\d+)/', $arg, $match)) {
                // サイズ指定 (幅x高さ)
                if ($match[0] == '0x0') {
                    $opt['width'] = $width;
                    $opt['height'] = $height;
                } else {
                    if ($match[1] != 0) {
                        $opt['width'] = $match[1];
                    } else {
                        $opt['width'] = floor($match[2] / $height * $width);
                    }
                    if ($match[2] != 0) {
                        $opt['height'] = $match[2];
                    } else {
                        $opt['height'] = floor($match[1] / $width * $height);
                    }
                }
            } else if (preg_match('/^(\d+)%$/', $arg, $match)) {
                // サイズ指定 (%)
                $opt['width'] = $match[1] / 100 * $width;
                $opt['height'] = $match[1] / 100 * $height;
            } else if (preg_match('/^(\d+)px$/', $arg, $match)) {
                // サイズ指定 (px)
                $opt['width'] = $match[1];
                $opt['height'] = floor($match[1] / $width * $height);
            } else if (preg_match('/^(left|center|right)$/', $arg)) {
                // 表示位置
                $opt['position'] = $arg;
            } else if (preg_match('/^(dark|light)$/', $arg)) {
                // テーマ
                $opt['theme'] = $arg;
            } else if (preg_match('/^(?:no)?(link|wrap|float)$/', $arg, $match)) {
                // リンク, 縁取り, フロート
                $opt[$match[1]] = $arg;
            } else if (preg_match('/^(bottom|overlay)-(left|center|right)$/', $arg)) {
                // キャプションのスタイル
                $opt['style'] = $arg;
            } else {
                // alt, title
                $opt['alt'] = $opt['title'] = preg_replace('/^~/', '', htmlsc($arg));
            }
        }
    }

    /**
     * ブロック型のHTMLに変換
     *
     * @return string
     */
    public function convert()
    {
        $opt = $this->options;
        $img_attr = $this->get_image_attr();
        $href = get_base_uri() . '?plugin=attach&amp;refer=' . rawurlencode($this->page)
        . '&amp;openfile=' . rawurlencode($this->image);
        $class = $opt['class'] ? ' ' . $opt['class'] : '';

        // 画像部分
        $img = '<img loading="lazy" class="figure-image" ' . $img_attr . '>';
        if ($opt['link'] == 'link') {
            $img = '<a class="figure-link" href="' . $href . '">' . $img . '</a>';
        }

        // キャプション
        $cap = '';
        if (! empty($opt['cap'])) {
            $cap = convert_html($opt['cap']);
            $cap = str_replace('p>', 'figcaption>', $cap);
        }

        // data属性
        $data = '';
        foreach ($opt as $key => $val) {
            if (preg_match('/float|position|theme|wrap|style/', $key)) {
                $data .= ' data-' . $key . '="' . $val . '"';
            }
        }

        // figure要素の幅
        $size = 'width:' . $opt['width'] . 'px;';

        $html = <<<EOD
        <figure class="plugin-figure$class" style="$size"$data>
            $img
            $cap
        </figure>
        EOD;

        return $html;
    }

    /**
     * インライン型のHTMLに変換
     *
     * @return string
     */
    public function inline()
    {
        $opt = $this->options;
        $img_attr = $this->get_image_attr();
        $href = get_base_uri() . '?plugin=attach&amp;refer=' . rawurlencode($this->page)
        . '&amp;openfile=' . rawurlencode($this->image);
        $cap = $opt['cap'] ? ' data-cap="' . htmlsc($opt['cap']) . '"' : '';
        $class = $opt['class'] ? ' ' . $opt['class'] : '';

        // 画像部分
        $img = '<img loading="lazy" class="figure-image" ' . $img_attr . '>';
        if ($opt['link'] == 'link') {
            $img = '<a class="figure-link" href="' . $href . '">' . $img . '</a>';
        }

        $html = <<<EOD
        <span class="plugin-figure-inline$class"$cap>
            $img
        </span>
        EOD;

        return $html;
    }

    /**
     * imgタグの属性をオプションから作成
     *
     * @return string
     */
    private function get_image_attr()
    {
        $opt = $this->options;
        $img_src = get_base_uri() . '?plugin=ref&amp;page=' . rawurlencode($this->page)
         . '&amp;src=' . rawurlencode($this->image);

        $attrs = array('alt', 'title', 'width', 'height');
        foreach ($attrs as $attr) {
            if (empty($opt[$attr])) {
                $$attr = '';
            } else {
                $$attr = ' ' . $attr . '="' . $opt[$attr] . '"';
            }
        }

        return 'src="' . $img_src . '"' . $alt . $title . $width . $height;
    }
}
