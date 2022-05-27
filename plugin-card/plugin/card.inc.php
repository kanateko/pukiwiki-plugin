<?php
/**
 * 内部リンクをブログカード風に表示するプラグイン
 *
 * @version 3.0
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * -- Updates --
 * 2022-05-27 v3.0 コードを整理して全体的に作り直し
 *                 1.5.4のURLカスタマイズに対応
 *                 サムネイル画像やスニペット、見出しの取得方法を変更
 *                 プラグインの設定項目 (定数) を大幅に変更・追加
 *                 デフォルトのサムネイル保存場所を変更
 *                 見出し表示の指定方法を変更
 *                 カードの配置方法を変更するオプションを追加
 *                 スニペットに複数の見出しを表示する機能を追加
 *                 日付やスニペットを非表示にするオプションを追加
 *                 クラスやスタイルを指定するオプションを追加
 *                 各ページの情報をキャッシュする機能を追加
 *                 キャッシュを管理する機能を追加 (一覧・内容の詳細表示・削除)
 * 2021-10-04 v2.3 figプラグインに対応
 * 2021-08-23 v2.2 ページ名からファイル名への変換で使用する関数を変更 (strtoupper, bix2hex -> encode)
 *                 キャッシュを生成しない場合の階層化されたページにある画像の取得方法を修正
 *                 キャッシュを生成する場合、ページにinfoboxプラグインが使われていた場合の対応を強化
 *                 キャッシュ保存時に拡張子が正常に付与されない問題を修正
 * 2021-08-04 v2.1 infoboxプラグインとの兼ね合いで一部正規表現を変更・追加
 * 2021-07-28 v2.0 定数を整理、よりわかりやすい名前に変更
 *                 カード表示エリアの幅を固定化。幅はCSSで制御するように
 *                 上記に伴って幅の固定化をON/OFFするオプションを廃止
 *                 カードを縦長表示するカラム数の閾値の設定を追加
 *                 各カードの幅をCSSのgridで制御するように変更
 *                 指定した見出しをスニペットとして表示する機能を追加
 * 2021-07-26 v1.5 webpに対応
 *                 他のページの画像を呼び出している場合のサムネイル取得処理を修正
 * 2021-06-29 v1.4 プラグインの呼び出し毎に個別のIDを割り振る機能を追加
 * 2021-06-20 v1.3 画像が縦長の場合はサムネイルの切り抜きを画像の上端に合わせるよう変更
 *                 存在しないページが含まれている場合にエラーを出すかどうかを設定できるように変更
 *                 スニペット作成時の正規表現を修正
 * 2021-04-07 v1.2 ベースネーム表示機能を追加
 *                 スニペット作成時の正規表現を修正
 *                 更新日アイコンの表示に関するバグを修正
 * 2021-04-04 v1.1 カラム数指定時に確実にintを取得できるよう修正
 *                 カードの高さをプラグイン側で調整するよう変更
 *                 カラム数2以下の場合はスマホでも横長のカードになるよう変更 (cssの変更のみ)
 * 2021-04-01 v1.0 短縮URLライブラリ未導入でも動くよう修正
 *                 エイリアスに対応
 *                 キャッシュ更新機能追加
 *                 未改造状態のPukiWiki向けにいくつかの設定を追加
 * 2021-03-31 v0.6 カラム数指定機能追加
 *                 サムネイルキャッシュ機能追加
 * 2021-03-30 v0.2 初版作成
 */

// デフォルトのカラム数
define('PLUGIN_CARD_DEFAULT_COLUMN', 1);
// レイアウト切り替えるカラム数の閾値
define('PLUGIN_CARD_CHANGE_LAYOUT_THRESHHOLD', 3);
// スニペットを非表示にするカラム数の閾値
define('PLUGIN_CARD_HIDE_SNIPPET_THRESHOLD', 4);
// コンテナの幅をデフォルトで設定する null = 幅を固定しない
define('PLUGIN_CARD_CONTAINER_WIDTH', '768px');
// 各ページの情報をキャッシュする
define('PLUGIN_CARD_USE_CACHE', true);
// キャッシュを読みやすい形で保存する
define('PLUGIN_CARD_CACHE_PRETTY', false);
// キャッシュのディレクトリ
define('PLUGIN_CARD_CACHE_DIR', CACHE_DIR . 'card/');
// ページごとにサムネイル画像を作成して保存する
define('PLUGIN_CARD_MAKE_THUMBNAIL', true);
// サムネイル画像のディレクトリ
define('PLUGIN_CARD_THUMB_DIR', CACHE_DIR . 'card/thumb/');
// デフォルトのサムネイル画像
define('PLUGIN_CARD_THUMB_DEFAULT', IMAGE_DIR . 'eyecatch.jpg');
// デフォルトのサムネイル画像を使用する場合、
// ページごとのサムネイルを作成せずに直接使用する
define('PLUGIN_CARD_THUMB_DEF_DIRECT', false);
// サムネイル画像の幅の最大値
define('PLUGIN_CARD_THUMB_WIDTH', 320);
// サムネイル画像の高さの最大値
define('PLUGIN_CARD_THUMB_HEIGHT', 180);
// サムネイル画像のjpeg圧縮率
define('PLUGIN_CARD_THUMB_COMP_RATIO', 75);
// スニペット (本文の抜粋) の文字数
define('PLUGIN_CARD_SNIPPET_LENGTH', 200);
// デフォルトでスニペットを表示する
define('PLUGIN_CARD_SHOW_SNIPPET', true);
// デフォルトで更新日を表示する
define('PLUGIN_CARD_SHOW_DATE', true);
// デフォルトでベースネーム表示
define('PLUGIN_CARD_FORCE_BASENAME', false);
// 存在しないページが含まれている場合にエラーを出す
define('PLUGIN_CARD_ERROR_IF_NOT_EXISTS', false);
// カード配置のデフォルト (justify-content)
define('PLUGIN_CARD_DEFAULT_JUSTIFY', 'flex-start');
// 短縮URLのIDパターン (参考：https://pukiwiki.osdn.jp/dev/?BugTrack/2525)
// 現状は標準URLかsプラグインを用いた短縮URLにのみ対応
// 例1：?034d2305ca  -> \?([0-9a-f]{10})
// 例2：?&034d2305ca -> \?\&([0-9a-f]{10})
define('PLUGIN_CARD_SHORT_URL_PATTERN', '/\?([0-9a-f]{10})/');
// リダイレクト系プラグインのリスト (ページのソース取得中にリダイレクトされないようにするため)
// 現状は #plugin(page[,options]) ← この書式に当てはまるタイプにのみ対応
define('PLUGIN_CARD_REDIRECT_PLUGINS', 'alias, redirect');

