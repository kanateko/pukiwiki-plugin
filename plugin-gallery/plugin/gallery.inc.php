<?php
/**
 * photoswipe版 画像のギャラリー表示プラグイン
 *
 * @version 2.11
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * -- Updates --
 * 2025-09-28 v2.11 page指定時にもボタンから画像を追加した際にページの内容が更新されるよう改善
 * 2025-09-12 v2.10 ページ名の相対指定に対応
 * 2025-08-28 v2.9  AVIF画像に対応
 * 2024-09-09 v2.8  画像同士の余白を指定するオプション (gap) を追加
 * 2023-06-10 v2.7  all指定時、キャプションの投稿時間が正しくなかった問題を修正
 *                  sortとnocapを同時指定した際にキャプションが表示されてしまう問題を修正
 * 2023-05-27 v2.6  キャプションの検索とソートを追加
 * 2023-05-24 v2.5  参照するページを指定するオプション (page) を追加
 * 2022-11-16 v2.4  ソートオプションを追加
 *            v2.3  添付されたすべての画像を表示するオプション (all) を追加
 * 2022-07-09 v2.2  画像の挿入位置を変更するオプションを追加
 *                  アクション型の脆弱性を修正
 *                  各オプションの初期値と初期化のタイミングを変更
 *                  画像サイズ計算時の0の扱いを変更
 *            v2.1  サムネイルの折り返しの有無を指定するオプションを追加
 * 2022-07-08 v2.0  Photoswipeのバージョンアップに対応
 *                  全体的にコードを改良
 *                  画像追加時、ページ上に全く同じギャラリーがあるとその全てが書き換えの対象になる問題を修正
 *                  キャプションでPukiWiki記法を利用可能に
 * 2022-05-18 v1.8  1.5.4のURLカスタマイズに対応
 *                  v1.7以前に使用していたURL短縮プラグインへの対応を終了
 * 2021-09-29 v1.7  ファイルとキャプションのセパレータを変更するための設定を追加
 * 2021-07-26 v1.6  対応する拡張子にwebpを追加 (一覧表示のみ)
 *                  初期化用コードの挿入部分を少し変更
 * 2021-07-11 v1.5  初期化用コードの呼び出しを1回のみに変更
 * 2021-07-01 v1.4  階層化されたページの添付ファイルを正常に表示できなかった問題を修正
 * 2021-06-22 v1.3  短縮URLを導入してあるかどうかで処理を変えるよう変更
 *                  一覧画像の高さ指定機能を追加
 *            v1.2  アップロード後元のページに戻る際にFatalErrorが出る問題を修正
 *                  一覧画像の高さが自動調整されなかった問題を修正
 * 2021-06-21 v1.1  画像の縁取り無効オプションを追加
 *                  切り抜きがsquareの場合はキャプションを表示するよう変更
 * 2021-06-20 v1.0  ページの編集権限をチェックする機能を追加
 *                  画像追加ボタンのデザインを調整
 *                  画像一覧の左寄せ・中央・右寄せを設定する機能を追加
 *                  画像一覧の画像を正方形・円に切り抜いて表示する機能を追加
 *                  画像一覧でキャプションを非表示にする機能を追加
 * 2021-06-19 v0.7  添付されたファイルのフォーマット判別を厳正化
 * 2021-06-18 v0.6  使用するライブラリをlightnox.jsからphotoswipe.jsに変更
 * 2021-06-17 v0.5  画像追加機能を追加
 *                  画像追加ボタンの表示/非表示切り替え機能を追加
 *            v0.2  他のページの添付画像を表示する機能を追加
 *                  画像の横幅を指定する機能を追加
 * 2021-06-16 v0.1  初版作成
 */

