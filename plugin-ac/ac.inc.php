<?php
/**
 * 折りたたみ可能な見出しを作成するプラグイン
 *
 * @version 1.7.1
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * -- Updates --
 * 2025-10-22 v1.7.1 FontAwesomeへの依存を解消
 * 2025-10-04 v1.7.0 jQueryへの依存を解消
 * 2021-07-26 v1.6.0 プラグインの呼び出し毎に挿入されていたスクリプトを大幅に削減
 *            v1.5.0 h使用時に元々ある疑似要素と開閉アイコンが干渉しないよう、アイコン表示用の要素を追加
 * 2021-07-07 v1.4.0 全開閉ボタンにも状態に合わせてクラスを切り替える機能を追加
 *                   全開閉ボタンが連打できたバグを修正
 *                   全開閉ボタンの制御範囲の終了位置を作成する機能を追加
 * 2021-07-06 v1.3.0 全開閉ボタン作成機能を追加。設置位置以降の同階層にある全てのアコーディオンが対象となる
 * 2021-07-05 v1.2.0 ヘッダーに見出しを指定する場合はオプションで明示するように変更
 * 2021-07-04 v1.1.0 インライン型を追加
 * 2021-04-02 v1.0.0 初版作成
 */

// 折りたたみ時に変わりに表示するメッセージ
define('PLUGIN_AC_ALT_MESSAGE', '&#9652; クリック or タップで詳細を表示');
// オプションリスト
define('PLUGIN_AC_OPTION_LIST', '/^(end|all|h|open|alt)$/');

/**
 * ブロック型
 */
function plugin_ac_convert()
{
    $ac = new PluginAc;

    if (func_num_args() == 0) {
        return $ac->msg['usage'];
    }

    $args = func_get_args();

    // ヘッダーとコンテンツ
    if (func_num_args() > 1 && ! preg_match(PLUGIN_AC_OPTION_LIST, $args[0])) {
        $header = convert_html(array_shift($args));
        $ac->set_header($header);
    }
    if(!preg_match('/^(end|all)$/', $args[0])) {
        $contents = preg_replace("/\r|\r\n/", "\n", array_pop($args));
        $contents = convert_html($contents);
    }

    // オプション判別
    if ($args) {
        foreach ($args as $arg) {
            $arg = htmlsc($arg);
            switch ($arg) {
                case 'end':
                    return $ac->build_control_end();
                case 'all':
                    return $ac->build_control_button();
                    break;
                case 'h':
                    // no break
                case 'open':
                    // no break
                case 'alt':
                    $ac->set_options($arg);
                    break;
                default:
                    return $ac->msg['unknown'] . $arg;
            }
        }
    }

    // 本文とスクリプトを作成
    return $ac->build_accordion($contents);
}

/**
 * インライン型
 */
function plugin_ac_inline()
{
    $ac = new PluginAc;

    if (func_num_args() == 0) {
        return $ac->msg['usage'];
    }

    $args = func_get_args();

    // ヘッダーとコンテンツ
    if (func_num_args() > 1 && ! preg_match(PLUGIN_AC_OPTION_LIST, $args[0])) {
        $header = convert_html(array_shift($args));
        $ac->set_header($header);
    }
    $contents = array_pop($args);

    if (empty($contents)) {
        // コンテンツが空の場合はエラー
        return $ac->msg['empty'];
    }

    // オプション判別
    if ($args) {
        foreach ($args as $arg) {
            $arg = htmlsc($arg);
            switch ($arg) {
                case 'end':
                    // no break
                case 'all':
                    // no break
                case 'h':
                    return $ac->msg['incorrect'] . $arg;
                    break;
                case 'open':
                    // no break
                case 'alt':
                    $ac->set_options($arg);
                    break;
                default:
                    return $ac->msg['unknown'] . $arg;
            }
        }
    }

    // 本文とスクリプトを作成
    return $ac->build_accordion($contents);
}

/**
 * アコーディオン作成用クラス
 */