// 画像圧縮ライブラリの読込
require_once(PLUGIN_DIR . 'resize.php');

/**
 * 初期化
 *
 * @return void
 */
function plugin_card_init()
{
    $messages['_card_messages'] = [
        'err_usage'        =>    '#card([options]){{ internal links }}',
        'err_unknown'      =>    '#card Error: Unknown argument. -> ',
        'err_nolinks'      =>    '#card Error: Could not find any internal links.',
        'err_noexists'     =>    '#card Error: The page does not exist. -> ',
        'err_range'        =>    '#card Error: The number of columns must be set between 1 to 6.',
        'err_conflict'     =>    '#card Error: The options are conflicting -> "h" and "toc"',
        'err_pass'         =>    'パスワードが間違っています。',
        'err_notfound'     =>    'キャッシュが見つかりませんでした。',
        'err_noselect'     =>    'キャッシュが選択されていません。',
        'err_failed'       =>    '"$1" の削除に失敗しました。',
        'msg_return'       =>    'トップページに戻る',
        'msg_redirect'     =>    '"$1" にリダイレクトします。',
        'msg_select'       =>    '削除するキャッシュを選んで下さい。',
        'msg_confirm'      =>    '以下のページのキャッシュを削除します。',
        'msg_delete'       =>    '"$1" のキャッシュを削除します。',
        'msg_result'       =>    '以下のキャッシュを削除しました。',
        'msg_checkall'     =>    'すべて選択 / 解除',
        'btn_delete'       =>    '削除',
        'btn_confirm'      =>    '確認',
        'btn_details'      =>    '詳細',
        'title_select'     =>    'キャッシュの管理',
        'title_confirm'    =>    '選択の確認',
        'title_result'     =>    '実行結果',
        'title_details'    =>    'キャッシュの詳細: $1',
        'label_file'       =>    'ファイル名',
        'label_tcache'     =>    '保存日時',
        'label_link'       =>    'リンク',
        'label_page'       =>    'ページ名',
        'label_base'       =>    'ベースネーム',
        'label_lastmod'    =>    '最終更新',
        'label_unix'       =>    'UNIX時間',
        'label_image'      =>    'サムネイル',
        'label_snippet'    =>    '本文の抜粋',
        'label_heading'    =>    '見出し',
        'label_pass'       =>    'パスワード'
    ];
    set_plugin_messages($messages);
}

/**
 * ブロック型
 *
 * @return string $html 作成したブログカード風リンク | エラーメッセージ
 */
function plugin_card_convert()
{
    global $_card_messages;
    static $id = 0;

    if (func_num_args() == 0) return '<p>' . $_card_messages['err_usage'] . '</p>';

    $args = func_get_args();
    $src = preg_replace("/\r|\r\n/", "\n", array_pop($args));
    $cards = new PluginCard();

    // オプション判別
    if (! empty($args)) {
        if (! $cards->parse_options($args)) {
            switch ($cards->err) {
                case 'err_range':
                case 'err_conflict':
                    return '<p>' . $_card_messages[$cards->err] . '</p>';
                default:
                    return '<p>' . $_card_messages['err_unknown'] . $cards->err . '</p>';
            }
        }
    }

    // 内部リンクとページ名を取得
    if (! $cards->parse_internal_links($src)) {
        if ($cards->err == 'err_nolinks') return '<p>' . $_card_messages['err_nolinks'] . '</p>';
        else return '<p>' . $_card_messages['err_noexists'] . $cards->err . '</p>';
    }
    // 各ページの情報を取得
    $cards->parse_page_data();

    // カードの作成
    $html = $cards->convert($id++);

    return $html;
}

/**
 * アクション型
 *
 * @return array 操作画面
 */
function plugin_card_action()
{
    $manager = new CardCacheManager();

    return $manager->action();
}

/**
 * カード作成用クラス
 *
 * @see plugin_card_convert()
 */
