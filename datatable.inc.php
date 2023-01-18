<?php
/**
 * ソートや検索機能のあるテーブルを作成するプラグイン
 *
 * @version 1.0.1
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * -- Update --
<<<<<<< HEAD
 * 2023-01-18 v1.0.1 simple-datatablesのバージョンを5.x.xに固定 (一時的な対処)
=======
 * 2022-01-18 v1.0.1 simple-datatablesのバージョンを5.x.xに固定 (一時的な対処)
>>>>>>> 72713577d9ecd5088bc3f0ff34aad869c1421b48
 * 2022-02-03 v1.0.0 初版作成
 */

// CDN
define('DATATABLE_CDN_CSS', '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@5/dist/style.css">');
define('DATATABLE_CDN_JS', '<script src="https://cdn.jsdelivr.net/npm/simple-datatables@5"></script>');
// 日本語化
define('DATATABLE_TRANSLATE_LABELS', true);
// ----- DataTablesのデフォルト設定 -----
// ソート (DataTableのデフォルト: true)
define('DATATABLE_ENABLE_SORT', true);
// 検索 (DataTableのデフォルト: true)
define('DATATABLE_ENABLE_SEARCH', true);
// ページング (DataTableのデフォルト: true)
define('DATATABLE_ENABLE_PAGING', false);
// 1ページに表示するデータ件数 (DataTableのデフォルト: 10)
define('DATATABLE_PER_PAGE', 10);
// 表示数の選択肢 (DataTableのデフォルト: 5|10|15|20|25)
define('DATATABLE_PER_PAGE_SELECT', '5|10|25|50|100');

/**
 * 初期化
 */
function plugin_datatable_init()
{
    global $head_tags, $_datatable_messages;

    array_push($head_tags, DATATABLE_CDN_CSS, DATATABLE_CDN_JS);
    $_datatable_messages = [
        'msg_unknown'   => '#datatable Error: Unknown argument -> ',
        'msg_no_table'  => '#datatable Error: Could not find any tables.',
        'label_search'  => 'キーワード',
        'label_perpage' => '{select}件を表示',
        'label_norows'  => 'データがありません',
        'label_info'    => '{rows}件中{start}-{end}件を表示'
    ];
}

/**
 * ブロック型
 */
function plugin_datatable_convert()
{
    global $_datatable_messages;

    $args = func_get_args();
    $table = preg_replace("/\r|\r\n/", "\n", array_pop($args));
    $table = convert_html($table);
    if (strpos($table, '<table') === false) return '<p>' . $_datatable_messages['msg_no_table'] . '</p>';

    $dt = new PluginDatatable($table);
    $dt->args_to_options($args);
    if ($dt->err) return '<p>' . $_datatable_messages['msg_unknown'] . $dt->err . '</p>';

    $body = $dt->datatable_convert();
    return $body;
}

/**
 * テーブル作成用クラス
 */
Class PluginDatatable
{
    public $err;
    private static $t_counts;
    private $datatable;
    private $json;

    public function __construct($table)
    {
        self::$t_counts++;
        $this->datatable = str_replace('<table', '<table id="datatable' . self::$t_counts . '"', $table);
    }

    /**
     * 引数からオプション用の配列を作成する
     *
     * @param array $args テーブル部分を除いた引数
     * @return void
     */
    public function args_to_options($args)
    {
        $dop = new DatatableOptions();
        if (! empty($args)) $dop->convert_options($args);
        if ($dop->err) {
            $this->err = $dop->err;
        } else {
            $options = $dop->get_options();
            if ($options !== null) {
                // JSON形式にエンコード
                $encode = JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
                $this->json = ', ' . "\n" . json_encode($dop->get_options(), $encode) . "\n";
            } else {
                $this->json = null;
            }
        }

    }

    /**
     * 出力するHTMLを作成
     *
     * @return string $body 最終的なHTML
     */
    public function datatable_convert()
    {
        $id = self::$t_counts;
        $datatable = $this->datatable;
        $json = $this->json;
        $body = <<<EOD
$datatable
<script>
    var dataTable = new simpleDatatables.DataTable("#datatable$id"$json);
</script>
EOD;
        return $body;
    }
}

/**
 * DataTablesオプション関連クラス
 */