Class PluginAc
{
    private const SVG_ICONS = [
        'open' => '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px"><path d="M440-280h80v-160h160v-80H520v-160h-80v160H280v80h160v160ZM200-120q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-560H200v560Zm0-560v560-560Z"/></svg>',
        'close' => '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px"><path d="M280-440h400v-80H280v80Zm-80 320q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-560H200v560Zm0-560v560-560Z"/></svg>'
    ];
    private static $ac_counts = 0;
    private static $ac_ctrl_counts = 0;

    // メッセージ
    public $msg = array(
        'usage'     => '#ac Usage:<br>
                        &lt;title or header&gt;<br>
                        #ac([end,all,h,open,alt]){{<br>
                        &lt;ontents&gt;<br>
                        }}<br>
                        or<br>
                        &ac(&lt;title&gt;[,open,alt]){&lt;contents&gt;};',
        'unknown'   => '#ac Error: Unknown argument. -> ',
        'empty'     => '&ac; Error: The text area is empty.',
        'incorrect' => '$ac; Error: This option is not available for inline-type plugin. -> ',
    );

    // オプション
    private $options = array (
        'class'   => array(
            'header'   =>    'plugin-ac-header',
            'contents' =>    'plugin-ac',
            'alt'      =>    'plugin-ac-altmsg',
            'ctrl'     =>    'plugin-ac-ctrl',
        ),
        'display' => 'none',
        'alt'     => '',
        'header'  => '...',
        'open'    => false,
    );

    /**
     * ヘッダーの指定
     */
    public function set_header($header)
    {
        $header = preg_replace('/<\/?p>/', '', $header);
        $this->options['header'] = $header;
    }

    /**
     * オプションの判別
     */
    public function set_options($arg)
    {
        $op =& $this->options;
        switch ($arg) {
            case 'h':
                $this->set_header('');
                break;
            case 'open':
                // 初期状態を開いた状態にする
                $op['open'] .= true;
                $op['display'] = 'block';
                break;
            case 'alt':
                // 代わりの文章を表示する
                $op['alt'] = '<div class="' . $op['class']['alt'] . '" style="display:none">' . PLUGIN_AC_ALT_MESSAGE . '</div>';
        }
    }

    /**
     * アコーディオンを作成する
     */
    public function build_accordion($contents)
    {
        $header = $this->options['header'];
        $class = $this->options['class'];
        if (! empty($header)) {
            $header = '<div>' . $header . '</div>';
        }
        $id = 'ac-' . self::$ac_counts++;

        // 1回のみ挿入するスクリプト
        $script_once = '';
        $script_min = $this->build_minified_script(false);
        if (self::$ac_counts === 1) {
            $script_once = <<<EOD
            <script>
                document.addEventListener('DOMContentLoaded', function(){ $script_min });
            </script>
            EOD;
        }

        // オプションの有無によってによって挿入するスクリプト
        if ($this->options['open']) {
            $script_open = <<<EOD
            <script>
                document.addEventListener('DOMContentLoaded', function(){
                    var content = document.getElementById('$id');
                    if (content && content.previousElementSibling) {
                        content.previousElementSibling.classList.add('open');
                    }
                });
            </script>
            EOD;
        }

        $html = <<<EOD
        $header
        <div class="{$class['contents']}" id="$id" style="display:{$this->options['display']}">
        $contents
        </div>
        {$this->options['alt']}
        $script_open
        $script_once
        EOD;

        return $html;
    }

    /**
     * 全開閉用ボタンの作成
     */
    public function build_control_button()
    {
        $id = 'ac-c-' . self::$ac_ctrl_counts++;
        $class_ctrl = $this->options['class']['ctrl'];

        // 1回のみ挿入するスクリプト
        $script_once = '';
        $script_min = $this->build_minified_script(true);
        if (self::$ac_ctrl_counts === 1) {
            $script_once = <<<EOD
            <script>
                document.addEventListener('DOMContentLoaded', function(){ $script_min });
            </script>
            EOD;
        }

        $html = <<<EOD
        <div class="$class_ctrl" id="$id"><span>全て開く</span></div>
        $script_once
        EOD;

        return $html;
    }

    /**
     * 全開閉ボタンの制御範囲の終了位置を作成
     */
    public function build_control_end()
    {
        $html = '<div class="' . $this->options['class']['ctrl'] . ' ctrl-end" style="display:none"></div>';
        return $html;
    }

    /**
     * 一度だけ挿入するスクリプトを作成
     * (元々jQueryのhtml関数を使っていて、そのためにスクリプトを1行にまとめる必要があった)
     */
    private function build_minified_script($is_ctrl)
    {
        // 各クラス名を変数に格納
        foreach ($this->options['class'] as $key => $val) {
            ${"class_" . $key} = $val;
        }

        if (! $is_ctrl) {
            // 通常の折りたたみ開閉用スクリプト
            $icon_open = self::SVG_ICONS['open'];
            $icon_close = self::SVG_ICONS['close'];
            $base_script = <<<EOD
            (function(){
                var headerClass = '$class_header';
                var contentsClass = '$class_contents';
                var altClass = '$class_alt';

                if (!window.pluginAcSlide) {
                    window.pluginAcSlide = function(element, open) {
                        if (!element) { return; }

                        if (element._pluginAcAnimation) {
                            cancelAnimationFrame(element._pluginAcAnimation.id);
                            var runningHeight = parseFloat(window.getComputedStyle(element).height);
                            if (isNaN(runningHeight)) { runningHeight = 0; }
                            element.style.height = runningHeight + 'px';
                            element.style.display = 'block';
                            element.style.overflow = 'hidden';
                            element._pluginAcAnimation = null;
                        }

                        var computed = window.getComputedStyle(element);
                        var startHeight = computed.display === 'none' ? 0 : element.getBoundingClientRect().height;
                        if (isNaN(startHeight)) { startHeight = 0; }

                        var endHeight;
                        if (open) {
                            element.style.display = 'block';
                            endHeight = element.scrollHeight;
                        } else {
                            if (computed.display === 'none' && startHeight === 0) {
                                element.style.display = 'none';
                                element.style.height = '';
                                element.style.overflow = '';
                                return;
                            }
                            endHeight = 0;
                        }

                        if (startHeight === endHeight) {
                            element.style.display = open ? 'block' : 'none';
                            element.style.height = '';
                            element.style.overflow = '';
                            return;
                        }

                        element.style.overflow = 'hidden';
                        element.style.height = startHeight + 'px';

                        var duration = 500;
                        var startTime = null;

                        function ease(t) {
                            return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
                        }

                        var animation = {};

                        function finish() {
                            element.style.height = '';
                            element.style.overflow = '';
                            element.style.display = open ? 'block' : 'none';
                            element._pluginAcAnimation = null;
                        }

                        function step(timestamp) {
                            if (!startTime) { startTime = timestamp; }
                            var progress = Math.min((timestamp - startTime) / duration, 1);
                            var eased = ease(progress);
                            if (open) {
                                var dynamicHeight = element.scrollHeight;
                                if (dynamicHeight !== endHeight) {
                                    endHeight = dynamicHeight;
                                }
                            }
                            var current = startHeight + (endHeight - startHeight) * eased;
                            element.style.height = current + 'px';
                            if (progress < 1) {
                                animation.id = requestAnimationFrame(step);
                            } else {
                                finish();
                            }
                        }

                        animation.id = requestAnimationFrame(step);
                        animation.finish = finish;
                        element._pluginAcAnimation = animation;
                    };
                }

                Array.prototype.forEach.call(document.querySelectorAll('.' + contentsClass), function(content) {
                    var header = content.previousElementSibling;
                    if (!header) { return; }

                    if (!header.classList.contains(headerClass)) {
                        header.classList.add(headerClass);
                    }

                    if (!header.querySelector('.ac-icon__open')) {
                        var iconOpen = document.createElement('i');
                        var iconClose = document.createElement('i');
                        iconOpen.className = 'ac-icon__open';
                        iconClose.className = 'ac-icon__close';
                        iconOpen.innerHTML = '$icon_open';
                        iconClose.innerHTML = '$icon_close';
                        header.insertBefore(iconOpen, header.firstChild);
                        header.insertBefore(iconClose, header.firstChild);
                    }

                    var alt = content.nextElementSibling;
                    if (alt && alt.classList.contains(altClass)) {
                        alt.style.display = 'block';
                    }

                    if (!header.hasAttribute('data-plugin-ac-bound')) {
                        header.setAttribute('data-plugin-ac-bound', '1');
                        header.addEventListener('click', function() {
                            var isOpen = header.classList.toggle('open');
                            window.pluginAcSlide(content, isOpen);
                        });
                    }

                    if (window.getComputedStyle(content).display !== 'none') {
                        header.classList.add('open');
                        content.style.display = 'block';
                    } else {
                        header.classList.remove('open');
                        content.style.display = 'none';
                    }
                });
            })();
            EOD;
        } else {
            // 複数開閉用のスクリプト
            $base_script = <<<EOD
            (function(){
                var headerClass = '$class_header';
                var contentsClass = '$class_contents';
                var ctrlClass = '$class_ctrl';
                var openText = '全て開く';
                var closeText = '全て閉じる';

                if (!window.pluginAcSlide) {
                    window.pluginAcSlide = function(element, open) {
                        if (!element) { return; }

                        if (element._pluginAcAnimation) {
                            cancelAnimationFrame(element._pluginAcAnimation.id);
                            var runningHeight = parseFloat(window.getComputedStyle(element).height);
                            if (isNaN(runningHeight)) { runningHeight = 0; }
                            element.style.height = runningHeight + 'px';
                            element.style.display = 'block';
                            element.style.overflow = 'hidden';
                            element._pluginAcAnimation = null;
                        }

                        var computed = window.getComputedStyle(element);
                        var startHeight = computed.display === 'none' ? 0 : element.getBoundingClientRect().height;
                        if (isNaN(startHeight)) { startHeight = 0; }

                        var endHeight;
                        if (open) {
                            element.style.display = 'block';
                            endHeight = element.scrollHeight;
                        } else {
                            if (computed.display === 'none' && startHeight === 0) {
                                element.style.display = 'none';
                                element.style.height = '';
                                element.style.overflow = '';
                                return;
                            }
                            endHeight = 0;
                        }

                        if (startHeight === endHeight) {
                            element.style.display = open ? 'block' : 'none';
                            element.style.height = '';
                            element.style.overflow = '';
                            return;
                        }

                        element.style.overflow = 'hidden';
                        element.style.height = startHeight + 'px';

                        var duration = 500;
                        var startTime = null;

                        function ease(t) {
                            return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
                        }

                        var animation = {};

                        function finish() {
                            element.style.height = '';
                            element.style.overflow = '';
                            element.style.display = open ? 'block' : 'none';
                            element._pluginAcAnimation = null;
                        }

                        function step(timestamp) {
                            if (!startTime) { startTime = timestamp; }
                            var progress = Math.min((timestamp - startTime) / duration, 1);
                            var eased = ease(progress);
                            if (open) {
                                var dynamicHeight = element.scrollHeight;
                                if (dynamicHeight !== endHeight) {
                                    endHeight = dynamicHeight;
                                }
                            }
                            var current = startHeight + (endHeight - startHeight) * eased;
                            element.style.height = current + 'px';
                            if (progress < 1) {
                                animation.id = requestAnimationFrame(step);
                            } else {
                                finish();
                            }
                        }

                        animation.id = requestAnimationFrame(step);
                        animation.finish = finish;
                        element._pluginAcAnimation = animation;
                    };
                }

                Array.prototype.forEach.call(document.querySelectorAll('.' + ctrlClass), function(button) {
                    if (!button || button.hasAttribute('data-plugin-ac-ctrl-bound')) { return; }
                    button.setAttribute('data-plugin-ac-ctrl-bound', '1');

                    button.addEventListener('click', function() {
                        var span = button.querySelector('span');
                        var label = span ? span.textContent.trim() : '';
                        var willOpen = span ? label === openText : !button.classList.contains('open');

                        button.classList.toggle('open');

                        var headers = [];
                        var contents = [];
                        var node = button.nextElementSibling;

                        while (node && !(node.classList && node.classList.contains(ctrlClass))) {
                            if (node.classList && node.classList.contains(headerClass)) {
                                headers.push(node);
                                var content = node.nextElementSibling;
                                if (content && content.classList.contains(contentsClass)) {
                                    contents.push(content);
                                }
                            }
                            node = node.nextElementSibling;
                        }

                        if (willOpen) {
                            for (var i = 0; i < headers.length; i++) {
                                headers[i].classList.add('open');
                            }
                            for (var j = 0; j < contents.length; j++) {
                                window.pluginAcSlide(contents[j], true);
                            }
                            if (span) { span.textContent = closeText; }
                        } else {
                            for (var i2 = 0; i2 < headers.length; i2++) {
                                headers[i2].classList.remove('open');
                            }
                            for (var j2 = 0; j2 < contents.length; j2++) {
                                window.pluginAcSlide(contents[j2], false);
                            }
                            if (span) { span.textContent = openText; }
                        }
                    });
                });
            })();
            EOD;
        }

        $minified_script = preg_replace("/\r?\n/", ' ', $base_script);
        $minified_script = preg_replace('/\s+/', ' ', $minified_script);

        return $minified_script;
    }
}

