<?php
namespace mailform\class;

/**
 * フォーム内容からメールを作成して送信する
 *
 * メール本文に使う値はAction::get_posted_items()の時点でエスケープ済み。
 * バリデーションもAction::validation()で終えている。
 *
 * @see plugin_mailform_convert() (mailform.inc.php)
 */
class Mail
{
    /**
     * メールの基本部分を作成
     *
     * @param array $p_items 各項目のパーツとPOSTされた値
     * @return void
     */
    public static function send_mail($p_items)
    {
        global $vars, $_mailform_messages;

        extract($_mailform_messages);

        $body = "【送信日時】" . format_date(UTIME) . "\n";
        foreach ($p_items as $info) {
            if (empty($info['value'])) continue;
            if ($info['type'] === 'textarea') {
                $info['value'] = str_replace('<br>', "\n", $info['value']);
                $body .= $info['label'] ? "\n【{$info['label']}】\n{$info['value']}\n\n" : '';
            } else {
                $body .= $info['label'] ? "【{$info['label']}】{$info['value']}\n" : '';
            }
        }

        $to_admin = self::send_to_admin($body, $p_items);
        $to_user = $vars['autoreply'] ? self::autoreply($body, $p_items) : true;

        return $to_admin && $to_user;
    }

    /**
     * 管理者宛のメールを送信
     *
     * プラグインの設定 (定数) で指定されているメールアドレスに送信する。
     * hiddenで渡された値や送信者の情報を加える。
     *
     * @param string $body ベースとなるメール本文
     * @param array  $p_items $p_items 各項目のパーツとPOSTされた値
     * @return bool $result
     */
    private static function send_to_admin($body, $p_items)
    {
        global $vars;

        // 名前、件名、メールアドレスを変数に格納
        foreach (['name', 'subject', 'mail'] as $key) {
            $$key = $p_items[PLUGIN_MAILFORM_NAME_PREFIX . $key]['value'];
        }

        // 管理者用のメール内容を作成
        foreach ($p_items as $info) {
            if ($info['type'] === 'hidden') {
                $body = $info['hlabel'] ? "【{$info['hlabel']}】{$info['hvalue']}\n" . $body : $body;
            }
        }
        $page = esc($vars['page']);
        $url = get_page_uri($vars['page'], PKWK_URI_ABSOLUTE);
        $body = <<<EOD
        以下の内容でお問い合わせがありました。
        ------------------------------
        $body
        ------------------------------
        PAGE: $page
        URL: $url
        EOD;
        if (PLUGIN_MAILFORM_SUBMIT_USER_DATA) {
            $user_ip = $_SERVER['REMOTE_ADDR'];
            $user_ua = $_SERVER['HTTP_USER_AGENT'];
            $body = <<<EOD
            $body
            IP: $user_ip
            UA: $user_ua
            EOD;
        }
        // die_message(str_replace("\n", '<br>', $body));

        // メールの送信
        $to = PLUGIN_MAILFORM_ADMIN_ADDR;
        $headers = [
            'MIME-Version' => '1.0',
            'Content-Transfer-Encoding' => 'Base64',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'From' => mb_encode_mimeheader($name) . " <$mail>"
        ];
        mb_language("ja");
        $result = mb_send_mail($to, $subject, $body, $headers);

        return $result;
    }

    /**
     * 自動返信メールの送信
     *
     * @param string $body ベースとなるメール本文
     * @param array  $p_items $p_items 各項目のパーツとPOSTされた値
     * @return bool $result
     */
    private static function autoreply($body, $p_items)
    {
        global $_mailform_messages, $page_title;

        $to = $p_items[PLUGIN_MAILFORM_NAME_PREFIX . 'mail']['value'];
        $from = mb_encode_mimeheader($page_title) . '<' . PLUGIN_MAILFORM_AUTOREPLY_ADDR . '>';
        $subject = $_mailform_messages['msg_mail_subject'];
        $url = get_base_uri(PKWK_URI_ABSOLUTE);

        $body = <<<EOD
        以下の内容でお問い合わせを受け付けました。
        ------------------------------
        $body
        ------------------------------
        ※このメールは自動返信によって送信されています。
        サイトURL: $url
        EOD;

        // メールの送信
        $headers = [
            'MIME-Version' => '1.0',
            'Content-Transfer-Encoding' => 'Base64',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'From' => $from
        ];
        mb_language("ja");
        $result = mb_send_mail($to, $subject, $body, $headers);

        return $result;
    }

}