class PluginCard
{
    public $err;
    private $options;
    private $pages;
    private $datalist;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->options['lastmod'] = PLUGIN_CARD_SHOW_DATE;
        $this->options['snippet'] = PLUGIN_CARD_SHOW_SNIPPET;
        $this->options['cols'] = PLUGIN_CARD_DEFAULT_COLUMN;
        $this->options['base'] = PLUGIN_CARD_FORCE_BASENAME;
        $this->options['justify'] = PLUGIN_CARD_DEFAULT_JUSTIFY;
        $this->options['width'] = PLUGIN_CARD_CONTAINER_WIDTH;
        $this->options['class'] = 'plugin-card';
    }

    /**
     * オプションの判別
     *
     * @param  array $args 残りの引数
     * @return bool  true: 問題なし false: エラー
     */
    public function parse_options($args)
    {
        $opt = &$this->options;

        foreach ($args as $arg) {
            $arg = trim(htmlsc($arg));
            switch ($arg) {
                case 'nodate':
                    // 日付の非表示
                    $opt['lastmod'] = false;
                    break;
                case 'nosnippet':
                    // スニペットの非表示
                    $opt['snippet'] = false;
                case 'flex-start':
                case 'flex-end':
                case 'start':
                case 'end':
                case 'left':
                case 'center':
                case 'right':
                case 'space-between':
                case 'space-around':
                case 'space-evenly':
                case 'stretch':
                    // カードの配置
                    $opt['justify'] = $arg;
                    break;
                case 'base':
                    // ベースネーム表示
                    $opt[$arg] = true;
                    break;
                default:
                    if (is_numeric($arg)) {
                        if (preg_match('/^[1-6]$/', $arg)) {
                            // カラム数
                            $opt['cols'] = $arg;
                        } else {
                            // 指定可能な数値の範囲外ならエラー
                            $this->err = 'err_range';
                            return false;
                        }
                    } elseif (preg_match('/^(\*{1,3})=(\d+)$/', $arg, $matches)) {
                        // 指定した見出しを表示 (旧指定法)
                        $opt['head'] = strlen($matches[1]) . '-' . $matches[2];
                    } elseif (preg_match('/^h=(\d+(?:-\d+)?)$/', $arg, $matches)) {
                        // 指定した見出しを表示
                        $opt['head'] = $matches[1];
                    } elseif (preg_match('/^toc(=(\d+(:\d+)?))?$/', $arg, $matches)) {
                        // 目次を表示
                        if (! isset($matches[2])) $opt['toc'] = -1;
                        else $opt['toc'] = $matches[2];
                    } elseif (preg_match('/^width=(\d+(px|%|em|rem|vw|vh)$)/', $arg, $matches)) {
                        // コンテナの幅
                        $opt['width'] = $matches[1];
                    } elseif (preg_match('/^(class|class)=([^\']+?)$/', $arg, $matches)) {
                        // クラスの追加
                        $opt[$matches[1]] .= ' ' . $matches[2];
                    } elseif (preg_match('/^style=([^\']+?)$/', $arg, $matches)) {
                        // スタイルの追加
                        $opt['style'] = $matches[1];
                    } else {
                        // 不明な引数
                        $this->err = $arg;
                        return false;
                    }
            }
        }
        if (isset($opt['head']) && isset($opt['toc'])) {
            $this->err = 'err_conflict';
            return false;
        }

        return true;
    }

    /**
     * 内部リンクとページ名を取得
     *
     * @param  string $src 内部リンクを含む文字列
     * @return bool   true: 問題なし false: エラー
     */
    public function parse_internal_links($src)
    {
        global $defaultpage;

        // HTMLに変換
        if (strpos($src, "\n") !== false) $src = convert_html($src);
        else $src = make_link($src);

        // 内部リンクの抽出
        $uri = preg_quote(get_base_uri(), '/');
        $pattern = '/<a\s.*?href="(' . $uri . '(.*?))"/';
        if (preg_match_all($pattern, $src, $matches)) {
            foreach ($matches[2] as $i => $q) {
                // クエリからページ名を取得
                if (empty($q)) {
                    // トップページ
                     $pages[$defaultpage] = $matches[2];
                } elseif (preg_match(PLUGIN_CARD_SHORT_URL_PATTERN, $q, $m_id)) {
                    // 短縮URL
                    $page = $this->get_page_from_page_id($m_id[1]);
                } else {
                    // 標準URL
                    preg_match('/\?(.+)/', $q, $str);
                    $page = strpos($str[1], '+') !== false ? urldecode($str[1]) : rawurldecode($str[1]);
                }

                if (is_page($page)) {
                    $pages[$page] = $matches[1][$i];
                } else {
                    if (PLUGIN_CARD_ERROR_IF_NOT_EXISTS) {
                        $this->err = $page;
                        return false;
                    } else {
                        continue;
                    }
                }
            }
        } else {
            $this->err = 'err_nolinks';
            return false;
        }

        $this->pages = $pages;

        return true;
    }

    /**
     * 各ページの情報を取得する
     *
     * @return void
     */
    public function parse_page_data()
    {
        foreach ($this->pages as $page => $link) {
            if (PLUGIN_CARD_USE_CACHE) {
                $parser = new CardPageDataCache($page, $link);
            } else {
                $parser = new CardPageData($page, $link);
            }
            $this->datalist[$page] = $parser->data;
        }
    }

    /**
     * 全ての内部リンクをカードに変換する
     *
     * @return string $cards 全カードのHTML
     */
    public function convert($id)
    {
        $opt = $this->options;
        $datalist = $this->datalist;

        $cards = '';
        foreach ($datalist as $page => $data) {
            // パーツの組立
            $card = new CardElement($data);
            $link = $data['link'];
            $title = $card->title($opt['base']);
            $image = $card->image();
            $snippet = $opt['snippet'] ? $card->snippet($opt) : '';
            $lastmod = $opt['lastmod'] ? $card->lastmod() : '';
            $cards .= <<<EOD
            <div class="card-item">
                <a class="card-overwrap" href="$link" title="$page">
                    <div class="card-body">
                        $image
                        $title
                        $snippet
                        $lastmod
                    </div>
                </a>
            </div>
            EOD . "\n";
        }

        // コンテナの組立
        $attrs = $this->format_attrs($opt);
        $cards = <<<EOD
        <div id="cardContainer$id"$attrs>
        $cards
        </div>
        EOD;

        return $cards;
    }

    /**
     * 属性を整形する
     *
     * @param  array  $opt オプションの配列
     * @return string 属性
     */
    private function format_attrs($opt)
    {
        $arr_attrs['class'] = $opt['class'];
        $arr_attrs['data']['cols'] = $opt['cols'];
        $arr_attrs['data']['layout'] = ($opt['cols'] < PLUGIN_CARD_CHANGE_LAYOUT_THRESHHOLD) ? 'horizontal' : 'vertical';
        $arr_attrs['data']['compact'] = ! ($opt['cols'] < PLUGIN_CARD_HIDE_SNIPPET_THRESHOLD) ? 'true' : '';
        $arr_attrs['data']['justify'] = $opt['justify'];
        $arr_attrs['style'] = $opt['style'];
        if (! is_null($opt['width'])) $arr_attrs['style'] = 'width:' . $opt['width'] . ';' . $arr_attrs['style'];


        $attrs = '';
        if (isset($arr_attrs['class'])) {
            $class = ' class="' . $arr_attrs['class'] . '"';
            $attrs .= $class;
        }
        if (isset($arr_attrs['data'])) {
            foreach ($arr_attrs['data'] as $key => $val) {
                $attrs .= ' data-' . $key . '="' . $val . '"';
            }
        }
        if (isset($arr_attrs['style'])) {
            $style = ' style="' . $arr_attrs['style'] . '"';
            $attrs .= $style;
        }

        return $attrs;
    }

    /**
     * 短縮URLのページIDからページ名を取得する
     *
     * @param  string $id ページID
     * @return string $page ページ名
     */
    private function get_page_from_page_id($id)
    {
        $file = 'shortener/' . $id . '.txt';
        $page = file_get_contents($file);

        return $page;
    }
}

