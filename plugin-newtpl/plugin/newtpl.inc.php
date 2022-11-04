<?php
/**
 * フォーム形式のページテンプレートプラグイン 配布版
 *
 * @version 1.1.2
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * @todo 非同期バリデーション + プレビュー
 * -- Updates --
 * 2022-11-04 v1.1.2 文字数カウントがマルチバイト文字に対応していなかった問題を修正
 * 2022-10-28 v1.1.1 編集制限時は管理者パスワードではなくログインを求めるように変更
 * 2022-10-27 v1.1.0 ファイル添付機能を追加
 *                   設定ページの凍結の要/不要を切り替える機能を追加
 *                   細かいバグを修正
 * 2022-10-12 v1.0.5 ページ名の相対指定に対応
 *                   rangeのスライダーに数値の表記を追加
 * 2022-10-11 v1.0.0 初版作成
 */

// スタイルシート
define('PLUGIN_NEWTPL_CSS', SKIN_DIR . 'css/newtpl.css');
// テンプレートの親ページ
define('PLUGIN_NEWTPL_ROOT', ':config/plugin/newtpl/');
// 管理者のみ
define('PLUGIN_NEWTPL_ADMINONLY', false);
// 設定ページの凍結の要/不要
define('PLUGIN_NEWTPL_RESTRICT', false);
// 添付可能なファイルのmime-type (カンマ区切り)
define('PLUGIN_NEWTPL_AVAILABLE_FORMAT', 'image/jpeg,image/png,image/gif,image/webp');
// 添付可能なファイルの最大サイズ (キロバイト)
define('PLUGIN_NEWTPL_MAX_FILESIZE', 1024);

// 連携プラグインの読み込み
require_once(PLUGIN_DIR . 'newpage.inc.php');

/**
 * 初期丘
 *
 * @return void
 */
function plugin_newtpl_init(): void
{
    global $head_tags;

    $msg['_newtpl_messages'] = [
        'msg_template'   => '以下のテンプレートを利用できます。',
        'msg_notpl'      => '利用可能なテンプレートがありません。',
        'msg_tplform'    => 'テンプレート：$1',
        'label_pagename' => 'ページ名',
        'label_auth'     => '管理者パスワード',
        'label_maxsize'  => '最大サイズ：$1 KB',
        'warn_used'      => 'ページ名 "$1" は既に使用されています。',
        'warn_wrongpass' => 'パスワードが間違っています。',
        'warn_pagename'  => 'ページ名が長すぎます。 (max: $1 bytes)',
        'warn_character' => 'ページ名に使用できない文字が含まれています。',
        'warn_length'    => '文字数制限を超えています。 ($1)',
        'warn_range'     => '文字数制限、あるいは値の制限範囲を超えています。($1)',
        'warn_required'  => '必須項目を記入してください。($1)',
        'warn_size'      => 'ファイルが最大サイズを超えています。($1)',
        'warn_format'    => 'ファイル形式が不正です。($1)',
        'btn_submit'     => 'ページを作成',
        'btn_back'       => '戻る',
        'err_noexist'    => '#newtpl Error: Failed to load the template. ($1)',
        'err_readonly'   => '#newtpl Error: PKWK_READONLY Enabled.',
        'err_token'      => '#newtpl Error: Invalid token. click <a href="$1">here</a> for refresh manually.',
        'err_freeze'     => '#newtpl Error: Restrict mode enabled. Need to freeze config pages before load them.'
    ];
    set_plugin_messages($msg);

    $head_tags[] = '<link rel="stylesheet" href="' . PLUGIN_NEWTPL_CSS . '?t=' . filemtime(PLUGIN_NEWTPL_CSS) . '">';
}

/**
 * ブロック型
 *
 * @return string テンプレート一覧
 */
function plugin_newtpl_convert(): string
{
    $list = new NewtplList;
    return $list->list_templates();
}

/**
 * アクション型
 *
 * @return array
 */
