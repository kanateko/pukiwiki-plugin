<?php
namespace mailform\class;

use DateTime;

/**
 * 入力項目を個別に構築する
 *
 * 設定ページから拾ってきたパーツを元に項目名と各追加項目を作成する。
 *
 * @see  plugin_mailform_convert() (mailform.inc.php)
 * @uses mailform\class\Template
 * @property array  $itemcfg 設定ページから読み込んだフォームの各要素
 * @property string $tpl_path 追加項目のテンプレートがある場所
 */
class Component
{
    private $itemcfg;
    private $tpl_path;

    /**
     * コンストラクタ
     *
     * @param string $tpl_path 追加項目用のテンプレート
     * @param string $cfg 設定ページの名前
     */
    public function __construct($tpl_path, $cfg)
    {
        $this->parse_cfg($cfg);
        $this->tpl_path = $tpl_path;
    }

    /**
     * 設定ページからパースした各項目の設定を取得
     *
     * @return string 各項目の設定
     */
    public function get_itemcfg()
    {
        return $this->itemcfg;
    }

    /**
     * 追加項目の組み立て
     *
     * @return array $items 追加項目のHTML
     */
    public function get_items()
    {
        $items = [];
        foreach ($this->itemcfg as $name => $info) {
            extract($info); // type, label, need, attrs
            $required = $need === 'required' ? $need : '';

            $t2h = new Template($this->tpl_path . $type . '.tpl');
            $t2h->replace(['name' => $name], $t2h->html);

            switch ($type) {
                case 'hidden':
                    $items[$name] = ['hname' => $name, 'hvalue' => $attrs['value']];
                    continue 2;
                case 'textarea':
                case 'text':
                case 'email':
                case 'number':
                    $input = $this->text($t2h, $name, $attrs, $required);
                    break;
                case 'select':
                    $input = $this->select($t2h, $name,  $attrs, $required);
                    break;
                case 'radio':
                case 'checkbox':
                    $input = $this->checkable($t2h, $name, $attrs, $required);
                     break;
                case 'date':
                case 'time':
                case 'datetime-local':
                    $input = $this->datetime($t2h, $name, $attrs, $required);
                default:
                    continue;
            }
            $items[$name] = ['label' => $label, 'input' => $input, 'need' => $need];
        }

        return $items;
    }

    /**
     * テキスト or 複数行テキスト
     *
     * @param object $t2h テンプレート変換用
     * @param string $name name属性
     * @param array $attrs 属性
     * @param string $required required属性
     * @return string 追加項目のHTML
     */
    private function text($t2h, $name, $attrs, $required)
    {
        global $vars;

        // プレースホルダー, 初期値, 最大文字数
        extract($attrs);
        if (isset($vars[$name])) $value = esc($vars[$name]);

        $t2h->replace(['max' => $max], $t2h->html);
        $arr[] = [
            'holder' => $holder,
            'value' => $value,
            'required' => $required,
            'start' => $start,
            'end' => $end
        ];
        $t2h->replace_ex($arr, $t2h->html);

        return $t2h->html;
    }

    /**
     * プルダウンメニュー
     *
     * @param object $t2h テンプレート変換用
     * @param string $name name属性
     * @param array $attrs 属性
     * @param string $required required属性
     * @return string 追加項目のHTML
     */
    private function select($t2h, $name, $attrs, $required)
    {
        global $vars;

        // 選択肢 ("|" 区切り), 初期値
        extract($attrs);
        $options = explode('|', $s_options);

        foreach ($options as $op) {
            if (isset($vars[$name])) $init = esc($vars[$name]);
            $selected = trim($op) === trim($init) ? 'selected' : '';
            $arr[] = [
                'op' => $op,
                'selected' => $selected,
                'required' => $required
            ];
        }
        $t2h->replace_ex($arr, $t2h->html);

        return $t2h->html;
    }