/**
 * 個別のカードを組み立てるためのクラス
 * 
 * @see PluginCrad::convert()
 */
class CardElement
{
    private $data;

    /**
     * コンストラクタ
     *
     * @param array $data 個別ページのデータ
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * カードタイトルの整形
     *
     * @param  bool $base ベースネームかどうか
     * @return string カードタイトルのHTML
     */
    public function title($base)
    {
        $title = $base ? $this->data['base'] : $this->data['title'];
        $title = '<h5 class="card-title bold">' . $title . '</h5>';

        return $title;
    }

    /**
     * サムネイルの整形
     *
     * @return string サムネイルのHTML
     */
    public function image()
    {
        $image =$this->data['image'];
        if (PLUGIN_CARD_MAKE_THUMBNAIL) {
            $w = PLUGIN_CARD_THUMB_WIDTH;
            $h = PLUGIN_CARD_THUMB_HEIGHT;
        } else {
            list($sw, $sh) = getimagesize($image);
            if ($sw == 0 || $sh == 0) return '';
            $h = PLUGIN_CARD_THUMB_HEIGHT;
            $w = $sw * $h / $sh;
        }
        $alt = $this->data['title'];
        $image = '<figure class="card-thumbnail"><img loading="lazy" class="card-image" src="'
         . $image . '" alt="' . $alt . '" width="' . $w . '" height="' . $h . '"></figure>';

        return $image;
    }

    /**
     * スニペットの整形
     *
     * @param  array  $opt オプションの配列
     * @return string $snippet スニペットのHTML
     */
    public function snippet($opt)
    {
        if (isset($opt['head'])) {
            $snippet = $this->head($opt['head']);
        } elseif (isset($opt['toc'])) {
            $snippet = $this->toc($opt['toc']);
        } else {
            $snippet = $this->data['snippet'];
        }
        $snippet = '<p class="card-snippet small">' . $snippet . '</p>';

        return $snippet;
    }

    /**
     * 更新日時の整形
     *
     * @return string $lastmod 更新日時のHTML
     */
    public function lastmod()
    {
        $full = format_date($this->data['lastmod']);
        $short = date('Y-m-d', $this->data['lastmod']);

        $lastmod = '<span class="card-lastmod small" data-length="full">' . $full . '</span>';
        $lastmod .='<span class="card-lastmod small" data-length="short">' . $short . '</span>';

        return $lastmod;
    }

    /**
     * スニペットに指定した見出しを表示する
     *
     * @param  string $index 見出しの指定
     * @return string $head 見出しor空白
     */
    private function head($index)
    {
        $hlist = $this->data['heads'];
        if (strpos($index, '-') === false) {
            // 上からn番目
            $head = current(array_slice($hlist, $index - 1, 1));
            if (empty($head)) $head = '';
        } else {
            // n番目のh[2-4]
            $head = array_key_exists($index, $hlist) ? $hlist[$index] : '';
        }

        return $head;
    }