function plugin_newtpl_action(): array
{
    global $vars, $_msg_newpage;

    if (PKWK_READONLY) return ['msg' => $_msg_newpage, 'body' => Newtpl::get_message('err_readonly')];

    session_start();

    if ($vars['_submit']) {
        // ページの作成
        $cmd = new NewtplPage;
        if ($cmd->validation()) return $cmd->create_page();
        else return $cmd->show_form();
    } elseif ($vars['tpl']) {
        // フォームの表示
        $tplname = htmlsc(rawurldecode($vars['tpl']));
        $cmd = new NewtplForm($tplname);
        return $cmd->show_form();
    } else {
        // テンプレート一覧
        $cmd = new NewtplList;
        $body = plugin_newpage_convert() . "\n<br>\n" . $cmd->list_templates();
        return ['msg' => $_msg_newpage, 'body' => $body];
    }
}

/**
 * テンプレート一覧の表示
 */
class NewtplList
{
    /**
     * テンプレートの一覧を表示する
     *
     * @return string テンプレート一覧
     */
    public function list_templates(): string
    {
        global $vars;

        $refer = $vars['page'] ?: $vars['refer'];
        $templates = $this->get_templates();
        $body = Newtpl::get_message('msg_template') . "\n";

        $list = '';
        foreach ($templates as $tpl) {
            if (preg_match('/\/page$/', $tpl)) continue;
            $pattern = '/' . preg_quote(PLUGIN_NEWTPL_ROOT, '/') . '([^\/]+)/';
            preg_match($pattern, $tpl, $m);
            if ($m[1] !== null) {
                $base_uri = get_base_uri();
                $e_tplname = rawurlencode($m[1]);
                $list .= '<li><a href="' . $base_uri . '?cmd=newtpl&tpl=' . $e_tplname . '&refer=' . $refer . '">' . $m[1] . '</a></li>' . "\n";
            }
        }

        if (! empty($list)) return "$body<ul class=\"list1 list-indent1\">\n$list\n</ul>\n";
        else return Newtpl::get_message('msg_notpl');
    }

    /**
     * テンプレート一覧の取得
     *
     * @return array $pages 設定した親ページ配下のページ一覧
     */
    private function get_templates(): array
    {
        $root = encode(PLUGIN_NEWTPL_ROOT);
        $dh = opendir(DATA_DIR);
        $pages = [];
        while (($file = readdir($dh)) !== false) {
            if (strpos($file, $root) !== false) {
                $pages[] = decode(str_replace('.txt', '', $file));
            }
        }
        closedir($dh);

        return $pages;
    }
}

/**
 * 入力フォームの表示
 *
 * @property string $msg タブの表示名
 * @property string $tplname テンプレ名
 * @property string $tplcfg テンプレの設定ページ
 * @property string $tplpage テンプレにするページ
 * @property string $notification 通知メッセージ
 */
class NewtplForm
{
    private $msg;
    private $tplname;
    private $tplcfg;
    private $tplpage;
    private $notification;
    private static $script = [];

    /**
     * コンストラクタ
     *
     * @param string $tplname テンプレ名
     */
    public function __construct($tplname, $notification = null)
    {
        $esc = htmlsc($tplname);
        $this->msg = Newtpl::get_message('msg_tplform', $esc, false);
        $this->tplname = $esc;
        $this->tplcfg = PLUGIN_NEWTPL_ROOT . $tplname;
        $this->tplpage = $this->tplcfg . '/page';
        $this->notification = $notification;

        if (! (is_page($this->tplcfg) && is_page($this->tplpage)))
            die_message(Newtpl::get_message('err_noexist', $this->tplname, false));
        elseif (PLUGIN_NEWTPL_RESTRICT && ! is_freeze($this->tplcfg) && is_freeze($this->tplpage))
            die_message(Newtpl::get_message('err_freeze'));
    }

