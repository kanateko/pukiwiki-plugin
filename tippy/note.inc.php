<?php
/**
 * ホバーorタップで注釈を表示するプラグイン
 *
 * @version 0.3
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license http://www.gnu.org/licenses/gpl.ja.html GPL
 * -- Update --
 * 2021-11-25 v0.3 脚注/注釈のアンカーに飛んだ際、上に余白ができるよう調整
 * 2021-11-24 v0.2 脚注から本文に戻るリンクを追加
 *            v0.1 初版作成
 */

// tippy.js関連クラスの読み込み
require_once('tippy.php');

// 番号のフォーマット
define('NOTE_INDEX_FORMAT', '*%d');
// キーワードと注釈のセパレータ
define('NOTE_KEYWORD_SEPARATOR', ':');

function plugin_note_init()
{
    tippy_init();
}

function plugin_note_convert()
{
    return;
}

function plugin_note_inline()
{
    $args = func_get_args();
    $note = new PluginNote($args);
    return $note->error ?: $note->convert_note_to_tooltip();
}

/**
 * ツールチップの作成
 */
class PluginNote extends Tippy
{
    private $note;
    private $keyword;
    private $comment;
    private $note_no;
    private static $notes = array();
    private static $note_count = 0;
    private static $id = 0;
    private static $loaded = array();

    public $msg = array(
        'usage'     => ' &amp;note(){&lt;keyword&gt;:&lt;comment&gt;}; ',
        'unknown'   => ' &amp;note Error: Unknown argument. -> ',
        'undefined' => ' &amp;note Error: Undefined keyword. -> ',
    );

    public function __construct($args)
    {
        if (func_num_args() == 0) return $this->msg['usage'] . ' ';
        $this->note = array_pop($args);
        // キーワードの検索と追加
        $this->search_keywords($this->note);
        // オプション判別
        if ($args) $this->set_tippy_options($args);
    }

    /**
     * キーワードの検索と追加
     */
    private function search_keywords($note)
    {
        $notes =& self::$notes;
        $separator = NOTE_KEYWORD_SEPARATOR;

        // キーワードと注釈に分解
        if (strpos($note, $separator) !== false) {
            // keyword + comment or comment only
            list($keyword, $comment) = explode($separator, $note, 2);
            if (empty($keyword)) $keyword = $comment;
        } else {
            // keyword only
            $keyword = $note;
        }

        // キーワードの検索
        if (! isset($notes[$keyword]) && empty($comment)) {
            $this->error = $this->msg['undefined'] . $keyword . ' ';
        } else {
            if (! isset($notes[$keyword])) {
                $notes[$keyword] = array(++self::$note_count => $comment);
            }
            foreach ($notes[$keyword] as $i => $val) {
                $this->keyword = $keyword;
                $this->comment = $val;
                $this->note_no = $i;
            }
        }
    }

    /**
     * ツールチップの作成
     */
    public function convert_note_to_tooltip()
    {
        global $foot_explain;

        $fcount_base = 10000;  // PukiWikiの脚注が$foot_explainで使う分を予約
        $note_no = $this->note_no;
        $note_index = str_replace('%d', $this->note_no, NOTE_INDEX_FORMAT);
        $tag = substr(md5($this->keyword), 0, 10);
        $cfg = self::const_tippy_props($this->options['props']);

        // ツールチップ表示用スクリプト
        if (isset(self::$loaded[$tag])) {
            // 重複を無くす
            $script = '';
        } else {
            $script = <<<EOD
                document.addEventListener('DOMContentLoaded', () => {
                    tippy('.note-$tag', {
                        content: '<p>$this->comment</p><div class="note-link"><a href="#note_foot_$note_no">脚注{$note_index}へ</a></div>',
                        $cfg
                    });
                });
            EOD;
            self::$loaded[$tag] = true;

            //フッタに脚注を追加
            $foot_explain[$fcount_base + $note_no] = '<li id="note_foot_' . $note_no . '"><a href="#note_text_' . self::$id . '">^</a>' . $this->comment . '</li>';
        }

        $html = '<span class="note-anchor" id="note_text_' . self::$id++ . '"></span><a href="#" class="note_super plugin-note note-' . $tag . '">' .  $note_index . '</a>';
        if ($script) {
            $html .= '<script>' . preg_replace('/\s{2,}/', ' ', preg_replace('/\r|\n|\r\n/', ' ', $script)) .'</script>';
        }

        return $html;
    }
}

?>