Class DatatableOptions
{
    private $options;
    private $availables;
    public $err;

    public function __construct()
    {
        $op = &$this->options;

        if (! DATATABLE_ENABLE_SORT) $op['sortable'] = false;
        if (! DATATABLE_ENABLE_SEARCH) $op['searchable'] = false;
        if (! DATATABLE_ENABLE_PAGING) $op['paging'] = false;

        // 利用可能なDataTablesのオプションとデフォルト値
        $this->availables = [
            'firstLast'     => false,
            'fixedColumns'  => false,
            'fixedHeight'   => false,
            'footer'        => false,
            'header'        => true,
            'hiddenHeader'  => false,
            'nextPrev'      => true,
            'paging'        => DATATABLE_ENABLE_PAGING,
            'perPage'       => 10,
            'perPageSelect' => '5|10|15|20|25',
            'scrollY'       => '',
            'sortable'      => DATATABLE_ENABLE_SORT,
            'searchable'    => DATATABLE_ENABLE_SEARCH,
        ];

        // 各ラベルを日本語化
        if (DATATABLE_TRANSLATE_LABELS) $this->translate_labels();
    }

    /**
     * JSONエンコード用に整形済みのオプションを渡す
     *
     * @return array $options オプションの配列
     */
    public function get_options()
    {
        if (empty($this->options)) return null;
        return $this->options;
    }

    /**
     * 引数を判別してオプション用に整形する
     *
     * @param array $args テーブル部分を除いた引数
     * @return void
     */
    public function convert_options($args)
    {
        $op = &$this->options;

        foreach ($args as $arg) {
            $arg = htmlsc($arg);

            if (preg_match('/^(string|number|date(=.+)?|hide|\|)/i', $arg)) {
                // columns
                $this->cols_setting($arg);
            } elseif (preg_match('/^no(.+)$/', $arg, $matches)) {
                // デフォルトで有効のオプションを無効化
                switch ($matches[1]) {
                    case 'sort':
                    case 'search':
                        $op[$matches[1]. 'able'] = false;
                        break;
                    default:
                        if (array_key_exists($matches[1], $this->availables)) {
                            if ($this->availables[$matches[1]] === true) $op[$matches[1]] = false;
                            else continue;
                        } else {
                            $this->err = $arg;
                            break 2;
                        }
                }
            } elseif (array_key_exists($arg, $this->availables)) {
                // デフォルトで無効のオプションを有効化
                if ($this->availables[$arg] === false) $op[$arg] = true;
                else continue;
            } elseif (strpos($arg, '=') !== false) {
                // key=val 形式でのオプション指定
                list($key, $val) = explode('=', $arg, 2);
                if ($this->is_available($key)) {
                    // デフォルトと同じなら無視
                    if ($this->is_default($key, $val)) continue;
                    // true / false をboolean型にキャスト
                    if ($val === 'true' || $val === 'false') $val = (bool)$val;
                    // perPageSelectの指定を配列に変換
                    if ($key == 'perPageSelect') $val = $this->str_to_array($val);
                    $op[$key] = $val;
                } else {
                    $this->err = $arg;
                    break;
                }
            } else {
                $this->err = $arg;
                break;
            }
        }
        // perPageとperPageSelectのデフォルト設定を反映
        if ($op['paging']) $this->set_paging_options();
    }

    /**
     * ラベルの日本語化 (labelsオプション)
     *
     * @return void
     */
    private function translate_labels()
    {
        global $_datatable_messages;

        $this->options['labels'] = [
            'placeholder' => $_datatable_messages['label_search'],
            'perPage'     => $_datatable_messages['label_perpage'],
            'noRows'      => $_datatable_messages['label_norows'],
            'info'        => $_datatable_messages['label_info']
        ];
    }

    /**
     * 列ごとのソート設定 (columnsオプション)
     *
     * @param string $arg ソート形式の指定
     * @return void
     */
    private function cols_setting($arg)
    {
        $op = &$this->options;
        $keys = explode('|', $arg);

        for ($i =0; $i < count($keys); $i++) {
            $key = $keys[$i];
            $col = [];
            // select: 列数
            $col['select'] = $i;
            if (preg_match('/^(string|number)$/i', $key)) {
                // type: string / number
                $key = strtolower($key);
                $col['type'] = $key;
            } elseif (preg_match('/^date(?:=(.+))?$/i', $key, $matches)) {
                // type: date, format=日付のフォーマット (e.g. YY/MM/DD)
                $col['type'] = 'date';
                if (isset($matches[1])) {
                    $col['format'] = $matches[1];
                }
            } elseif (preg_match('/^hide$/i', $key)) {
                $col['hidden'] = true;
            } else {
                $col['sortable'] = false;
            }
            $op['columns'][] = $col;
        }
    }

    /**
     * perPageとperPageSelectのデフォルト設定を反映
     *
     * @return void
     */
    private function set_paging_options()
    {
        $op = &$this->options;
        $perPage = DATATABLE_PER_PAGE;
        $perPageSelect = DATATABLE_PER_PAGE_SELECT;

        foreach (['perPage', 'perPageSelect'] as $key) {
            if (! isset($op[$key]) && ! ($this->availables[$key] == $$key)) {
                if ($key == 'perPageSelect') $$key = $this->str_to_array($$key);
                $op[$key] = $$key;
            }
        }
    }

    /**
     * perPageSelectの指定を配列に変換
     *
     * @param string $val perPageSelectの指定
     * @return array $pps 配列に直したperPageSelectの指定
     */
    private function str_to_array($val)
    {
        if (strpos($val, '|') === false) return false;
        $pps = explode('|', $val);
        return $pps;
    }

    /**
     * 利用可能なオプションか判別する
     *
     * @param string $key 引数で指定されたオプションのキー
     * @return boolean
     */
    private function is_available($key)
    {
        return array_key_exists($key, $this->availables);
    }

    /**
     * オプションの指定がデフォルト値と同じか判別する
     *
     * @param string $key 引数で指定されたオプションのキー
     * @param string $val 引数で指定されたオプションの値
     * @return boolean
     */
    private function is_default($key, $val)
    {
        return $val === $this->availables[$key];
    }

}