    /**
     * フォームの作成と表示
     *
     * @return array 入力フォーム
     */
    public function show_form(): array
    {
        global $_newtpl_messages, $edit_auth, $auth_user, $vars;

        $refer = htmlsc($vars['refer']);

        // 編集制限時のログインチェック
        if ($edit_auth && ! $auth_user) {
            header('Location:' . get_base_uri() . '?plugin=loginform&pcmd=login&page=' . $refer);
            exit;
        }

        $items = Newtpl::parse_config($this->tplcfg);
        $token = $_SESSION['token'] = Newtpl::token(16);
        $names = ['page' => true, '_date' => true];
        $fields = '';
        foreach ($items as $item => $cfg) {
            // 不正項目をスキップ
            if (! (isset($cfg['type']) && isset($cfg['name']))) continue;
            if ($names[$cfg['name']]) continue;
            else $names[$cfg['name']] = true;

            // 項目の作成
            if ($cfg['type'] === 'hidden') {
                $fields .= '<input type="hidden" name="' . $cfg['name'] . '" value="' . $cfg['value'] . '">' . "\n";
            } else {
                switch ($cfg['type']) {
                    case 'text':
                    case 'textarea':
                        $field = $this->text($cfg);
                        break;
                    case 'number':
                    case 'range':
                        $field = $this->number($cfg);
                        break;
                    case 'radio':
                    case 'checkbox':
                        if (empty($cfg['option'])) break;
                        $field = $this->checkable($cfg);
                        break;
                    case 'select':
                        if (empty($cfg['option'])) break;
                        $field = $this->select($cfg);
                        break;
                    case 'file':
                        $field = $this->file($cfg);
                    default:
                        continue;
                }

                if (isset($cfg['desc'])) {
                    $desc = '<p class="newtpl-desc">' . make_link(htmlspecialchars_decode($cfg['desc'])) . '</p>';
                } else {
                    $desc = '';
                }
                $fields .= <<<EOD
                <fieldset class="newtpl-item" data-require="{$cfg['required']}">
                    <legend class="newtpl-label">$item</legend>
                    $desc
                    <div class="newtpl-post">
                        $field
                    </div>
                </fieldset>
                EOD;
            }
        }

        // 管理者パスワードの認証
        if (PLUGIN_NEWTPL_ADMINONLY) $auth = <<<EOD
            <fieldset class="newtpl-item" data-require="true">
                <legend class="newtpl-label">{$_newtpl_messages['label_auth']}</legend>
                <div class="newtpl-post">
                    <input type="password" name="_password" required>
                </div>
            </fieldset>
        EOD;
        else $auth = '';

        // フォーム全体
        $p_page = isset($vars['page']) ? htmlsc($vars['page']) : '';
        $body = <<<EOD
        {$this->notification}
        <form class="plugin-newtpl" method="post" enctype="multipart/form-data">
            <div class="newtpl-fields">
                <input type="hidden" name="token" value="$token">
                <input type="hidden" name="cmd" value="newtpl">
                <input type="hidden" name="refer" value="$refer">
                <input type="hidden" name="_tplname" value="{$this->tplname}">
                $auth
                <fieldset class="newtpl-item" data-require="true">
                    <legend class="newtpl-label">{$_newtpl_messages['label_pagename']}</legend>
                    <div class="newtpl-post">
                        <input type="text" name="page" value="$p_page" required>
                    </div>
                </fieldset>
                $fields
            </div>
            <div class="newtpl-button">
                <button type="submit" class="newtpl-submit" name="_submit" value="1">{$_newtpl_messages['btn_submit']}</button>
            </div>
        </form>
        EOD;

        return ['msg' => $this->msg, 'body' => $body];
    }

    /**
     * テキスト入力
     *
     * オプション：placeholder, default, max, required, desc, null, link
     *
     * @param array $cfg 項目の設定
     * @return string $field 項目のHTML
     */
    private function text($cfg): string
    {
        global $vars;

        $name = $cfg['name'];
        $holder = $cfg['placeholder'] ? ' placeholder="' . $cfg['placeholder'] . '"' : '';
        $max = $cfg['max'] > 0 ? ' maxlength="' . $cfg['max'] . '" data-max="' . $cfg['max'] . '"' : '';
        $require = $cfg['required'] === 'true' ? ' required' : '';

        if ($vars[$name]) {
            $default = $cfg['type'] === 'text' ? ' value="' . htmlsc($vars[$name]) . '"' : htmlsc($vars[$name]);
        } else {
            switch ($cfg['type']) {
                case 'text':
                    $default = $cfg['default'] ? ' value="' . $cfg['default'] . '"' : '';
                    break;
                case 'textarea':
                    $default = $cfg['default'] ?: '';
                    break;
                default:
                    break;
            }
        }

        if ($cfg['type'] === 'text') {
            $field = "<input type=\"text\" name=\"$name\"$holder$default$max$require>\n";
        } else {
            $field = "<textarea name=\"$name\"$holder$max$require>$default</textarea>\n";
        }

        return $field;
    }

