<?php
/**
* メールフォームを作成するプラグイン
*
* @version 1.0
* @author kanateko
* @link https://jpngamerswiki.com/?f51cd63681
* @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
* -- Updates --
* 2022-06-13 v1.0 初版作成
*/

// 管理者のメールアドレス
define('PLUGIN_MAILFORM_ADMIN_ADDR', 'admin@example.com');
// 自動返信の送信元メールアドレス
define('PLUGIN_MAILFORM_AUTOREPLY_ADDR', 'noreply@example.com');
// 設定ページ
define('PLUGIN_MAILFORM_CONFIG_PAGE', ':config/plugin/mailform');
// 設定ページが凍結されている場合のみ使用可能
define('PLUGIN_MAILFORM_RESTRICT_MODE', true);
// 利用者のデータを一緒に送信
define('PLUGIN_MAILFORM_SUBMIT_USER_DATA', true);
// テキストエリアの文字数制限
define('PLUGIN_MAILFORM_MAX_TEXTAREA_LENGTH', 1200);
// テキストエリア以外の文字数制限
define('PLUGIN_MAILFORM_MAX_TEXT_LENGTH', 80);
// 入力項目のname属性のプレフィックス
define('PLUGIN_MAILFORM_NAME_PREFIX', 'mf_');
// classディレクトリ
define('PLUGIN_MAILFORM_CLASS_DIR', PLUGIN_DIR . 'mailform/class/');
// templateディレクトリ
define('PLUGIN_MAILFORM_TPL_DIR', PLUGIN_DIR . 'mailform/template/');
// JavaScript
define('PLUGIN_MAILFORM_JS', '<script src="' . SKIN_DIR . 'js/mailform.js"></script>');
// CSS
define('PLUGIN_MAILFORM_CSS', '<link rel="stylesheet" href="' . SKIN_DIR . 'css/mailform.css">');

// クラスの読み込み
require_once(PLUGIN_MAILFORM_CLASS_DIR . 'component.class.php');
require_once(PLUGIN_MAILFORM_CLASS_DIR . 'template.class.php');
require_once(PLUGIN_MAILFORM_CLASS_DIR . 'action.class.php');
require_once(PLUGIN_MAILFORM_CLASS_DIR . 'mail.class.php');

use mailform\class\{Component, Template, Action, Mail};

/**
 * 初期化
 *
 * @return void
 */
function plugin_mailform_init()
{
    global $head_tags;

    $messages['_mailform_messages'] = [
        'btn_confirm'           =>    '確認する',
        'btn_submit'            =>    '送信する',
        'btn_back'              =>    '修正する',
        'msg_autoreply'         =>    '確認用メールを送信する',
        'msg_form_start'        =>    '必要事項を記入して確認ボタンを押してください。',
        'msg_form_notoken'      =>    '無効なセッションです。',
        'msg_confirm_succeed'   =>    '入力内容を確認して送信ボタンを押してください。',
        'msg_confirm_format'    =>    '形式が不正です。 ($1)',
        'msg_confirm_range'     =>    '値が決められた範囲を逸脱しています。 ($1)',
        'msg_confirm_empty'     =>    '必須項目を全て記入してください。 ($1)',
        'msg_confirm_over'      =>    '文字数が制限を超えています。 ($1)',
        'msg_send_succeed'      =>    '入力内容を送信しました。',
        'msg_send_failed'       =>    '送信に失敗しました。後ほどもう一度お試し下さい。',
        'msg_send_progress'     =>    '送信中...',
        'msg_mail_subject'      =>    'お問い合わせを受け付けました',
        'msg_return'            =>    '自動的に元のページに戻ります。',
        'label_name'            =>    'お名前',
        'label_mail'            =>    'メールアドレス',
        'label_subject'         =>    '件名',
        'label_body'            =>    'お問い合わせ内容',
        'pholder_name'          =>    '例) 鈴木 太郎',
        'pholder_mail'          =>    '例) info@example.com',
        'err_restrict'          =>    '#mailform Error: The config page should be frozen to use this plugin.',
        'err_once'              =>    '#mailform Error: This plugin can be used only once per page.',
        'err_nocfg'             =>    '#mailform Error: Could not find a config page. ($1)',
        'err_doubled'           =>    '#mailform Error: The name "$1" is already used.',
        'err_unknown'           =>    '#mailform Error: The type of "$1" is not supported.',
    ];
    set_plugin_messages($messages);
    header('X-Frame-Options: DENY');
    $head_tags = [...$head_tags, PLUGIN_MAILFORM_JS, PLUGIN_MAILFORM_CSS];
}