    /**
     * スニペットに目次を表示する
     *
     * @param  int    $num 見出し数の指定
     * @return string $toc 目次
     */
    private function toc($num)
    {
        if (strpos($num, ':') === false) {
            $hlist = $this->data['heads'];
        } else {
            // 階層指定
            list($depth, $num) = explode(':', $num);
            $hlist = [];
            foreach ($this->data['heads'] as $key => $val) {
                if (strpos($key, $depth . '-') !== false) $hlist[] = $val;
            }
        }

        $i = 0;
        $toc = '';
        foreach ($hlist as $head) {
            if ($i == $num) break;
            $toc .= $head . '、';
            $i++;
        }
        $toc = preg_replace('/、$/', '。', $toc);

        return $toc;
    }
}

/**
 * 各ページの情報を取得するためのクラス (キャッシュ有効)
 *
 * @see PluginCard::parse_page_data()
 */
class CardPageDataCache extends CardPageData
{
    public function __construct($page, $link)
    {
        // ディレクトリの確認と作成
        if (! file_exists(PLUGIN_CARD_CACHE_DIR)) {
            mkdir(PLUGIN_CARD_CACHE_DIR, 0755);
            chmod(PLUGIN_CARD_CACHE_DIR, 0755);
        }

        // ページごとに情報を取得
        $cache = PLUGIN_CARD_CACHE_DIR . encode($page) . '.dat';
        if (! $this->is_fresh($page, $cache)) {
            $this->get_page_data($page, $link);
            // キャッシュの保存
            $this->save_cache($cache);
        } else {
            // キャッシュの読み込み
            $this->use_cache($cache);
            if (! file_exists($this->data['image']) || filesize($this->data['image']) < 1) {
                // サムネイルが破損 or なくなっていたら再取得
                $this->get_page_data($page, $link);
                $this->save_cache($cache);
            }
        }
    }

    /**
     * キャッシュの更新日をチェック
     *
     * @param  string $page ページ名
     * @param  string $cache キャッシュのパス
     * @return bool   true: 更新不要 false: 更新が必要
     */
    private function is_fresh($page, $cache)
    {
        if (! file_exists($cache)) return false;

        $file = get_filename($page);
        $t_cache = filemtime($cache);
        $t_file =filemtime($file);

        if ($t_file - $t_cache > 0) return false;
        else return true;
    }

    /**
     * キャッシュからページの情報を取得する
     *
     * @param  string $cache キャッシュのパス
     * @return void
     */
    private function use_cache($cache)
    {
        $json = file_get_contents($cache);
        $this->data = json_decode($json, true);
    }

    /**
     * キャッシュとしてページの情報を保存する
     *
     * @param  string $cache キャッシュのパス
     * @return void
     */
    private function save_cache($cache)
    {
        if (PLUGIN_CARD_CACHE_PRETTY) $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
        else $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        $json = json_encode($this->data, $flags);
        file_put_contents($cache, $json);
    }
}

/**
 * 各ページの情報を取得するためのクラス (キャッシュ無効)
 *
 * @see PluginCard::parse_page_data()
 */
class CardPageData
{
    public $data;

    /**
     * コンストラクタ
     *
     * @param string $page ページ名
     * @param string $link ページのリンク
     */
    public function __construct($page, $link)
    {
        $this->get_page_data($page, $link);
    }

    /**
     * 各ページの情報を配列に格納する
     *
     * @param  string $page ページ名とリンクの配列
     * @param  string $link ページのリンク
     * @return void
     */
    public function get_page_data($page, $link) {
        // ページのソースを取得
        $body = $this->get_page_source($page);

        // リンク
        $this->data['link'] = $link;
        // ページタイトル
        $this->data['title'] = $page;
        // ベースネーム
        if (strpos($page, '/')) $this->data['base'] = array_pop(explode('/', $page));
        else $this->data['base'] = $page;
        // 更新日時
        $this->data['lastmod'] = get_filetime($page);
        // サムネイル画像
        $this->data['image'] = $this->get_thumbnail($page, $body);
        // スニペット
        $this->data['snippet'] = $this->get_snippet($body);
        // 見出しリスト
        $this->data['heads'] = $this->get_heads($page);
    }

    /**
     * サムネイル画像を取得
     *
     * @param  string $page ページ名
     * @param  string $body ページのソース
     * @return string $image | $thumb サムネイル画像のリンクorパス
     */
    public function get_thumbnail($page, $body)
    {
        // ディレクトリの確認と作成
        if (PLUGIN_CARD_MAKE_THUMBNAIL) {
            if (! file_exists(PLUGIN_CARD_THUMB_DIR)) {
                mkdir(PLUGIN_CARD_THUMB_DIR, 0755);
                chmod(PLUGIN_CARD_THUMB_DIR, 0755);
            }
            // 画像の読み取りを許可する
            $htaccess = PLUGIN_CARD_THUMB_DIR . '.htaccess';
            if (! file_exists($htaccess)) {
                $cmd = <<<EOD
                Require all denied
                # card plugin's image cache
                <FilesMatch "^([0-9A-F]{2})+?\.jpg$">
                    Require all granted
                </FilesMatch>
                EOD;
                file_put_contents($htaccess, $cmd);
                chmod($htaccess, 0644);
            }
        }

        $eyecatch = PLUGIN_CARD_THUMB_DEFAULT;
        $thumb = PLUGIN_CARD_THUMB_DIR . encode($page) . '.jpg';
        $uri = preg_quote(get_base_uri(), '/');
        if (preg_match('/<img\s.*?(data-lazy|src)="' . $uri . '(.*?)"/', $body, $matches)) {
            // ページ本文から探す
            if ($matches[2]) {
                $image = get_base_uri(PKWK_URI_ABSOLUTE) . htmlspecialchars_decode($matches[2]);
            } else {
                $image = $eyecatch;
            }
        } else {
            $image = $eyecatch;
        }
        if (PLUGIN_CARD_THUMB_DEF_DIRECT && $image == $eyecatch) {
            return $image;
        } elseif (PLUGIN_CARD_MAKE_THUMBNAIL) {
            file_put_contents($thumb, file_get_contents($image));
        } else {
            return $image;
        }

        // サムネイル画像をリサイズ
        $width = PLUGIN_CARD_THUMB_WIDTH;
        $height = PLUGIN_CARD_THUMB_HEIGHT;
        $comp = PLUGIN_CARD_THUMB_COMP_RATIO;
        @ImageResizer::make_thumbnail($thumb, $thumb, $width, $height, $comp);

        return $thumb;
    }