    /**
     * 数値系
     *
     * オプション：default, min, max, step, required, desc, null(, link)
     *
     * @param array $cfg 項目の設定
     * @return string $field 項目のHTML
     */
    private function number($cfg): string
    {
        global $vars;

        $type = $cfg['type'];
        $name = $cfg['name'];
        $max = isset($cfg['max']) ? ' max="' . $cfg['max'] . '" data-max="' . $cfg['max'] . '"' : '';
        $min = isset($cfg['min']) ? ' min="' . $cfg['min'] . '" data-min="' . $cfg['min'] . '"' : '';
        $step = isset($cfg['step']) ? ' step="' . $cfg['step'] . '"' : '';
        $require = $cfg['required'] === 'true' ? ' required' : '';

        if ($vars[$name]) {
            $default = ' value="' . htmlsc($vars[$name]) . '"';
        } else {
            $default = $cfg['default'] ? ' value="' . $cfg['default'] . '"' : '';
        }

        $field = "<input type=\"$type\" name=\"$name\"$default$min$max$step$require>\n";

        if ($type === 'range') {
            $field .= "<i class=\"$name-val\"></i>\n";
            // rangeの数値表示用スクリプト
            if (! self::$script['range']) {
                self::$script['range'] = true;
                $field .= <<<EOD
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const rngs = document.querySelectorAll('.newtpl-post input[type="range"]');
                        for (const rng of rngs) {
                            showRangeValue(rng);
                            rng.addEventListener('input', () => {
                                showRangeValue(rng);
                            });
                        }
                    });
                    function showRangeValue(rng) {
                        const rngval = rng.value;
                        const display = rng.nextElementSibling;
                        display.innerText = rngval;
                    }
                </script>
                EOD;
            }
        }
        return $field;
    }

    /**
     * 選択項目
     *
     * オプション：default, option, required, desc, null, separator, link
     *
     * @param array $cfg 項目の設定
     * @return string $field 項目のHTML
     */
    private function checkable($cfg): string
    {
        global $vars;

        $type = $cfg['type'];
        $name = $type ==='radio' ? $cfg['name'] : $cfg['name'] . '[]';
        $options = explode('|', $cfg['option']);
        $require = $cfg['required'] === 'true' ? ' required' : '';

        $field = '';
        foreach ($options as $i => $option) {
            $id = $cfg['name'] . $i;
            if ($vars[$cfg['name']]) {
                switch ($type) {
                    case 'radio':
                        $check = $vars[$name] === $option ? ' checked' : '';
                        break;
                    case 'checkbox':
                        $post = array_flip($vars[$cfg['name']]);
                        $check = isset($post[$option]) ? ' checked' : '';
                        break;
                    default:
                        break;
                }
            } else {
                $check = $cfg['default'] === $option ? ' checked' : '';
            }
            $field .= "<input type=\"$type\" name=\"$name\" id=\"$id\" value=\"$option\"$check$require><label for=\"$id\">$option</label>\n";
        }

         // チェックボックス用のrequired切り替え用スクリプト
         if ($type === 'checkbox' && $require && ! self::$script['chk']) {
            self::$script['chk'] = true;
            $field .= <<<EOD
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const chks = document.querySelectorAll('.newtpl-post input[type="checkbox"]');
                    for (const chk of chks) {
                        toggleReq(chk);
                        chk.addEventListener('change', () => {
                            toggleReq(chk);
                        });
                    }
                });
                function toggleReq(chk) {
                    const group = document.querySelectorAll('input[name="' + chk.name + '"]');
                    const checked = document.querySelectorAll('input[name="' + chk.name + '"]:checked');
                    const stats = checked.length > 0 ? false : true;
                    for (const t of group) {
                        t.required = stats;
                    }
                }
            </script>
            EOD;
        }

        return $field;
    }

    /**
     * プルダウン
     *
     * オプション：default, option, required, desc, null, link
     *
     * @param array $cfg 項目の設定
     * @return string $field 項目のHTML
     */
    private function select($cfg): string
    {
        global $vars;

        $name = $cfg['name'];
        $options = explode('|', $cfg['option']);
        $require = $cfg['required'] === 'true' ? ' required' : '';

        $field = "<select name=\"$name\"$require>\n";
        foreach ($options as $option) {
            if ($vars[$name]) {
                $select = $vars[$name] === $option ? ' selected' : '';
            } else {
                $select = $cfg['default'] === $option ? ' selected' : '';
            }
            $field .= "<option value=\"$option\"$select>$option</option>\n";
        }
        $field .= "</select>\n";

        return $field;
    }

     /**
     * ファイル添付
     *
     * オプション：required, desc, null(, link)
     *
     * @param array $cfg 項目の設定
     * @return string $field 項目のHTML
     */
    private function file($cfg): string
    {
        $name = $cfg['name'];
        $size = Newtpl::get_message('label_maxsize', number_format(PLUGIN_NEWTPL_MAX_FILESIZE), false);
        $format = PLUGIN_NEWTPL_AVAILABLE_FORMAT;
        $require = $cfg['required'] === 'true' ? ' required' : '';

        $field = "<i class=\"small\">$size</i><br><input type=\"file\" name=\"$name\" accept=\"$format\"$require>\n";

        return $field;
    }
}

