<?php
namespace mailform\class;

use DateTime;

/**
 * 入力内容の確認と送信
 *
 * @property array $p_items 各項目と受け取った値
 * @property array $warn バリデーションに失敗した項目
 */
class Action
{
    private $p_items;
    private $warn;

    /**
     * コンストラクタ
     *
     * @param array $itemcfg 設定ページから読み込んだフォームの各要素
     */
    public function __construct($itemcfg)
    {
        $this->p_items = $this->get_posted_items($itemcfg);
    }

    /**
     * 各項目の設定とPOSTされた値を取得
     *
     * @return array $this->p_items
     */
    public function get_p_items()
    {
        return $this->p_items;
    }

    /**
     * 確認画面の表示
     *
     * @return string 確認画面
     */
    public function confirm_screen()
    {
        global $_mailform_messages;

        $t2h = new Template(PLUGIN_MAILFORM_TPL_DIR . 'confirm.tpl');

        extract($_mailform_messages);
        $arr = [
            'token' => $_SESSION['token'] = token(16),
            'btn_back' => $btn_back,
            'btn_submit' => $btn_submit,
            'msg' => $msg_confirm_succeed,
            'autoreply' => $msg_autoreply,
            'progress' => $msg_send_progress
        ];
        $t2h->replace($arr, $t2h->html);
        $t2h->replace_ex($this->p_items, $t2h->html);

        return $t2h->html;
    }

    /**
     * 各項目を検証する
     *
     * @uses $this->validate_format()
     * @uses $this->validate_range()
     * @uses $this->validate_required_items()
     * @uses $this->validate_length()
     * @return bool true: バリデーション成功 | false: 失敗
     */
    public function validation()
    {
        global $vars;

        $is_valid_token = $vars['token'] === $_SESSION['token'];
        $is_valid_format = $this->validate_format();
        $is_valid_range = $this->validate_range();
        $is_filled = $this->validate_required_items();
        $is_under_limit = $this->validate_length();

        return $is_valid_token && $is_valid_format && $is_valid_range && $is_filled && $is_under_limit;
    }

    /**
     * 問題のある項目の通知
     *
     * @return string エラー文
     */
    public function get_warn()
    {
        global $_mailform_messages;

        $msg = '';
        foreach ($this->warn as $key => $val) {
            $msg .= $msg ? '<br>' : '';
            $invalids = implode(', ', $val);
            $msg .= str_replace('$1', $invalids , $_mailform_messages['msg_confirm_' . $key]);
        }

        return $msg;
    }

    /**
     * 各項目とPOSTされた値を格納
     *
     * @param array $itemcfg 設定ページから読み込んだフォームの各要素
     * @return void
     */
    private function get_posted_items($itemcfg)
    {
        global $vars;

        $p_items = [];
        foreach ($itemcfg as $name => $info) {
            if (! isset($vars[$name])) continue;
            extract($info); // type, need, label, attrs
            extract($attrs); // name, need, max, start, end

            switch ($type) {
                case 'hidden':
                    $p_items[$name] = [
                        'type' => $type,
                        'hlabel' => $label,
                        'hname' => $name,
                        'hvalue' => esc($vars[$name])
                    ];
                    break;
                case 'email':
                    $vars[$name] = preg_replace("/\r|\r\n|\n/", '', $vars[$name]);
                case 'textarea':
                    $vars[$name] = preg_replace("/<br>|\r\n/", "\n", $vars[$name]);
                case 'checkbox':
                    if (is_array($vars[$name]) && ! empty($vars[$name])) $vars[$name] = implode(', ', $vars[$name]);
                default:
                    $p_items[$name] = [
                        'type' => $type,
                        'name' => $name,
                        'value' => esc($vars[$name]),
                        'label' => $label,
                        'need' => $need,
                        'max' => $max ? (int)$max : null,
                        'start' => $start ?: null,
                        'end' => $end ?: null
                    ];
                    if ($type === 'textarea') {
                        $p_items[$name]['value'] = str_replace("\n", '<br>', ($p_items[$name]['value']));
                    }
            }
        }

        return $p_items;
    }

    /**
     * 形式をチェック
     *
     * @see $this->validation()
     * @return bool true: バリデーション成功 | false: 失敗
     */
    private function validate_format()
    {
        $is_valid_email = true;
        foreach ($this->p_items as $name => $info) {
            if (($info['type'] === 'email' && ! filter_var($info['value'], FILTER_VALIDATE_EMAIL)) ||
                ($info['type'] === 'number' && ! is_numeric($info['value'])) ||
                (preg_match('/^(date|time|datetime-local)$/', $info['type']) && ! preg_match(get_format('pattern', $info['type']), $info['value']))) {
                    $this->warn['format'][] = '<span class="mailform-invalid" data-name="' . $name . '">' . $info['label'] . '</span>';
                    $is_valid_email = false;
            }
        }

        return  $is_valid_email;
    }

    /**
     * 値の範囲をチェック
     *
     * @see $this->validation()
     * @return bool true: バリデーション成功 | false: 失敗
     */
    private function validate_range()
    {
        $is_valid_range = true;
        foreach ($this->p_items as $name => $info) {
            switch ($info['type']) {
                case 'number':
                    $value = (int)$info['value'];
                    $start = $info['start'] ? (int)$info['start'] : null;
                    $end = $info['end'] ? (int)$info['end'] : null;
                    break;
                case 'date':
                case 'time':
                case 'datetime-local':
                    foreach (['value', 'start', 'end'] as $str) {
                        // UNIX時間に変換
                        if ($info[$str] === null) {
                            $$str = null;
                            continue;
                        }
                        $dt = new DateTime($info[$str]);
                        $$str = $dt->format('U');
                    }
                    break;
                default:
                    continue 2;
            }
            if ((isset($start) && $value < $start) || (isset($end) && $value > $end)) {
                    $this->warn['range'][] = '<span class="mailform-invalid" data-name="' . $name . '">' . $info['label'] . '</span>';
                    $is_valid_range = false;
            }
        }

        return  $is_valid_range;
    }

    /**
     * 必須項目が埋まっているかチェック
     *
     * @see $this->validation()
     * @return bool true: バリデーション成功 | false: 失敗
     */
    private function validate_required_items()
    {
        $is_filled = true;
        foreach ($this->p_items as $name => $info) {
            if ($info['need'] === 'required' && empty($info['value'])) {
                $this->warn['empty'][] = '<span class="mailform-invalid" data-name="' . $name . '">' . $info['label'] . '</span>';
                $is_filled = false;
            }
        }

        return $is_filled;
    }

    /**
     * 文字数制限内に収まっているかチェック
     *
     * @see $this->validation()
     * @return bool true: バリデーション成功 | false: 失敗
     */
    private function validate_length()
    {
        $is_under_limit = true;
        foreach ($this->p_items as $name => $info) {
            if (preg_match('/^(text|textarea|email)$/', $info['type'])) {
                if (mb_strlen($info['value']) > $info['max']) {
                    $this->warn['over'][] = '<span class="mailform-invalid" data-name="' . $name . '">' . $info['label'] . '</span>';
                    $is_under_limit = false;
                }
            }
        }

        return $is_under_limit;
    }
}