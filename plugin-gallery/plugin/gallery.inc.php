<?php
/**
 * photoswipe版 画像のギャラリー表示プラグイン (配布版)
 *
 * @version 2.1
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * -- Updates --
 * 2022-07-09 v2.1 nobreakオプションを追加
 * 2022-07-08 v2.0 Photoswipeのバージョンアップに対応
 *                 全体的にコードを改良
 *                 画像追加時、ページ上に全く同じギャラリーがあるとその全てが書き換えの対象になる問題を修正
 *                 キャプションでPukiWiki記法を利用可能に
 * 2022-05-18 v1.8 1.5.4のURLカスタマイズに対応
 *                 v1.7以前に使用していたURL短縮プラグインへの対応を終了
 * 2021-09-29 v1.7 ファイルとキャプションのセパレータを変更するための設定を追加
 * 2021-07-26 v1.6 対応する拡張子にwebpを追加 (一覧表示のみ)
 *                 初期化用コードの挿入部分を少し変更
 * 2021-07-11 v1.5 初期化用コードの呼び出しを1回のみに変更
 * 2021-07-01 v1.4 階層化されたページの添付ファイルを正常に表示できなかった問題を修正
 * 2021-06-22 v1.3 短縮URLを導入してあるかどうかで処理を変えるよう変更
 *                 一覧画像の高さ指定機能を追加
 *            v1.2 アップロード後元のページに戻る際にFatalErrorが出る問題を修正
 *                 一覧画像の高さが自動調整されなかった問題を修正
 * 2021-06-21 v1.1 画像の縁取り無効オプションを追加
 *                 切り抜きがsquareの場合はキャプションを表示するよう変更
 * 2021-06-20 v1.0 ページの編集権限をチェックする機能を追加
 *                 画像追加ボタンのデザインを調整
 *                 画像一覧の左寄せ・中央・右寄せを設定する機能を追加
 *                 画像一覧の画像を正方形・円に切り抜いて表示する機能を追加
 *                 画像一覧でキャプションを非表示にする機能を追加
 * 2021-06-19 v0.7 添付されたファイルのフォーマット判別を厳正化
 * 2021-06-18 v0.6 使用するライブラリをlightnox.jsからphotoswipe.jsに変更
 * 2021-06-17 v0.5 画像追加機能を追加
 *                 画像追加ボタンの表示/非表示切り替え機能を追加
 *            v0.2 他のページの添付画像を表示する機能を追加
 *                 画像の横幅を指定する機能を追加
 * 2021-06-16 v0.1 初版作成
 */

// Photoswipeの必要ファイル
define('PLUGIN_GALLERY_PSWP_CORE', 'https://unpkg.com/photoswipe/dist/photoswipe.esm.min.js');
define('PLUGIN_GALLERY_PSWP_LIGHTBOX', 'https://unpkg.com/photoswipe/dist/photoswipe-lightbox.esm.min.js');
define('PLUGIN_GALLERY_PSWP_CSS', 'https://unpkg.com/photoswipe/dist/photoswipe.css');
// PukiWiki用CSS
define('PLUGIN_GALLERY_CSS', SKIN_DIR . 'css/gallery.css');
// ファイルとキャプションのセパレータ
define('PLUGIN_GALLERY_SEPARATOR', '>');
// 対応フォーマット (参考：https://www.php.net/manual/ja/function.exif-imagetype.php)
// gif, jpg, png, webp
define('PLUGIN_GALLERY_AVAILABLE_FORMAT', '/[1-3]|18/');