    /**
     * スニペットを取得
     *
     * @param  string $body ページのソース
     * @return string 本文や見出しの抜粋
     */
    public function get_snippet($body)
    {
        // 本文からスニペットを生成。サイトに合わせてカスタマイズ推奨
        $snippet = preg_replace('/<div[^>]*?class="(contents|toc).*?<\/div>/', '', $body);
        $snippet = preg_replace('/<(span|div)[^>]*?class="small".*?<\/\1>/' , '', $snippet);
        $snippet = preg_replace('/<span[^>]*?class="tag.*?<\/span>/', '', $snippet);
        $snippet = preg_replace('/<script.*?\/script>/', '', $snippet);
        preg_match_all('/<(p|dt|dd|li).*?>(.*?)<\/\1>/', $snippet, $matches);
        foreach ($matches[2] as $i => $line) {
            if (empty($line)) {
                unset($matches[2][$i]);
                continue;
            } elseif (! preg_match('/[,.。、！？!?]$/', $line)) {
                $matches[2][$i] .= '。';
            }
        }
        $snippet = implode(' ', $matches[2]);
        $snippet = strip_tags($snippet);
        $snippet = preg_replace('/\s{2,}/', ' ', $snippet);
        $snippet = mb_substr($snippet, 0, PLUGIN_CARD_SNIPPET_LENGTH, 'UTF-8');

        return $snippet;

    }

    /**
     * 見出し一覧を取得
     *
     * @param  string $page ページ名
     * @return array  $heads 見出し一覧
     */
    public function get_heads($page)
    {
        $source = get_source($page);
        $heads= [];
        $h_count = [];

        foreach ($source as $line) {
            if (preg_match('/^(\*{1,3})\s*?(.+?)\s\[#/', $line, $matches)) {
                $depth = strlen($matches[1]);
                if (! isset($h_count[$depth])) $h_count[$depth] = 0;
                $num = ++$h_count[$depth];
                $key = $depth . '-' . $num;
                $head = strip_tags(make_link($matches[2]));
                $heads[$key] = $head;
            }
        }

        return $heads;
    }

    /**
     * 対象ページのHTMLソースを取得
     *
     * @param  string $page ページ名
     * @return string ページのソース
     */
    public function get_page_source($page)
    {
        global $vars, $_card_messages;

        // 一時的に書き換え
        $cp = $vars['page'];
        $vars['page'] = $page;

        $raw = get_source($page, true, true);
        $nglist = explode(',', PLUGIN_CARD_REDIRECT_PLUGINS);
        foreach ($nglist as $ng) {
            // リダイレクト系プラグインに対処
            $ng = trim($ng);
            if (preg_match("/#" . $ng . "\((.+?)\)\n/", $raw, $m_args)) {
                $first = array_shift(explode(',', $m_args[1]));
                $body = '<p>' . str_replace('$1', htmlsc($first), $_card_messages['msg_redirect']) . '</p>';
                $vars['page'] = $cp;

                return $body;
            }
        }

        while (true) {
            // ループ防止のため対象ページに含まれるcardプラグインを消す
            if (preg_match("/(#card(\(.*?\))?(\{{2,})?)\n/", $raw, $matches)) {
                if (isset($matches[3])) {
                    $end = str_replace('{', '}', $matches[3]);
                    $pattern = '/' . preg_quote($matches[1], '/') . '[\s\S]*?' . $end . '/';
                } else {
                    $pattern = '/' . preg_quote($matches[1], '/') . '/';
                }
                $raw = preg_replace($pattern, '', $raw);
            } else {
                break;
            }
        }
        $body =  preg_replace("/\r|\r\n|\n/", '', convert_html($raw));
        $vars['page'] = $cp;

        return $body;
    }
}

/**
 * キャッシュ操作 (アクション型) 用クラス
 *
 * @see plugin_card_action()
 */
