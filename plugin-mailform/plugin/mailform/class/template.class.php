<?php
namespace mailform\class;

/**
 * テンプレートからHTMLを作成する
 *
 * ひたすら繰り返し処理でプロパティに格納したテンプレートを書き換える。
 * テンプレート上の変数：{$<変数名>}
 * テンプレート上の関数：{foreach}<繰り返し出力する内容>{/foreach}
 *                       {if "<調べるキー>"}<キーが空でない場合に出力する内容>{/if}
 *
 * @see mailform\class\Form::convert()
 * @see mailform\class\Element::get_items()
 * @property string $html 使用するテンプレート → 最終的なHTML
 */
class Template
{
    public $html;

    /**
     * コンストラクタ
     *
     * @param string $template 使用するテンプレートのパス
     */
    public function __construct($template)
    {
        $this->html = file_get_contents($template);
    }

    /**
     * 参照渡しされたテンプレート上の変数を置き換える
     *
     * @param array  $arr 置き換える対象とその値の連想配列
     * @param string $html 対象となるテンプレート
     * @return void (参照渡しで直接書き換え)
     */
    public function replace($arr, &$html)
    {
        preg_match_all('/\{\$(.+?)\}/', $html, $m);
        foreach ($m[1] as $i => $key) {
            // テンプレート上の変数名と同じキーがあれば置き換え
            if (isset($arr[$key])) $html = str_replace($m[0][$i], $arr[$key], $html);
        }
    }

    /**
     * 参照渡しされたテンプレート上の関数を実行して置き換える
     *
     * @param array  $items 置き換える対象とその値の連想配列
     * @param string $html 対象となるテンプレート
     * @return void (参照渡しで直接書き換え)
     */
    public function replace_ex($items, &$html)
    {
        preg_match_all('/\{([^\s]+?)(\s"(.+?)")?\}([\s\S]+?)\{\/\1\}/', $html, $m);
        foreach ($m[4] as $i => $tpl) {
            $func = $m[1][$i];
            $func_name = 'replace_ex_' . $func;
            $replaced = '';

            switch ($func) {
                case 'foreach':
                    $this->$func_name($items, $tpl, $replaced);
                    break;
                case 'if':
                    $this->$func_name($items, $m[3][$i], $tpl, $replaced);
                    break;
                default:
                    continue;
            }
            $html = str_replace($m[0][$i], $replaced, $html);
        }
    }

    /**
     * 繰り返し処理部分の置き換え
     *
     * さらに関数が含まれている場合は再帰的に処理する
     *
     * @param array  $items 置き換える対象とその値の連想配列
     * @param string $tpl 対象となるテンプレート上の処理部分
     * @param string $replaced 置き換え後に表示する内容
     * @return void (参照渡しで直接書き換え)
     */
    private function replace_ex_foreach($items, $tpl, &$replaced)
    {
        foreach ($items as $arr) {
            $_tpl = $tpl;
            if (preg_match_all('/\{([^\s]+?)(\s"(.+?)")?\}([\s\S]+?)\{\/\1\}/', $_tpl, $m)) {
                foreach ($m[0] as $i => $inner_tpl) {
                    $this->replace_ex([$arr], $inner_tpl);
                    $_tpl = str_replace($m[0][$i], $inner_tpl, $_tpl);
                }
            }
            $this->replace($arr, $_tpl);
            $replaced .= $_tpl;
        }
    }

    /**
     * 条件部分の置き換え
     *
     * 条件として指定されたキーに値があれば置き換えを実行
     * なければ空白に置き換える
     *
     * @param array  $items 置き換える対象とその値の連想配列
     * @param string $key 条件として調べるキー
     * @param string $tpl 対象となるテンプレート上の処理部分
     * @param string $replaced 置き換え後に表示する内容
     * @return void (参照渡しで直接書き換え)
     */
    private function replace_ex_if($items, $key, $tpl, &$replaced)
    {
        foreach ($items as $arr) {
            if (! empty($arr[$key])) {
                $this->replace($arr, $tpl);
                $replaced = $tpl;
            }
        }
    }

}