// Photoswipeの必要ファイル
define('PLUGIN_GALLERY_PSWP_CORE', 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.esm.min.js');
define('PLUGIN_GALLERY_PSWP_LIGHTBOX', 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.esm.min.js');
define('PLUGIN_GALLERY_PSWP_CSS', 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.min.css');
// ソート用ライブラリ (List.js)
define('PLUGIN_GALLERY_SORT_JS', 'https://cdn.jsdelivr.net/npm/list.js@2/dist/list.min.js');
// PukiWiki用CSS
define('PLUGIN_GALLERY_CSS', SKIN_DIR . 'css/gallery.min.css');
// ファイルとキャプションのセパレータ
define('PLUGIN_GALLERY_SEPARATOR', '>');
// 対応フォーマット (参考：https://www.php.net/manual/ja/function.exif-imagetype.php)
// gif, jpg, png, webp, avif
define('PLUGIN_GALLERY_AVAILABLE_FORMAT', '/[1-3]|18|19/');

function plugin_gallery_init(): void
{
    global $head_tags;

    $msg['_gallery_messages'] = [
        'err_invalid'  => '#gallery Error: Invalid argument. ($1)',
        'err_notfound' => '#gallery Error: File not Found. ($1)',
        'err_nopage'   => '#gallery Error: Page Not Found. ($1)',
        'err_mime'     => '#gallery Error: Unsuppoerted Format. ($1)',
        'label_add'    => '画像を追加する',
        'label_cap'    => 'キャプション',
        'label_upload' => 'アップロード',
        'msg_maxsize'  => 'アップロード可能なファイルサイズの最大',
        'msg_sizeover' => 'ファイルサイズが上限を超えています',
        'msg_mime'     => '不正なファイル形式です'
    ];
    set_plugin_messages($msg);

    $head_tags[] = '<link rel="stylesheet" href="' . PLUGIN_GALLERY_CSS . '?t=' . filemtime(PLUGIN_GALLERY_CSS) . '">';
    $head_tags[] = '<link rel="stylesheet" href="' . PLUGIN_GALLERY_PSWP_CSS . '">';

}

/**
 * ブロック型
 *
 * @param array $args プラグインの引数
 * @return string HTMLに変換した画像ギャラリー
 */
function plugin_gallery_convert(...$args): string
{
    $gallery = new PluginGallery($args);

    if ($gallery->err) return "<p>$gallery->err</p>";
    else return $gallery->convert();
}

function plugin_gallery_action(): ?array
{
    $action = new GalleryAction();

    return $action->select_mode();
}

/**
 * 画像ギャラリーの表示
 *
 * @property string $err エラー内容
 * @property bool $is_empty マルチライン部分が空か
 * @property string $page 現在のページ名
 * @property ?array $images 表示する画像の情報
 * @property ?array $options プラグインのオプション
 * @property int $id プラグインの呼び出し回数
 * @property array $loaded スクリプトの読み込みフラグ
 */
class PluginGallery
{
    public $err;
    private $is_empty;
    private $page;
    private $images;
    private $options;
    private static $id = 0;
    private static $loaded = [];

    /**
     * コンストラクタ
     *
     * @param ?array $args プラグインの引数
     */
    public function __construct($args)
    {
        global $vars;

        $this->page = $vars['page'];
        $args = preg_replace("/\r|\r\n/", "\n", $args);
        if (empty($args)) {
            $this->is_empty = true;
        } elseif (strpos(end($args), "\n") === false) {
            $this->array_options($args);
            if ($this->options['uploadto']) $this->page = $this->options['uploadto'];
            if ($this->options['all'] === true) {
                $this->array_images('');
                if ($this->images === null || empty(array_filter($this->images))) $this->is_empty = true;
            } else {
                $this->is_empty = true;
            }
        } else {
            $multiline = array_pop($args);
            if (! empty($args)) $this->array_options($args);
            if ($this->options['uploadto'])  $this->page = $this->options['uploadto'];
            $this->array_images($multiline);
            if ($this->images === null || empty(array_filter($this->images))) $this->is_empty = true;
        }

        // デフォルト設定
        $this->options['add']       ??= true;
        $this->options['break']     ??= true;
        $this->options['cap']       ??= true;
        $this->options['wrap']      ??= true;
        $this->options['insert_to'] ??= 'bottom';
        $this->options['placement'] ??= 'center';
        $this->options['unit']      ??= 'px';
        if (! $this->options['width'] && ! $this->options['height']) {
            $this->options['height'] = 180;
        }
    }

    /**
     * HTMLに変換して出力
     *
     * @return string $html
     */
    public function convert(): string
    {
        global $vars, $_gallery_messages;

        $id = self::$id++;
        $items = '';
        if (! $this->is_empty) {
            $items = '';
            foreach ($this->images as $i => $image) {
                // サイズ
                [$width, $height, $unit] = $this->calculate_size($image['width'], $image['height']);
                // キャプション
                if ($this->options['cap'] && $image['cap']) {
                    $cap = '<div class="pswp-caption-content">' . $image['cap'] . '</div>';
                    $cap_attr = ' data-cap="' . strip_tags(preg_replace('/<script[\s\S]*?\/script>/', '', $cap)) . '"';
                } else {
                    $cap = $cap_attr = '';
                }
                // ソート用
                if ($this->options['sort']) {
                    $sort_attr = ' data-name="' . $image['src'] . '" data-date="' . $image['date'] . '"';
                } else {
                    $sort_attr = '';
                }
                // 参照ページ
                if ($this->options['uploadto']) {
                    $image['page'] = $this->options['uploadto'];
                }

                $href = get_base_uri() . '?cmd=ref&page=' . rawurlencode($image['page']) . '&src=' . $image['src'];
                $items .= <<<EOD
                <div class="gallery-item" id="galleryItem{$id}_$i"$cap_attr$sort_attr>
                    <a href="$href"data-pswp-width="{$image['width']}"
                       data-pswp-height="{$image['height']}" target="blank">
                        <img class="gallery-image" src="$href" style="width:$width$unit;height:$height$unit;"
                             title="{$image['src']}" alt="{$image['src']}">
                    </a>
                    $cap
                </div>
                EOD;
            }

            // スクリプト
            if (! isset(self::$loaded['pswp'])) {
                self::$loaded['pswp'] = true;
                $core = PLUGIN_GALLERY_PSWP_CORE;
                $lightbox = PLUGIN_GALLERY_PSWP_LIGHTBOX;

                $script = <<<EOD
                <script type="module">
                    import PhotoSwipeLightbox from '$lightbox';
                    const lightbox = new PhotoSwipeLightbox({
                        gallery: '.gallery-items',
                        children: '.gallery-item',
                        pswpModule: () => import('$core'),
                        wheelToZoom: true,
                        padding: { top: 20, bottom: 80, left: 80, right: 80 }
                    });
                    document.addEventListener('DOMContentLoaded', () => {
                        lightbox.on('uiRegister', function() {
                            lightbox.pswp.ui.registerElement({
                                name: 'custom-caption',
                                isButton: false,
                                appendTo: 'wrapper',
                                html: '',
                                onInit: (el, pswp) => {
                                    lightbox.pswp.on('change', () => {
                                        const currSlideElement = lightbox.pswp.currSlide.data.element;
                                        let captionHTML = '';
                                        if (currSlideElement) {
                                            const hiddenCaption = currSlideElement.querySelector('.pswp-caption-content');
                                            if (hiddenCaption) {
                                                captionHTML = hiddenCaption.innerHTML;
                                            } else {
                                                captionHTML = currSlideElement.querySelector('img').getAttribute('alt');
                                            }
                                        }
                                        el.innerHTML = captionHTML || '';
                                    });
                                }
                            });
                        });
                        lightbox.init();
                    });
                </script>
                EOD;
            }
            // ソート用
            if ($this->options['sort']) {
                if (! isset(self::$loaded['sort'])) {
                    self::$loaded['sort'] = true;
                    $script .= '<script src="' . PLUGIN_GALLERY_SORT_JS . '"></script>';
                }
                if ($this->options['cap'] === true) {
                    $data_cap = ", 'cap'";
                    $sorter_cap = '<span class="sort" data-sort="cap">キャプション</span>';
                } else {
                    $data_cap = $sorter_cap = '';
                }
                $script .= <<<EOD
                <script>
                    const options$id = {
                        valueNames: [
                            {data: ['name', 'date'$data_cap]}
                        ]
                    };
                    const gallery$id = new List('gallery_wrap$id', options$id);
                </script>
                EOD;
                $sorter = <<<EOD
                <div class="gallery-control">
                    <input class="search" placeholder="検索">
                    <div class="sorter">
                        <span class="sort" data-sort="name">ファイル名</span>
                        $sorter_cap
                        <span class="sort" data-sort="date">投稿日時</span>
                    </div>
                </div>
                EOD;
            } else {
                $sorter = '';
            }
        }

        // 最終的な表示
        $add_button = $this->options['add'] ? '<a class="gallery-add" href="'
        . get_base_uri() . '?cmd=gallery&mode=add&uploadto=' . rawurlencode($this->page) . '&refer=' . $vars['page'] . '&id=' . $id
        . '&insert_to=' . $this->options['insert_to'] . '">' . $_gallery_messages['label_add'] . '</a>' : '';
        $placement = ! $this->options['break'] ? 'left' : $this->options['placement'];
        $gap = $this->options['gap'] !== null ? ';gap:' . $this->options['gap'] : '';
        $break = ' data-break="' . var_export($this->options['break'], true) . '"';
        $crop = $this->options['crop'] ? ' data-crop="' . $this->options['crop'] . '"' : '';
        $wrap = ' data-wrap="' . var_export($this->options['wrap'], true) . '"';

        $html = <<<EOD
        <div class="plugin-gallery" id="gallery_wrap$id">
            $sorter
            <div class="gallery-items list" id="gallery$id" style="justify-content:$placement$gap"$break$crop$wrap>
            $items
            </div>
            $add_button
        </div>
        $script
        EOD;

        return $html;
    }

    /**
     * 画像の情報を配列に格納する
     *
     * @param string $multiline 引数のマルチライン部分
     * @return void
     */
    private function array_images($multiline): void
    {
        global $_gallery_messages;

        if ($this->options['all'] === true) {
            $multiline = '';
            $pattern = UPLOAD_DIR . encode($this->page) . '_*';
            $files = glob($pattern);
            foreach ($files as $file) {
                preg_match('/.+_([^\.]+)$/', $file, $m);
                if (empty($m[1])) continue;
                $name = decode($m[1]);
                $multiline .= $name . PLUGIN_GALLERY_SEPARATOR . $name . ' - ' . format_date(filemtime($file) - LOCALZONE) . "\n";
            }
        }

        $lines = function($x) {
            foreach (explode("\n", $x) as $str) yield $str;
        };

        foreach ($lines($multiline) as $line) {
            [$src, $cap] = explode(PLUGIN_GALLERY_SEPARATOR, $line);
            if ($this->options['uploadto']) $src = $this->options['uploadto'] . '/' . $src;
            if (preg_match('/\.(jpe?g|png|gif|webp)$/', $src)) {
                // 暫定的にフォーマットを確認
                $info = $this->get_image_info(trim(htmlsc($src)));
                if ($info === null) break;
                $info['cap'] = make_link($cap);
                $this->images[] = $info;
            } elseif ($line === '') {
                continue;
            } else {
                $this->err = str_replace('$1', $line, $_gallery_messages['err_invalid']);
                break;
            }
        }
    }

    /**
     * オプションの判別
     *
     * @param array $args マルチライン部分を除外した引数
     * @return void
     */
    private function array_options($args): void
    {
        global $vars, $_gallery_messages;

        foreach ($args as $arg) {
            $arg = htmlsc($arg);
            $arg = preg_replace('/^\s*(.+?)(?:\s*(=)\s*(.+))?\s*$/', '$1$2$3', $arg);
            if (preg_match('/^(left|center|right|space-(around|between|evenly)|(flex-)?(start|end))$/', $arg)) {
                // サムネイルの配置
                $this->options['placement'] = $arg;
            } elseif (preg_match('/^(top|bottom)$/', $arg)) {
                // 画像の挿入位置
                $this->options['insert_to'] = $arg;
            } elseif (preg_match('/^(circle|square)$/', $arg)) {
                // サムネイルの切り取り
                $this->options['crop'] = $arg;
            } elseif (preg_match('/^(width|height)=(\d+)(.+)?$/', $arg, $m)) {
                // サムネイルのサイズ
                $this->options[$m[1]] = (int)$m[2];
                $this->options['unit'] = $m[3];
            } elseif (preg_match('/^(no)?(add|break|cap|wrap)$/', $arg, $m)) {
                // 追加ボタン、折り返し、キャプション、縁の有無
                $this->options[$m[2]] = $m[1] ? false : true;
            } elseif (preg_match('/^page=(.+)$/', $arg, $m)) {
                // 参照ページの指定
                $page = $m[1];
                if (strpos($m[1], './') !== false) {
                    $page = get_fullname($page, $vars['page']);
                }
                if (is_page($page)){
                    $this->options['uploadto'] = $page;
                } else {
                    $this->err = str_replace('$1', $arg, $_gallery_messages['err_nopage']);
                }
            } elseif (preg_match('/^gap=(.+)$/', $arg, $m)) {
                // 画像同士の余白
                $m[1] = is_numeric($m[1]) && $m[1] !== '0' ? $m[1] . 'px' : $m[1];
                $this->options['gap'] = $m[1];
            } elseif ($arg === 'all') {
                // 全添付ファイルを表示
                $this->options['all'] = true;
            } elseif ($arg === 'sort') {
                // ソート
                $this->options['sort'] = true;
            } else {
                $this->err = str_replace('$1', $arg, $_gallery_messages['err_invalid']);
                break;
            }
        }
        if ($this->options['all'] === true) $this->options['add'] = false;
    }

    /**
     * サムネイルの幅と高さを計算する
     *
     * @param array $size 0 = 幅 / 1 = 高さ
     * @return array 幅, 高さ, 単位
     */
    private function calculate_size(...$size): array
    {
        $width = $this->options['width'];
        $height = $this->options['height'];
        $unit = $this->options['unit'];

        if ($this->options['crop']) {
            // 切り抜き
            if ($unit == '%') {
                if ($height) $height = $width = round($size[1] * $height / 100, 2);
                else $width = $height = round($size[0] * $width / 100, 2);
                $unit = 'px';
            } else {
                if ($width && $height) {
                    $width = $height;
                } else {
                    $width = $width ?: $height;
                    $height = $height ?: $width;
                }
            }
        } elseif ($unit == '%') {
            // %指定
            $width = $width ?: $height;
            $height = $height ?: $width;
            $width = round($size[0] * $width / 100, 2);
            $height = round($size[1] * $height / 100, 2);
            $unit = 'px';
        } else {
            // px指定等
            $width = $width ?: round($size[0] * $height / $size[1], 2);
            $height = $height ?: round($size[1] * $width / $size[0], 2);
        }

        // サムネイルの最大サイズを元のサイズに制限
        if ($width > $size[0] || $height > $size[1]) {
            $width = $size[0];
            $height = $size[1];
        }

        return [$width, $height, $unit];
    }

    /**
     * 画像の情報を取得する
     *
     * @param string $src 画像ファイルの指定
     * @return array|null ファイルが存在しなければnullを返す
     */
    private function get_image_info($src): ?array
    {
        global $_gallery_messages;

        // 添付ページを取得
        if (strpos($src, '/') !== false) {
            $src = get_fullname($src, $this->page);
            preg_match('/(.+)\/(.+)/', $src, $m);
            $page = $m[1];
            $src = $m[2];
        } else {
            $page = $this->page;
        }
        // 幅と高さを取得
        $file = UPLOAD_DIR . encode($page) . '_' . encode($src);
        if (file_exists($file)) {
            $size = getimagesize($file);
            if (! preg_match(PLUGIN_GALLERY_AVAILABLE_FORMAT, $size[2])) {
                // 許可された画像フォーマット以外
                $this->err = str_replace('$1', $src, $_gallery_messages['err_mime']);
                return null;
            }
            return [
                'src' => $src,
                'page' => $page,
                'width' => $size[0],
                'height' => $size[1],
                'date' => get_date(DATE_RFC3339, filemtime($file))
            ];
        } else {
            $this->err = str_replace('$1', $src, $_gallery_messages['err_notfound']);
            return null;
        }
    }
}

/**
 * ギャラリーへの画像追加
 *
 * @var int MAX_FILESIZE アップロード可能な最大ファイルサイズ (キロバイト)
 * @property string $uploadto アップロード先のページ
 * @property string $err エラー内容
 */
class GalleryAction
{
    private const MAX_FILESIZE = 1024;
    private $err;

    /**
     * 動作を選択する
     *
     * @return array 操作画面
     */
    public function select_mode(): array
    {
        global $vars, $_gallery_messages;

        $is_page = is_page($vars['uploadto']);
        $nopage = str_replace('$1', htmlsc($vars['uploadto']), $_gallery_messages['err_nopage']);

        if ($vars['mode'] === 'add' && $is_page) return $this->show_upload_form();
        elseif (! $is_page) return ['msg', 'body' => '<p>' . $nopage . '</p>'];
        else return ['msg', 'body'];
    }

    /**
     * 画像追加フォームの表示
     *
     * @return array 画像追加フォーム
     */
    private function show_upload_form(): array
    {
        global $vars, $_gallery_messages;

        $current_page = $vars['refer'];
        $upload_page = $vars['uploadto'];

        // 編集権限をチェック
        check_editable($upload_page);

        $max_kb = number_format(self::MAX_FILESIZE);
        $id = htmlsc($vars['id']);
        $insert_to = htmlsc($vars['insert_to']);
        $file = $_FILES['attach_file'];

        // 画像追加用フォーム
        $body = <<<EOD
        <div style="margin:24px 12px">
            <span class="small">{$_gallery_messages['msg_maxsize']}：$max_kb KB</span>
            <form enctype="multipart/form-data" method="post">
                <input type="hidden" name="cmd" value="gallery">
                <input type="hidden" name="mode" value="add">
                <input type="hidden" name="page" value="$upload_page">
                <input type="hidden" name="id" value="$id">
                <input type="hidden" name="insert_to" value="$insert_to">
                <input type="file" name="attach_file"  accept="image/jpeg, image/png, image/gif, image/webp">&nbsp;
                <label>{$_gallery_messages['label_cap']}：<input type="text" name="caption"></label>
                <button name="upload">{$_gallery_messages['label_upload']}</button>
            </form>
        </div>
        EOD;

        if (isset($file) && is_uploaded_file($file['tmp_name'])) {
            // mime-typeとサイズをチェック
            if (! $this->is_valid_file($file)) {
                return ['msg' , 'body' => '<p>' . $this->err . '</p>' . $body];
            }
            // アップロードされた画像を保存
            $attach_name = encode($upload_page) . '_' . encode($file['name']);
            if (! file_exists(UPLOAD_DIR . $attach_name)) {
                move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $attach_name);
            }

            // 追加する行を作成
            $new_line = $file['name'];
            if (! empty($vars['caption'])) {
                $new_line .= PLUGIN_GALLERY_SEPARATOR . $vars['caption'];
            }

            // ページ内容を書き換え
            if ($current_page) $this->add_new_item($new_line, $current_page, $id, $insert_to);

            // 処理が終わったら元のページに戻る
            $uri = get_page_uri($current_page);
            header("Location: " . $uri);
        }

        return ['msg' => $_gallery_messages['label_add'], 'body' => $body];
    }

    /**
     * ファイルがアップロードに適しているかチェック
     *
     * @param array $file 添付されたファイルの情報
     * @return boolean
     */
    private function is_valid_file($file): bool
    {
        global $_gallery_messages;

        // 最大サイズ
        if ($file['size'] > self::MAX_FILESIZE * 1000) {
            $this->err = $_gallery_messages['msg_sizeover'];
            return false;
        }
        // フォーマット
        if(! preg_match(PLUGIN_GALLERY_AVAILABLE_FORMAT, (string)exif_imagetype($file['tmp_name']))) {
            $this->err = $_gallery_messages['msg_mime'];
            return false;
        }

        return true;
    }

    /**
     * ギャラリーに新しい行を追加する
     *
     * @param string $new_line 追加する行
     * @param string $current_page 書き換えるページ
     * @param string $id 書き換え対象のID
     * @param string $insert_to 画像を追加する方向
     * @return void
     */
    private function add_new_item($new_line, $current_page, $id, $insert_to): void
    {
        // 対象を取得
        $source = get_source($current_page, true, true);
        preg_match_all("/\n#gallery(\(.*?\))?({{[\s\S]+?}})?/", $source, $m, PREG_OFFSET_CAPTURE);

        // 書き換え
        $target = $m[0][$id][0];
        if ($target) {
            if (! $m[2][$id][0]) $target .= "{{\n}}";
            if ($insert_to == 'bottom') {
                $search = "\n}}";
                $new_line = "\n$new_line" . $search;
            } else {
                $search = "{{\n";
                $new_line = $search . "$new_line\n";
            }
            $replace = str_replace($search, $new_line, $target);
            $postdata = substr_replace($source, $replace, $m[0][$id][1], strlen($m[0][$id][0]));
            page_write($current_page, $postdata, false);
        }
    }

}