    /**
     * ラジオボタン or チェックボックス
     *
     * @param object $t2h テンプレート変換用
     * @param string $name name属性
     * @param array $attrs 属性
     * @param string $required required属性
     * @return string 追加項目のHTML
     */
    private function checkable($t2h, $name, $attrs, $required)
    {
        global $vars;

        // 選択肢 ("|" 区切り), 初期値
        extract($attrs);
        $options = explode('|', $s_options);

        if (isset($vars[$name])) {
            if (is_array($vars[$name]) && ! empty($vars[$name])) $init = esc(implode(',', $vars[$name]));
            else $init = esc($vars[$name]);
        }

        foreach ($options as $i => $op) {
            $checked = strpos(trim($init), trim($op)) !== false ? 'checked' : '';
            $id = $name . '_' . $i;
            $arr[] = [
                'op' => $op,
                'id' => $id,
                'checked' => $checked,
                'required' => $required
            ];
        }
        $t2h->replace_ex($arr, $t2h->html);

        return $t2h->html;
    }

    /**
     * 日時
     *
     * @param object $t2h テンプレート変換用
     * @param string $name name属性
     * @param array $attrs 属性
     * @param string $required required属性
     * @return string 追加項目のHTML
     */
    private function datetime($t2h, $name, $attrs, $required)
    {
        global $vars;

        // 初期値, 最小, 最大
        extract($attrs);
        if (isset($vars[$name])) $value = esc($vars[$name]);

        $arr[] = [
            'value' => $value,
            'start' => $start,
            'end' => $end,
            'required' => $required
        ];
        $t2h->replace_ex($arr, $t2h->html);

        return $t2h->html;
    }

    /**
     * 設定ページから追加項目を取得
     *
     * @param string $cfg 設定ページの名前
     */
    private function parse_cfg($cfg)
    {
        global $_mailform_messages;

        $itemcfg = [];
        $src = get_source($cfg);

        // デフォルトの入力欄を追加
        extract($_mailform_messages);
        $src = [
            ':*' . $label_name . ',name|text,' . $pholder_name . ',,20',
            ':*' . $label_mail . ',mail|email,' . $pholder_mail,
            ...$src,
            ':*' . $label_subject . ',subject|text',
            ':*' . $label_body . ',body|textarea'
        ];

        // 設定ページを一行ずつ確認
        foreach ($src as $line) {
            $line = esc($line);
            if (preg_match('/^:(\*)?([^\|]+)\|(.+)$/', $line, $m)) {
                // dtを表示名とname属性に分割
                [$label, $name] = explode(',', $m[2], 2);
                $name = PLUGIN_MAILFORM_NAME_PREFIX . trim($name);
                // name属性が重複している場合はエラー
                if (isset($itemcfg[$name])) die_message(str_replace('$1', esc($name), $err_doubled));
                // 表示名が "*" で始まっていれば必須項目
                $itemcfg[$name]['need'] = $m[1] ? 'required' : 'optional';
                $itemcfg[$name]['label'] = trim($label);
                // ddを要素のタイプとその属性に分けて格納
                [$type, $option] = explode(',', $m[3], 2);
                $type = trim($type);
                $attrs = [];
                switch ($type) {
                    case 'hidden':
                        $attrs['value'] = $option;
                        break;
                    case 'text':
                    case 'textarea':
                    case 'email':
                    case 'number':
                        //テキスト系
                        [$attrs['holder'], $attrs['value'], $attrs['max'], $attrs['start'], $attrs['end']] = explode(',', $option, 5);
                        if (empty($attrs['max'])) {
                            if ($type === 'textarea') $attrs['max'] = PLUGIN_MAILFORM_MAX_TEXTAREA_LENGTH;
                            else $attrs['max'] = PLUGIN_MAILFORM_MAX_TEXT_LENGTH;
                        }
                        break;
                    case 'select':
                    case 'checkbox':
                    case 'radio':
                        // 選択系
                        [$attrs['s_options'], $attrs['init']] = explode(',', $option, 2);
                        break;
                    case 'date':
                    case 'time':
                    case 'datetime-local':
                        // 日時系
                        [$attrs['value'], $attrs['start'], $attrs['end']] = explode(',', $option, 3);
                        foreach ($attrs as $key => $val) {
                            if (! preg_match(get_format('pattern', $type), $val)) {
                                $dt = new DateTime(get_date('c'));
                                $dt->modify($val);
                                $attrs[$key] = $dt->format(get_format('format', $type));
                            }
                        }
                        break;
                    default:
                        die_message(str_replace('$1', $type, $err_unknown));
                }
                $itemcfg[$name]['type'] = $type;
                $itemcfg[$name]['attrs'] = array_map('trim', $attrs);
            } else {
                continue;
            }
        }
        $this->itemcfg = $itemcfg;
    }
}