/**
 * 新規ページの作成
 *
 * @property string $err エラーor警告
 * @property string $pass 管理者パスワード
 * @property string $pagename ページ名
 * @property string $tplname テンプレ名
 * @property string $tplpage テンプレにするページ
 * @property array $items テンプレの項目と設定
 */
class NewtplPage
{
    public $err;
    private $pass;
    private $pagename;
    private $tplname;
    private $tplcfg;
    private $tplpage;
    private $items;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        global $vars;

        $this->tplname = htmlsc($vars['_tplname']);
        $this->tplcfg = PLUGIN_NEWTPL_ROOT . $this->tplname;
        $this->tplpage = $this->tplcfg . '/page';
        $this->pass = $vars['_password'];
        if (strpos($vars['page'], './') !== false) {
            // 相対パスを絶対パスに変換
            $this->pagename = get_fullname($vars['page'], $vars['refer']);
        } else {
            $this->pagename = $vars['page'];
        }
        if (! (is_page($this->tplcfg) && is_page($this->tplpage))) {
            die_message(Newtpl::get_message('err_noexist', $this->tplname, false));
        } else {
            $this->items = Newtpl::parse_config($this->tplcfg, true);
        }
    }

    /**
     * 入力内容の確認
     *
     * @return boolean
     */
    public function validation(): bool
    {
        global $vars, $edit_auth;

        // トークンの確認
        if (! isset($_SESSION['token']) || $_SESSION['token'] !== $vars['token']) {
            header('refresh:5');
            die_message(Newtpl::get_message('err_token', get_base_uri() . '?cmd=newtpl&tpl=' . $this->tplname, false));
        } else {
            $_SESSION['token'] = null;
        }

        // 入力内容の確認
        if (($edit_auth || PLUGIN_NEWTPL_ADMINONLY) && ! pkwk_login($this->pass)) {
            // 編集制限時のパスワード確認
            $this->err = Newtpl::get_message('warn_wrongpass');
            return false;
        } elseif (is_page($this->pagename)) {
            // ページ名の重複を確認
            $this->err = Newtpl::get_message('warn_used', $this->pagename);
            return false;
        } elseif (! is_pagename($this->pagename)) {
            // ページ名が不正でないか確認
            $this->err = Newtpl::get_message('warn_character');
            return false;
        } elseif (! is_pagename_bytes_within_soft_limit($this->pagename)) {
            // ページ名の長さを確認
            $this->err = Newtpl::get_message('warn_pagename', PKWK_PAGENAME_BYTES_SOFT_LIMIT);
            return false;
        } else {
            // 個別の設定を確認
            foreach ($this->items as $item => $cfg) {
                $type = $cfg['type'];
                $name = $cfg['name'];
                // 必須項目
                if ($cfg['required'] === 'true' && empty($vars[$name])) {
                    $this->err = Newtpl::get_message('warn_required', $item);
                    return false;
                }
                // 最大・最小
                if (isset($cfg['max']) || isset($cfg['min'])) {
                    switch ($type) {
                        case 'text':
                        case 'textarea':
                            $length = mb_strlen($vars[$name]);
                            if ($length > $cfg['max'] || $length < $cfg['min']) {
                                $this->err = Newtpl::get_message('warn_length', $item);
                                return false;
                            }
                            break;
                        case 'number':
                        case 'range':
                            if (! is_numeric($vars[$name]) || ($vars[$name] > $cfg['max'] || $vars[$name] < $cfg['min'])) {
                                $this->err = Newtpl::get_message('warn_range', $item);
                                return false;
                            }
                            break;
                        default:
                            break;
                    }
                }
                // 添付ファイル
                if ($type === 'file' && isset($_FILES[$name])) {
                    $file = $_FILES[$name];
                    if (is_uploaded_file($file['tmp_name'])) {
                        if ($file['size'] > PLUGIN_NEWTPL_MAX_FILESIZE * 1000) {
                            // 最大ファイルサイズ
                            $this->err = Newtpl::get_message('warn_size', $item);
                            return false;
                        } else {
                            // ファイル形式
                            $availables = str_replace(',', '|', preg_quote(PLUGIN_NEWTPL_AVAILABLE_FORMAT, '/'));
                            $availables = '/' . $availables . '/i';
                            $mime = getimagesize($file['tmp_name'])['mime'];
                            if (! preg_match ($availables, $mime)) {
                                $this->err = Newtpl::get_message('warn_format', $item);
                                return false;
                            }
                        }
                    }
                }
            }
        }

        return true;
    }

    /**
     * フォームの再表示
     *
     * @return array
     */
    public function show_form(): array
    {
        global $vars;

        $form = new NewtplForm($vars['_tplname'], $this->err);
        return $form->show_form();
    }

    /**
     * ページの作成
     *
     * @return void
     */
    public function create_page(): void
    {
        global $vars;

        $tpl = get_source($this->tplpage);
        if (empty($tpl)) die_message(Newtpl::get_message('err_noexist', null, false));
        foreach ($tpl as $i => $line) {
            if (preg_match('/^#(freeze|author)/', $line)) unset($tpl[$i]);
        }
        $tpl = implode($tpl);
        $basename = array_pop(explode('/', $this->pagename));

        // 予約済み
        $postdata = $this->replace('_page', $this->pagename, $tpl);
        $postdata = $this->replace('_pagelink', '[[' . $this->pagename . ']]', $postdata);
        $postdata = $this->replace('_base', $basename, $postdata);
        $postdata = $this->replace('_tpl', $this->tplcfg, $postdata);
        $postdata = $this->replace('_tpllink', '[[' . $this->tplcfg . ']]', $postdata);
        $postdata = $this->replace('_date', format_date(UTIME), $postdata);

        // 受け取った内容でテンプレートを置換
        foreach ($this->items as $item => $cfg) {
            $name = $cfg['name'];
            $type = $cfg['type'];

            if (($type === 'file' && empty($_FILES[$name]['name'])) || ($type !== 'file' && empty($vars[$name]))) {
                // 未入力の場合に表示する内容
                if (isset($cfg['null'])) $postdata = $this->replace($name, htmlspecialchars_decode($cfg['null']), $postdata);
                else $postdata = $this->replace($name, '', $postdata);
            } else {
                switch ($type) {
                    case 'file':
                        // 添付ファイル
                        $file = $_FILES[$name];
                        $attach_name = encode($this->pagename) . '_' . encode($file['name']);
                        if (! file_exists(UPLOAD_DIR . $attach_name)) {
                            move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $attach_name);
                        }
                        $postdata = $this->replace($name, $file['name'], $postdata);
                        break;
                    case 'checkbox':
                        // チェックボックス
                        if ($cfg['link'] === 'true') {
                            // 項目ごとにリンク化
                            foreach ($vars[$name] as $i => $val) {
                                if (is_page($val)) $vars[$name][$i] = '[[' . $vars[$name][$i] . ']]';
                            }
                        }
                        if (isset($cfg['separator'])) {
                            // 複数項目を表示する際のセパレータを変更
                            $separator = str_replace('\\s', ' ', htmlspecialchars_decode($cfg['separator']));
                            $separator = str_replace('\\n', "\r\n", $separator);
                            $vars[$name] = implode($separator, $vars[$name]);
                        } else {
                            $vars[$name] = implode(',', $vars[$name]);
                        }
                        $postdata = $this->replace($name, $vars[$name], $postdata);
                        break;
                    default:
                        // その他項目
                        if ($cfg['link'] === 'true' && is_page($vars[$name])) $vars[$name] = '[[' . $vars[$name] . ']]';
                        $postdata = $this->replace($name, $vars[$name], $postdata);
                        break;
                }
            }
            // 項目名を使用
            $postdata = $this->replace('title:' . $name, $item, $postdata);
        }

        // ページのファイルを作成
        page_write($this->pagename, $postdata);
        header('Location:' . get_page_uri($this->pagename));
        exit;
    }

    /**
     * テンプレートの置き換え
     *
     * @param string $name 置き換え用のアンカー
     * @param string $post 置き換える文字列
     * @param string $src 対象のページ内容
     * @return string 置き換え後のページ内容
     */
    private function replace($name, $post, $src): string
    {
        $name = '{{{' . $name . '}}}';
        if (strpos($src, $name) !== false) {
            return str_replace($name, $post, $src);
        } else {
            return $src;
        }
    }
}