/**
 * ブロック型 (フォームの表示)
 *
 * @param array $args
 * @return $form フォーム
 */
function plugin_mailform_convert(...$args)
{
    global $vars, $auth_user, $_mailform_messages;
    static $loaded = false;

    session_start();

    $cfg = PLUGIN_MAILFORM_CONFIG_PAGE;
    // 1度のみ呼び出し可
    if ($loaded == false) $loaded = true;
    else return '<p>' . $_mailform_messages['err_once'] . '</p>';
    // 引数での設定ページ指定
    if (! empty($args)) $cfg .= '/' . esc($args[0]);
    // 設定ページが存在するか確認
    if (! is_page($cfg)) return '<p>' . str_replace('$1', $cfg, $_mailform_messages['err_nocfg']) . '</p>';
    // 制限モードの確認
    if (PLUGIN_MAILFORM_RESTRICT_MODE) {
        if (! is_freeze($cfg)) return '<p>' . $_mailform_messages['err_restrict'] . '</p>';
    }

    // フォームの作成
    $t2h = new Template(PLUGIN_MAILFORM_TPL_DIR . 'form.tpl');
    $t2h->replace(['btn_confirm' => $_mailform_messages['btn_confirm']], $t2h->html);
    // 設定ページから項目を構築
    $cmp = new Component(PLUGIN_MAILFORM_TPL_DIR . 'input/', $cfg);
    $items = $cmp->get_items();
    $t2h->replace_ex($items, $t2h->html);
    // 最終的なフォームの表示
    $form = $t2h->html;
    if ($auth_user) $form = make_pagelink($cfg) . "\n" . $form;

    // 確認、修正、送信
    if (isset($vars['action'])) {
        $act = new Action($cmp->get_itemcfg());
        if (! isset($_SESSION['token']) || $vars['token'] !== $_SESSION['token']) {
            // 無効なセッション (トークン不一致)
            header('refresh:5');
            $form = '<p>' . $_mailform_messages['msg_form_notoken'] . '</p>';
            $form .= '<p>' . $_mailform_messages['msg_return'] . '</p>';
        } elseif ($act->validation() === true) {
            // バリデーション成功
            unset($_SESSION['token']);
            if ($vars['action'] === 'confirm') {
                // 確認
                $form = $act->confirm_screen();
            } elseif ($vars['action'] === 'send') {
                // 送信
                if (Mail::send_mail($act->get_p_items())) $msg = $_mailform_messages['msg_send_succeed'];
                else $msg = $_mailform_messages['msg_send_failed'];
                header('refresh:5');
                $form = '<p>' . $msg . '</p>';
                $form .= '<p>' . $_mailform_messages['msg_return'] . '</p>';
            }
        } else {
            // バリデーション失敗
            $arr = [
                'token' => $_SESSION['token'] = token(16),
                'msg' => $act->get_warn()
            ];
            $t2h->replace($arr, $form);
            $form .= "\n" . '<script>markInvalids()</script>' . "\n";
        }
    } else {
        // 初期入力画面
        $arr = [
            'token' => $_SESSION['token'] = token(16),
            'msg' => $_mailform_messages['msg_form_start']
        ];
        $t2h->replace($arr, $form);
    }
    $form = preg_replace('/(\s){2,}/', '$1', $form);

    return $form;
}

/**
 * エスケープ用
 *
 * @param string $arg
 * @return string
 */
function esc($arg)
{
    return htmlspecialchars($arg, ENT_QUOTES, "UTF-8");
}

/**
 * トークンの生成
 *
 * @param int $bytes バイト数
 * @return string ランダムな文字列
 */

 function token($bytes)
 {
    return bin2hex(openssl_random_pseudo_bytes($bytes));
 }

  /**
  * 日付フォーマット
  *
  * @param string $key 必要なデータの種類
  * @param string $type inputの種類
  * @return string フォーマット or パターン
  */
  function get_format($key, $type) {
    $datetime = [
        'format' => [
            'date' => 'Y-m-d',
            'time' => 'H:i:s',
            'datetime-local' => 'Y-m-d\TH:i'
        ],
        'pattern' => [
            'date' => '/^(\d{4}(-\d{2}){2}$/',
            'time' => '/^\d{2}:\d{2}(\d{2})?$/',
            'datetime-local' => '/^\d{4}(-\d{2}){2}T\d{2}:\d{2}$/'
        ]
    ];

    return $datetime[$key][$type];
  }