function plugin_gallery_init(): void
{
    global $head_tags;

    $msg['_gallery_messages'] = [
        'err_invalid'  => '#gallery Error: Invalid argument. ($1)',
        'err_notfound' => '#gallery Error: File not Found. ($1)',
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
 * @var bool DEFALUT_ADD 追加ボタンの表示/非表示
 * @var bool DEFALUT_CAP キャプションの表示/非表示
 * @var string DEFALUT_BREAK 折り返しの有無
 * @var string DEFALUT_WRAP 画像の縁の有無
 * @var string DEFALUT_HEIGHT 画像サイズ (高さ)
 * @var string DEFALUT_UNIT サイズ指定の単位
 * @var string DEFALUT_PLACEMENT 画像の配置 (justify-content)
 * @property string $err エラー内容
 * @property bool $is_empty マルチライン部分が空か
 * @property string $page 現在のページ名
 * @property ?array $images 表示する画像の情報
 * @property ?array $options プラグインのオプション
 * @property int $id プラグインの呼び出し回数
 * @property bool $loaded スクリプトの読み込みフラグ
 */
class PluginGallery
{
    private const DEFAULT_ADD = true;
    private const DEFAULT_CAP = true;
    private const DEFAULT_BREAK = 'true';
    private const DEFAULT_WRAP = 'true';
    private const DEFAULT_HEIGHT = '180';
    private const DEFAULT_UNIT = 'px';
    private const DEFAULT_PLACEMENT = 'center';

    public $err;
    private $is_empty;
    private $page;
    private $images;
    private $options;
    private static $id = 0;
    private static $loaded = false;

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
        if (empty($args) || strpos(end($args), "\n") === false) {
            $this->is_empty = true;
        } else {
            $this->array_images(array_pop($args));
            if ($this->images === null || empty(array_filter($this->images))) $this->is_empty = true;
            if (! empty($args)) $this->array_options($args);
        }

        $this->options['add'] ??= self::DEFAULT_ADD;
        $this->options['break'] ??= self::DEFAULT_BREAK;
        $this->options['cap'] ??= self::DEFAULT_CAP;
        $this->options['placement'] ??= self::DEFAULT_PLACEMENT;
        $this->options['unit'] ??= self::DEFAULT_UNIT;
        $this->options['wrap'] ??= self::DEFAULT_WRAP;
    }

    /**
     * HTMLに変換して出力
     *
     * @return string $html
     */
    public function convert(): string
    {
        global $_gallery_messages;

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

                $href = get_base_uri() . '?plugin=attach&pcmd=open&file=' . $image['src'] . '&refer=' . rawurlencode($image['page']);
                $items .= <<<EOD
                <div class="gallery-item" id="galleryItem{$id}_$i"$cap_attr>
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
            if (! self::$loaded) {
                self::$loaded = true;
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
        }

        // 最終的な表示
        $add = $this->options['add'] ? '<a class="gallery-add" href="'
        . get_base_uri() . '?cmd=gallery&mode=add&page=' . $this->page . '&id=' . $id . '">'
        . $_gallery_messages['label_add'] . '</a>' : '';
        $placement = $this->options['break'] === 'false' ? 'left' : $this->options['placement'];
        $break = ' data-break="' . $this->options['break'] . '"';
        $crop = $this->options['crop'] ? ' data-crop="' . $this->options['crop'] . '"' : '';
        $wrap = ' data-wrap="' . $this->options['wrap'] . '"';

        $html = <<<EOD
        <div class="plugin-gallery">
            <div class="gallery-items" id="gallery$id" style="justify-content:$placement"$break$crop$wrap>
            $items
            </div>
            $add
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

        $lines = function($x) {
            foreach (explode("\n", $x) as $str) yield $str;
        };

        foreach ($lines($multiline) as $line) {
            [$src, $cap] = explode(PLUGIN_GALLERY_SEPARATOR, $line);
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
        global $_gallery_messages;

        foreach ($args as $arg) {
            $arg = htmlsc($arg);
            $arg = preg_replace('/^\s*(.+?)(?:\s*(=)\s*(.+))?\s*$/', '$1$2$3', $arg);
            if (preg_match('/^(left|center|right|space-(around|between|evenly)|(flex-)?(start|end))$/', $arg)) {
                // サムネイルの配置
                $this->options['placement'] = $arg;
            } elseif (preg_match('/^(circle|square)$/', $arg)) {
                // サムネイルの切り取り
                $this->options['crop'] = $arg;
            } elseif (preg_match('/^(width|height)=(\d+)(.+)?$/', $arg, $m)) {
                // サムネイルのサイズ
                $this->options[$m[1]] = $m[2];
                $this->options['unit'] = $m[3];
            } elseif (preg_match('/^(no)?(break|wrap)$/', $arg, $m)) {
                // 縁、折り返しの有無
                $this->options[$m[2]] = $m[1] ? 'false' : 'true';
            } elseif (preg_match('/^(no)?(add|cap)$/', $arg, $m)) {
                // キャプション、追加ボタンの有無
                $this->options[$m[2]] = $m[1] ? false : true;
            } else {
                $this->err = str_replace('$1', $arg, $_gallery_messages['err_invalid']);
                break;
            }
        }
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
        if (! $width && ! $height) {
            $height = self::DEFAULT_HEIGHT;
        }

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
                    $width ??= $height;
                    $height ??= $width;
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
                // jpg, png, gif, webp以外
                $this->err = str_replace('$1', $src, $_gallery_messages['err_mime']);
                return null;
            }
            return [
                'src' => $src,
                'page' => $page,
                'width' => $size[0],
                'height' => $size[1]
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
        global $vars;

        if ($vars['mode'] === 'add') return $this->show_upload_form();
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

        // 編集権限をチェック
        check_editable($vars['page']);

        $max_kb = number_format(self::MAX_FILESIZE);
        $file = $_FILES['attach_file'];

        // 画像追加用フォーム
        $body = <<<EOD
        <div style="margin:24px 12px">
            <span class="small">{$_gallery_messages['msg_maxsize']}：$max_kb KB</span>
            <form enctype="multipart/form-data" method="post">
                <input type="hidden" name="cmd" value="gallery">
                <input type="hidden" name="mode" value="add">
                <input type="hidden" name="page" value="{$vars['page']}">
                <input type="hidden" name="id" value="{$vars['id']}">
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
            $attach_name = encode($vars['page']) . '_' . encode($file['name']);
            if (! file_exists(UPLOAD_DIR . $attach_name)) {
                move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $attach_name);
            }

            // 追加する行を作成
            $new_line = $file['name'];
            if (! empty($vars['caption'])) {
                $new_line .= PLUGIN_GALLERY_SEPARATOR . $vars['caption'];
            }

            // ページ内容を書き換え
            $this->add_new_item($new_line);

            // 処理が終わったら元のページに戻る
            $uri = get_page_uri($vars['page']);
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
        // フォーマット (jpg, png, gif, webp)
        if(! preg_match(PLUGIN_GALLERY_AVAILABLE_FORMAT, getimagesize($file['tmp_name'])[2])) {
            $this->err = $_gallery_messages['msg_mime'];
            return false;
        }

        return true;
    }

    /**
     * ギャラリーに新しい行を追加する
     *
     * @param string $new_line 追加する行
     * @return void
     */
    private function add_new_item($new_line): void
    {
        global $vars;

        // 対象を取得
        $source = get_source($vars['page'], true, true);
        preg_match_all("/\n#gallery(\(.*?\))?({{[\s\S]+?}})?/", $source, $m, PREG_OFFSET_CAPTURE);

        // 書き換え
        $replace = $m[0][$vars['id']][0];
        if ($replace) {
            if (! $m[2][$vars['id']][0]) $replace .= "{{\n}}";
            $replace = str_replace("\n}}", "\n$new_line\n}}", $replace);
            $postdata = substr_replace($source, $replace, $m[0][$vars['id']][1], strlen($m[0][$vars['id']][0]));
            page_write($vars['page'], $postdata, false);
        }
    }
}