Class CardCacheManager
{
    /**
     * 操作画面の振り分け
     *
     * @return array 操作画面
     */
    public function action()
    {
        global $vars;

        if ($vars['pcmd'] == 'info') {
            return $this->show_info();
        } elseif ($vars['pcmd'] == 'confirm') {
            return $this->confirmation();
        } else {
            return $this->make_list();
        }
    }

    /**
     * キャッシュ一覧の作成
     *
     * @param  array  $caches キャッシュ一覧
     * @return string キャッシュの管理画面
     */
    private function make_list()
    {
        global $_card_messages;

        $msg = $_card_messages['title_select'];

        // キャッシュを検索
        $caches = glob(PLUGIN_CARD_CACHE_DIR . '*.dat');
        if (empty($caches)) {
            $body = '<p>' . $_card_messages['err_notfound'] . '</p>';
            return array('msg' => $msg, 'body' => $body);
        }

        $home = get_base_uri();
        $body = '';
        foreach ($caches as $i => $cache) {
            $page = $this->decode_cache_name($cache);
            $e_page = rawurlencode($page);
            $title = str_replace('$1', $page, $_card_messages['title_details']);
            $url = $home . '?cmd=card&pcmd=info&page=' . $e_page;
            $body .= <<<EOD
            <li>
                <input type="checkbox" id="chk$i" class="check_list" name="cache[$i]" value="$cache">
                <input type="hidden" name="pagename[$i]" value="$page">
                <label for="chk$i">$page</label>
                <span class="small">[<a href="$url" title="$title">{$_card_messages['btn_details']}</a>]</span>
            </li>
            EOD;
        }
        $body = '<ul>' . "\n" . $body . "\n" . '</ul>';

        // 全選択/解除用スクリプト
        $js = <<<EOD
        <script>
            const check_all = document.querySelector("#check_all");
            const check_list = document.querySelectorAll(".check_list");

            check_all.addEventListener('change', () => {
                if (check_all.checked) {
                    check_list.forEach (checkbox => (checkbox.checked = true));
                } else {
                    check_list.forEach (checkbox => (checkbox.checked = false));
                }
            });
        </script>
        EOD;

        // 選択用フォームの作成
        $body = <<<EOD
        <p>{$_card_messages['msg_select']}</p>
        <p style="user-select:none">
            <input type="checkbox" id="check_all">
            <label for="check_all">{$_card_messages['msg_checkall']}</label>
        </p>
        <form method="post" action="./">
            $body
            <input type="hidden" name="cmd" value="card">
            <input type="hidden" name="pcmd" value="confirm">
            <input type="submit" value="{$_card_messages['btn_confirm']}">
        </form>
        $js
        EOD;

        return array('msg' => $msg, 'body' => $body);
    }

    /**
     * キャッシュの詳細を表示する
     *
     * @return array 詳細画面
     */
    private function show_info()
    {
        global $vars, $_card_messages;

        $page =$vars['page'];
        $msg = str_replace('$1', $vars['page'], $_card_messages['title_details']);
        $msg_delete = str_replace('$1', $page, $_card_messages['msg_delete']);

        // 表示するデータの整形
        $e_page = encode($page);
        $cache = PLUGIN_CARD_CACHE_DIR . $e_page . '.dat';
        if (! file_exists($cache)) {
            $body = '<p>' . $_card_messages['err_notfound'] . '</p>';
            return array('msg' => $msg, 'body' => $body);
        }
        $cachetime = filemtime($cache);
        $f_cachetime = format_date($cachetime);
        $data = json_decode(file_get_contents($cache), true);
        $pagelink = '<a href="' . $data['link'] . '" title="' . $page . '">' . $page . '</a>';
        $f_date = format_date($data['lastmod']);
        $size = getimagesize($data['image']);
        if ($size[1] > PLUGIN_CARD_THUMB_HEIGHT) {
            $w = $size[0] * PLUGIN_CARD_THUMB_HEIGHT / $size[1];
            $h = PLUGIN_CARD_THUMB_HEIGHT;
        } else {
            $w = $size[0];
            $h = $size[1];
        }
        $image = '<img loading="lazy" src="' . $data['image'] . '" alt="' . $page .
                  '" width="' . $w . '" height="' . $h . '">';
        $image = '<a href="' . $data['image'] . '" title="' . $page . '">' . $image . '</a>';
        $toc = $this->make_toc($data['heads']);

        // データ表示
        $body = <<<EOD
        <div class="ie5">
            <table class="style_table" style="width:100%;word-break:break-all;">
                <thead><tr><th class="style_th" colspan="2">$msg</th></tr></thead>
                <tbody>
                    <tr>
                        <th class="style_th" style="width:150px">{$_card_messages['label_file']}</th>
                        <td class="style_td">$cache</td>
                    </tr>
                    <tr>
                        <th class="style_th">{$_card_messages['label_tcache']}<br>({$_card_messages['label_unix']})</th>
                        <td class="style_td">$f_cachetime ($cachetime)</td>
                    </tr>
                    <tr>
                        <th class="style_th">{$_card_messages['label_page']}</th>
                        <td class="style_td">$pagelink</td>
                    </tr>
                    <tr>
                        <th class="style_th">{$_card_messages['label_base']}</th>
                        <td class="style_td">{$data['base']}</td>
                    </tr>
                    <tr>
                        <th class="style_th">{$_card_messages['label_link']}</th>
                        <td class="style_td">{$data['link']}</td>
                    </tr>
                    <tr>
                        <th class="style_th">{$_card_messages['label_lastmod']}<br>({$_card_messages['label_unix']})</th>
                        <td class="style_td">$f_date ({$data['lastmod']})</td>
                    </tr>
                    <tr>
                        <th class="style_th">{$_card_messages['label_image']}</th>
                        <td class="style_td">$image<br>{$data['image']}<br>($size[0] x $size[1], {$size['mime']})</td>
                    </tr>
                    <tr>
                        <th class="style_th">{$_card_messages['label_snippet']}</th>
                        <td class="style_td">{$data['snippet']}</td>
                    </tr>
                    <tr>
                        <th class="style_th">{$_card_messages['label_heading']}</th>
                        <td class="style_td">$toc</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <hr class="hr_full">
        <p>$msg_delete</p>
        <form method="post" action="./">
            <input type="password" name="pass" placeholder="{$_card_messages['label_pass']}">
            <input type="submit" value="{$_card_messages['btn_delete']}">
            <input type="hidden" name="cache[]" value="$cache">
            <input type="hidden" name="pagename[]" value="$page">
            <input type="hidden" name="cmd" value="card">
            <input type="hidden" name="pcmd" value="confirm">
        </form>
        EOD;

        return array('msg' => $msg, 'body' => $body);
    }

    /**
     * 削除するキャッシュの確認画面
     *
     * @param  string $msg
     * @return array  確認画面
     */
    private function confirmation() {
        global $vars, $_card_messages;

        $msg = $_card_messages['title_confirm'];

        // 選択したファイルのリストを作成
        $targets = '';
        if ($vars['cache']) {
            foreach ($vars['cache'] as $i => $val) {
                $page = $vars['pagename'][$i];
                $targets .= <<<EOD
                <li>
                    <input type="hidden" name="cache[$i]" value="$val"> $page
                    <input type="hidden" name="pagename[$i]" value="$page">
                </li>
                EOD;
            }
            $targets = '<ul>' . $targets . '</ul>';
        } else {
            // ファイルが一つも選択されていなかった場合はエラー
            return array('msg' => $msg, 'body' => '<p>' . $_card_messages['err_notselect'] . '</p>');
        }

        // 認証用フォームの作成
        $auth_failed = '<p>' . $_card_messages['err_pass'] . '</p>' . "\n";
        $body = <<<EOD
        <form method="post" action="./">
            <p>{$_card_messages['msg_confirm']}</p>
            <input type="password" name="pass" placeholder="{$_card_messages['label_pass']}">
            <input type="submit" value="{$_card_messages['btn_delete']}">
            $targets
            <input type="hidden" name="cmd" value="card">
            <input type="hidden" name="pcmd" value="confirm">
        </form>
        EOD;

        // パスワードのチェック
        if ($vars['pass']) {
            if (pkwk_login($vars['pass'])) {
                $body = $this->delete();
                return array('msg' => $msg, 'body' => $body);
            } else {
                return array('msg' => $msg, 'body' => $auth_failed . $body);
            }
        } else {
            return array('msg' => $msg, 'body' => $body);
        }
    }

    /**
     * リストされたキャッシュを削除する
     *
     * @return string $body 結果表示
     */
    private function delete()
    {
        global $vars, $_card_messages;

        $caches = $vars['cache'];
        $pages = $vars['pagename'];
        $toppage = get_base_uri();

        if (empty($caches)) {
            // キャッシュがなければ終了
            $body = '<p>' . $_card_messages['err_notfound'] . '</p>';
            return $body;
        } else {
            // キャッシュがあればそれらを削除
            $body = '';
            foreach ($caches as $i => $cache) {
                $page = $pages[$i];
                if (unlink($cache)) {
                    if (PLUGIN_CARD_MAKE_THUMBNAIL) {
                        // サムネイルの削除
                        $thumb = PLUGIN_CARD_THUMB_DIR . encode($page) . '.jpg';
                        if (file_exists($thumb)) unlink ($thumb);
                    }
                    // 削除に成功したページをリストアップ
                    $body .= '<li>' . $page . '</li>' . "\n";
                } else {
                    // 削除に失敗したら処理を終了
                    $body = '<p>' . str_replace('$1', $cache, $_card_messages['err_failed']) . '</p>';
                    return $body;
                }
            }
            $body = <<<EOD
            <a href="$toppage">{$_card_messages['msg_return']}</a>
            <p>{$_card_messages['msg_result']}<p>
            <ul>
            $body
            </ul>
            EOD;
        }
        return $body;
    }

    /**
     * 見出しリストから目次を作成する
     *
     * @param  array $hlist 見出しリスト
     * @return string 目次
     */
    private function make_toc($hlist) {
        if (empty($hlist)) return '';
        else $heads = '<ul>';
        $prev = 1;
        $i = 0;
        foreach($hlist as $key => $val) {
            $depth = array_shift(explode('-', $key));
            if ($i != 0) {
                switch ($prev - $depth) {
                    case -2:
                        $heads .= '<ul>' . '<li>' . '<ul>';
                        break;
                    case -1:
                        $heads .= '<ul>';
                        break;
                    case 1:
                        $heads .= '</li>' . '</ul>' . '</li>';
                        break;
                    default:
                        $heads .= '</li>';
                }
            }
            $heads .= '<li>' . $key . ': ' . $val;
            $prev = $depth;
            $i++;
        }
        for ($i = 0; $i < $prev; $i++) {
            $heads .= '</li>' . '</ul>';
        }

        return $heads;
    }

    /**
     * キャッシュのページ名部分をデコードする
     *
     * @param  string $cache キャッシュのパス
     * @return string $page ページ名
     */
    private function decode_cache_name($cache)
    {
        $pattern = '/(.+\/)(([0-9A-F]{2})+)/';
        if (preg_match($pattern, $cache, $matches)) {
            $page = decode($matches[2]);
        }

        return $page;
    }
}