/**
 * 汎用
 */
class Newtpl
{
    /**
     * メッセージの取得
     *
     * @param string $key 表示するメッセージの指定
     * @param string|null $replace メッセージの置き換え用
     * @param bool $is_body 本文中に表示するかどうか
     * @return string 表示するメッセージ
     */
    public static function get_message($key, $replace = null, $is_body = true): string
    {
        global $_newtpl_messages;

        if ($replace !== null) {
            $msg = str_replace('$1', $replace, $_newtpl_messages[$key]);
        } else {
            $msg = $_newtpl_messages[$key];
        }

        if ($is_body) return '<p>' . $msg . '</p>';
        else return $msg;
    }

    /**
     * 各項目と設定の取得
     *
     * @param string $tplcfg テンプレの設定ページ
     * @return array $items 入力項目と設定
     */
    public static function parse_config($tplcfg): array
    {
        $src = get_source($tplcfg);
        $src = array_map('trim', $src);
        $title = '';
        $items = [];
        foreach ($src as $line) {
            $line = htmlsc($line);
            if (preg_match('/^-([^\-]+(?<!\*))(\*)?$/', $line, $m)) {
                // 項目名
                $title = $m[1];
                if ($m[2]) $items[$title]['required'] = 'true';
                else $items[$title]['required'] = 'false';
            } elseif (preg_match('/^--([^\-]+)$/', $line, $m) && ! empty($title)) {
                // 各項目のオプション
                $m[1] = preg_replace('/\s+=\s+/', '=', $m[1]);
                [$key, $val] = explode('=', $m[1]);
                $items[$title][$key] = $val;
            } else {
                continue;
            }
        }

        return $items;
    }

    /**
     * トークンの生成
     *
     * @param int $bytes バイト数
     * @return string ランダムな文字列
     */
    public static function token($bytes): string
    {
        return bin2hex(openssl_random_pseudo_bytes($bytes));
    }
}