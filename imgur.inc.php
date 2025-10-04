<?php
declare(strict_types=1);

/**
 * imgurのメディアを表示するプラグイン (PHP 8+)
 *
 *
 * @version 1.0.0
 * @author kanateko
 * @link https://jpngamerswiki.com/?f51cd63681
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 * -- Updates --
 * 2025-10-03 1.0.0 初版作成
 */

function plugin_imgur_convert(mixed ...$args): string
{
    return plugin_imgur_render(false, ...$args);
}

function plugin_imgur_inline(mixed ...$args): string
{
    return plugin_imgur_render(true, ...$args);
}

function plugin_imgur_action()
{
    global $vars;

    $mode = isset($vars['mode']) ? (string)$vars['mode'] : '';
    if ($mode !== 'detect') {
        return ['msg' => 'imgur', 'body' => ''];
    }

    $mediaId = isset($vars['id']) ? trim((string)$vars['id']) : '';
    if ($mediaId === '' || preg_match('/^[A-Za-z0-9_]+$/', $mediaId) !== 1) {
        plugin_imgur_output_json(['status' => 'error', 'reason' => 'invalid_id'], 400);
    }

    $detected = plugin_imgur_detect_media($mediaId);
    if ($detected['status'] !== 'ok') {
        $statusCode = $detected['status'] === 'network_error' ? 503 : 404;
        plugin_imgur_output_json(['status' => 'error', 'reason' => $detected['status']], $statusCode);
    }

    $extension = $detected['extension'];
    $mediaType = $detected['mediaType'];
    if ($mediaType === 'gifv') {
        $extension = 'mp4';
        $mediaType = 'video';
    }

    plugin_imgur_output_json([
        'status' => 'ok',
        'extension' => $extension,
        'mediaType' => $mediaType,
    ]);
}

function plugin_imgur_render(bool $isInline, mixed ...$args): string
{
    $errors = [
        'id'  => '#imgur Error: imgurのハッシュが指定されていません。',
        'arg' => '#imgur Error: 引数の形式が正しくありません。',
        'ext' => '#imgur Error: 未対応の拡張子です。',
        'detect' => '#imgur Error: imgurメディアの拡張子を自動判別できませんでした。',
        'network' => '#imgur Error: imgurのメディア情報にアクセスできませんでした。サーバーのネットワーク設定やallow_url_fopenを確認してください。',
    ];

    if ($args === []) {
        return $errors['id'];
    }

    $hashOrId = trim((string)array_shift($args));
    $parsedReference = plugin_imgur_parse_media_reference($hashOrId);
    if ($parsedReference['status'] !== 'ok') {
        return $errors[$parsedReference['error']];
    }

    $mediaId = $parsedReference['mediaId'];
    $extension = $parsedReference['extension'];
    $mediaType = $parsedReference['mediaType'];
    $autoDetected = $parsedReference['autoDetected'];

    if ($mediaType === 'gifv') {
        $extension = 'mp4';
        $mediaType = 'video';
    }

    $dimensions = [];
    $alignment = null;
    $additionalClasses = [];
    $disableLink = false;
    $caption = null;

    foreach ($args as $arg) {
        $arg = trim((string)$arg);
        if ($arg === '') {
            continue;
        }

        if (in_array($arg, ['left', 'center', 'right'], true)) {
            $alignment = $arg;
            continue;
        }

        if (preg_match('/^(?:(\d+)(px|%)?)?x(?:(\d+)(px|%)?)?$/i', $arg, $matches)) {
            $widthValue = isset($matches[1]) ? $matches[1] : '';
            $heightValue = isset($matches[3]) ? $matches[3] : '';

            if ($widthValue === '' && $heightValue === '') {
                return $errors['arg'];
            }

            if ($widthValue !== '') {
                $widthUnit = isset($matches[2]) && $matches[2] !== '' ? strtolower($matches[2]) : 'px';
                $dimensions['width'] = $widthValue . $widthUnit;
            }

            if ($heightValue !== '') {
                $heightUnit = isset($matches[4]) && $matches[4] !== '' ? strtolower($matches[4]) : 'px';
                $dimensions['height'] = $heightValue . $heightUnit;
            }

            continue;
        }

        if (preg_match('/^(width|height)=(\d+)(px|%)?$/i', $arg, $matches)) {
            $property = strtolower($matches[1]);
            $unit = isset($matches[3]) && $matches[3] !== '' ? strtolower($matches[3]) : 'px';
            $dimensions[$property] = $matches[2] . $unit;
            continue;
        }

        if (strcasecmp($arg, 'nolink') === 0) {
            $disableLink = true;
            continue;
        }

        if (preg_match('/^class=(.+)$/i', $arg, $matches)) {
            $classNames = preg_split('/\s+/', trim($matches[1]));
            if ($classNames === false || $classNames === []) {
                return $errors['arg'];
            }

            foreach ($classNames as $className) {
                if ($className === '') {
                    continue;
                }
                if (preg_match('/^[A-Za-z0-9-]+$/', $className) !== 1) {
                    return $errors['arg'];
                }
                $additionalClasses[$className] = true;
            }
            continue;
        }

        if (strncasecmp($arg, 'cap=', 4) === 0) {
            $captionValue = trim(substr($arg, 4));
            if ($captionValue === '') {
                return $errors['arg'];
            }

            $caption = $captionValue;
            continue;
        }

        return $errors['arg'];
    }

    $mediaStyles = [];
    foreach ($dimensions as $property => $value) {
        $mediaStyles[] = sprintf('%s:%s', $property, $value);
    }
    $mediaStyleString = implode(';', $mediaStyles);
    $mediaStyleAttr = $mediaStyleString === ''
        ? ''
        : ' style="' . htmlspecialchars($mediaStyleString, ENT_QUOTES | ENT_SUBSTITUTE) . '"';

    $containerStyles = [];
    if (! $isInline) {
        $containerStyles[] = 'margin-bottom:24px';
    }

    if ($alignment !== null) {
        $containerStyles[] = $alignment === 'center'
            ? 'text-align:center'
            : sprintf('text-align:%s', $alignment);
    }

    $containerStyle = implode(';', $containerStyles);
    if ($containerStyle !== '' && substr($containerStyle, -1) !== ';') {
        $containerStyle .= ';';
    }

    $containerAttr = $containerStyle === ''
        ? ''
        : ' style="' . htmlspecialchars($containerStyle, ENT_QUOTES | ENT_SUBSTITUTE) . '"';

    $encodedId = rawurlencode($mediaId);
    $modalMarkup = '';
    $scriptMarkup = '';

    if ($caption !== null) {
        $scriptMarkup .= plugin_imgur_caption_style();
    }
    $content = '';
    $containerDataAttr = '';

    if ($autoDetected) {
        $lazyAttributes = [
            'data-plugin-imgur-lazy-container' => 'true',
            'data-plugin-imgur-media-id' => $mediaId,
            'data-plugin-imgur-encoded-id' => $encodedId,
            'data-plugin-imgur-inline' => $isInline ? '1' : '0',
            'data-plugin-imgur-disable-link' => $disableLink ? '1' : '0',
        ];

        if ($mediaStyleString !== '') {
            $lazyAttributes['data-plugin-imgur-media-style'] = $mediaStyleString;
        }

        if ($additionalClasses !== []) {
            $lazyAttributes['data-plugin-imgur-media-classes'] = implode(' ', array_keys($additionalClasses));
        }

        if ($caption !== null) {
            $lazyAttributes['data-plugin-imgur-caption'] = $caption;
        }

        if (! $disableLink) {
            $modalId = plugin_imgur_next_modal_id();
            $lazyAttributes['data-plugin-imgur-modal-id'] = $modalId;
            $scriptMarkup .= plugin_imgur_modal_script();
        }

        $containerDataAttr = plugin_imgur_build_data_attr($lazyAttributes);

        $content = plugin_imgur_loading_indicator() . '<span data-plugin-imgur-media-slot="true"></span>';
        if (! $disableLink) {
            $content .= '<span data-plugin-imgur-modal-slot="true"></span>';
        }

        $scriptMarkup .= plugin_imgur_lazy_style();
        $scriptMarkup .= plugin_imgur_lazy_script();
    } else {
        $mediaUrl = sprintf('https://i.imgur.com/%s.%s', $encodedId, $extension);
        $escapedUrl = htmlspecialchars($mediaUrl, ENT_QUOTES | ENT_SUBSTITUTE);

        if ($mediaType === 'image') {
            $classAttr = plugin_imgur_build_class_attr([], $additionalClasses);
            $imageTag = sprintf('<img src="%s" alt="" loading="lazy"%s%s />', $escapedUrl, $mediaStyleAttr, $classAttr);

            if ($disableLink) {
                $content = $imageTag;
            } else {
                $modalId = plugin_imgur_next_modal_id();
                $escapedModalId = htmlspecialchars($modalId, ENT_QUOTES | ENT_SUBSTITUTE);
                $triggerId = $modalId . '-trigger';
                $escapedTriggerId = htmlspecialchars($triggerId, ENT_QUOTES | ENT_SUBSTITUTE);
                $content = sprintf(
                    '<a href="#" id="%2$s" class="plugin-imgur-modal-trigger" data-plugin-imgur-open="%1$s">%3$s</a>',
                    $escapedModalId,
                    $escapedTriggerId,
                    $imageTag
                );
                $modalMarkup = plugin_imgur_build_modal_markup($modalId, $mediaUrl);
            }

            if ($caption !== null) {
                $content = plugin_imgur_wrap_with_caption($content, $caption, $isInline);
            }
        } else {
            $classAttr = plugin_imgur_build_class_attr([], $additionalClasses);
            $content = sprintf('<video src="%s" controls playsinline%s%s>', $escapedUrl, $mediaStyleAttr, $classAttr);
            $content .= 'Your browser does not support the video tag.';
            $content .= '</video>';
        }
    }

    $containerTag = $isInline ? 'span' : 'div';
    $containerClasses = ['plugin-imgur', 'imgur-embed'];
    if ($isInline) {
        $containerClasses[] = 'imgur-inline';
    }
    if ($autoDetected) {
        $containerClasses[] = 'imgur-lazy';
    }

    $containerClassAttr = plugin_imgur_build_class_attr($containerClasses, []);

    if ($modalMarkup !== '') {
        $content .= $modalMarkup;
        $scriptMarkup .= plugin_imgur_modal_script();
    }

    return $scriptMarkup . sprintf(
        '<%1$s%2$s%3$s%4$s>%5$s</%1$s>',
        $containerTag,
        $containerClassAttr,
        $containerAttr,
        $containerDataAttr,
        $content
    );
}

/**
 * @return array{status: 'ok', mediaId: string, extension: string, mediaType: string, autoDetected: bool}|array{status: 'error', error: 'id'|'arg'|'ext'}
 */
function plugin_imgur_parse_media_reference(string $reference): array
{
    $reference = trim($reference);
    if ($reference === '') {
        return ['status' => 'error', 'error' => 'id'];
    }

    if (preg_match('/^(?P<id>[A-Za-z0-9_]+)\.(?P<ext>[A-Za-z0-9]+)$/', $reference, $matches)) {
        $mediaType = plugin_imgur_resolve_media_type(strtolower($matches['ext']));
        if ($mediaType === '') {
            return ['status' => 'error', 'error' => 'ext'];
        }

        return [
            'status' => 'ok',
            'mediaId' => $matches['id'],
            'extension' => strtolower($matches['ext']),
            'mediaType' => $mediaType,
            'autoDetected' => false,
        ];
    }

    if (preg_match('/^[A-Za-z0-9_]+$/', $reference) === 1) {
        return [
            'status' => 'ok',
            'mediaId' => $reference,
            'extension' => '',
            'mediaType' => '',
            'autoDetected' => true,
        ];
    }

    if (filter_var($reference, FILTER_VALIDATE_URL) !== false) {
        $parts = parse_url($reference);
        if ($parts === false) {
            return ['status' => 'error', 'error' => 'arg'];
        }

        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        if ($scheme !== 'http' && $scheme !== 'https') {
            return ['status' => 'error', 'error' => 'arg'];
        }

        $host = isset($parts['host']) ? strtolower($parts['host']) : '';
        if ($host === '' || preg_match('/(^|\.)imgur\.com$/', $host) !== 1) {
            return ['status' => 'error', 'error' => 'arg'];
        }

        $path = $parts['path'] ?? '';
        $path = trim($path ?? '', '/');
        if ($path === '') {
            return ['status' => 'error', 'error' => 'arg'];
        }

        $segments = array_values(array_filter(explode('/', $path), static fn (string $segment): bool => $segment !== ''));
        if ($segments === []) {
            return ['status' => 'error', 'error' => 'arg'];
        }

        $filename = (string)end($segments);
        $filename = rawurldecode($filename);
        if ($filename === '') {
            return ['status' => 'error', 'error' => 'arg'];
        }

        if (preg_match('/^(?P<id>[A-Za-z0-9_]+)\.(?P<ext>[A-Za-z0-9]+)$/', $filename, $matchesFromUrl)) {
            $mediaTypeFromUrl = plugin_imgur_resolve_media_type(strtolower($matchesFromUrl['ext']));
            if ($mediaTypeFromUrl === '') {
                return ['status' => 'error', 'error' => 'ext'];
            }

            return [
                'status' => 'ok',
                'mediaId' => $matchesFromUrl['id'],
                'extension' => strtolower($matchesFromUrl['ext']),
                'mediaType' => $mediaTypeFromUrl,
                'autoDetected' => false,
            ];
        }

        if (preg_match('/^[A-Za-z0-9_]+$/', $filename) === 1) {
            return [
                'status' => 'ok',
                'mediaId' => $filename,
                'extension' => '',
                'mediaType' => '',
                'autoDetected' => true,
            ];
        }

        return ['status' => 'error', 'error' => 'arg'];
    }

    return ['status' => 'error', 'error' => 'arg'];
}

function plugin_imgur_build_class_attr(array $defaultClasses, array $additionalClasses): string
{
    $classes = [];

    foreach ($defaultClasses as $className) {
        $classes[$className] = true;
    }

    foreach (array_keys($additionalClasses) as $className) {
        $classes[$className] = true;
    }

    if ($classes === []) {
        return '';
    }

    $escaped = htmlspecialchars(implode(' ', array_keys($classes)), ENT_QUOTES | ENT_SUBSTITUTE);

    return ' class="' . $escaped . '"';
}

function plugin_imgur_build_data_attr(array $attributes): string
{
    if ($attributes === []) {
        return '';
    }

    $fragments = [];
    foreach ($attributes as $name => $value) {
        if ($value === null) {
            continue;
        }
        $fragments[] = sprintf(' %s="%s"', $name, htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE));
    }

    return implode('', $fragments);
}

function plugin_imgur_wrap_with_caption(string $mediaMarkup, string $caption, bool $isInline): string
{
    $wrapperClasses = ['plugin-imgur-media-wrapper'];
    if ($isInline) {
        $wrapperClasses[] = 'plugin-imgur-media-wrapper--inline';
    }

    $wrapperClassAttr = plugin_imgur_build_class_attr($wrapperClasses, []);
    $escapedCaption = htmlspecialchars($caption, ENT_QUOTES | ENT_SUBSTITUTE);

    return sprintf(
        '<span%1$s data-plugin-imgur-has-caption="1">%2$s<span class="plugin-imgur-caption">%3$s</span></span>',
        $wrapperClassAttr,
        $mediaMarkup,
        $escapedCaption
    );
}

function plugin_imgur_output_json(array $payload, int $statusCode = 200): void
{
    header('Content-Type: application/json; charset=utf-8');
    if (function_exists('http_response_code')) {
        http_response_code($statusCode);
    }

    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function plugin_imgur_next_modal_id(): string
{
    static $counter = 0;
    $counter++;

    return 'plugin-imgur-modal-' . $counter;
}

function plugin_imgur_build_modal_markup(string $modalId, string $mediaUrl): string
{
    $escapedId = htmlspecialchars($modalId, ENT_QUOTES | ENT_SUBSTITUTE);
    $escapedUrl = htmlspecialchars($mediaUrl, ENT_QUOTES | ENT_SUBSTITUTE);

    $containerStyle = implode('', [
        'display:none;',
        'position:fixed;',
        'inset:0;',
        'z-index:9999;',
        'padding:1.5rem;',
        'box-sizing:border-box;',
        'background:rgba(0,0,0,0.75);',
        'align-items:center;',
        'justify-content:center;',
    ]);

    $contentStyle = implode('', [
        'position:relative;',
        'display:block;',
        'overflow:auto;',
        'touch-action:none;',
        'max-width:100%;',
        'max-height:100%;',
    ]);

    $closeStyle = implode('', [
        'position:fixed;',
        'top:1.5rem;',
        'right:1.5rem;',
        'color:#fff;',
        'text-decoration:none;',
        'font-size:2rem;',
        'line-height:1;',
        'padding:0.25em;',
        'z-index:10000;',
    ]);

    $imageStyle = implode('', [
        'display:block;',
        'touch-action:none;',
        'user-select:none;',
        'cursor:zoom-in;',
    ]);

    $overlayStyle = implode('', [
        'position:absolute;',
        'inset:0;',
        'display:block;',
    ]);

    return sprintf(
        '<span id="%1$s" class="plugin-imgur-modal" role="dialog" aria-modal="true" aria-hidden="true" style="%2$s">'
        . '<a href="#" class="plugin-imgur-modal__overlay" data-plugin-imgur-close="%1$s" aria-hidden="true" style="%5$s"></a>'
        . '<span class="plugin-imgur-modal__content" role="document" style="%3$s">'
        . '<a href="#" class="plugin-imgur-modal__close" data-plugin-imgur-close="%1$s" aria-label="Close" data-plugin-imgur-focus="true" style="%4$s">&times;</a>'
        . '<img src="%6$s" alt="" loading="lazy" class="plugin-imgur-modal__image" style="%7$s" />'
        . '</span>'
        . '</span>',
        $escapedId,
        htmlspecialchars($containerStyle, ENT_QUOTES | ENT_SUBSTITUTE),
        htmlspecialchars($contentStyle, ENT_QUOTES | ENT_SUBSTITUTE),
        htmlspecialchars($closeStyle, ENT_QUOTES | ENT_SUBSTITUTE),
        htmlspecialchars($overlayStyle, ENT_QUOTES | ENT_SUBSTITUTE),
        $escapedUrl,
        htmlspecialchars($imageStyle, ENT_QUOTES | ENT_SUBSTITUTE)
    );
}

function plugin_imgur_modal_script(): string
{
    static $initialized = false;

    if ($initialized) {
        return '';
    }

    $initialized = true;

    $script = <<<'SCRIPT'
<script>(function(){
    if (window.pluginImgurModalInitialized) {
        return;
    }
    window.pluginImgurModalInitialized = true;

    function matchesSelector(element, selector) {
        if (!element) {
            return false;
        }

        var matcher = element.matches || element.msMatchesSelector || element.webkitMatchesSelector;
        if (matcher) {
            return matcher.call(element, selector);
        }

        return false;
    }

    function closest(element, selector) {
        var current = element;
        while (current && current.nodeType === 1) {
            if (matchesSelector(current, selector)) {
                return current;
            }
            current = current.parentElement;
        }
        return null;
    }

    var MIN_ZOOM_SCALE = 1;
    var MAX_ZOOM_SCALE = Number.POSITIVE_INFINITY;

    function clamp(value, min, max) {
        if (value < min) {
            return min;
        }
        if (value > max) {
            return max;
        }
        return value;
    }

    function ensureZoomState(modal) {
        if (!modal) {
            return null;
        }

        if (modal._pluginImgurZoomState) {
            return modal._pluginImgurZoomState;
        }

        var image = modal.querySelector('.plugin-imgur-modal__image');
        var content = modal.querySelector('.plugin-imgur-modal__content');
        if (!image || !content) {
            return null;
        }

        var state = {
            modal: modal,
            image: image,
            content: content,
            scale: 1,
            baseWidth: 0,
            baseHeight: 0,
            baseContentWidth: 0,
            baseContentHeight: 0,
            baseContentMaxWidth: content.style.maxWidth,
            baseContentMaxHeight: content.style.maxHeight,
            baseImageMaxWidth: image.style.maxWidth,
            baseImageMaxHeight: image.style.maxHeight,
            lastScale: 1,
            pointers: {},
            pinchStartDistance: 0,
            pinchStartScale: 1,
            dragPointerId: null,
            dragStartX: 0,
            dragStartY: 0,
            dragStartScrollLeft: 0,
            dragStartScrollTop: 0,
            dragging: false
        };

        function updateBaseSize() {
            if (!state.baseWidth || !state.baseHeight) {
                var imageRect = image.getBoundingClientRect();
                if (!state.baseWidth && imageRect.width) {
                    state.baseWidth = imageRect.width;
                }
                if (!state.baseHeight && imageRect.height) {
                    state.baseHeight = imageRect.height;
                }
                if (!state.baseWidth && image.naturalWidth) {
                    state.baseWidth = image.naturalWidth;
                }
                if (!state.baseHeight && image.naturalHeight) {
                    state.baseHeight = image.naturalHeight;
                }
            }

            if (!state.baseContentWidth || !state.baseContentHeight) {
                var contentRect = content.getBoundingClientRect();
                if (!state.baseContentWidth && contentRect.width) {
                    state.baseContentWidth = contentRect.width;
                }
                if (!state.baseContentHeight && contentRect.height) {
                    state.baseContentHeight = contentRect.height;
                }
            }
        }

        function updateCursor() {
            if (state.scale > 1) {
                var cursor = state.dragging ? 'grabbing' : 'grab';
                image.style.cursor = cursor;
                content.style.cursor = cursor;
            } else {
                image.style.cursor = 'zoom-in';
                content.style.cursor = 'zoom-in';
            }
        }

        function applyScale() {
            updateBaseSize();

            var width = state.baseWidth || image.naturalWidth || image.clientWidth;
            var height = state.baseHeight || image.naturalHeight || image.clientHeight;

            if (!width) {
                updateCursor();
                return;
            }

            if (state.scale <= 1) {
                image.style.width = '';
                image.style.height = '';
                image.style.maxWidth = state.baseImageMaxWidth;
                image.style.maxHeight = state.baseImageMaxHeight;
                content.scrollLeft = 0;
                content.scrollTop = 0;
                state.dragPointerId = null;
                state.dragging = false;
                state.lastScale = 1;
            } else {
                var previousScale = state.lastScale || 1;
                var centerX = content.scrollLeft + (content.clientWidth / 2);
                var centerY = content.scrollTop + (content.clientHeight / 2);

                image.style.maxWidth = 'none';
                image.style.maxHeight = 'none';
                image.style.width = (width * state.scale) + 'px';
                if (height) {
                    image.style.height = (height * state.scale) + 'px';
                } else {
                    image.style.height = '';
                }

                var ratio = previousScale > 0 ? (state.scale / previousScale) : 1;
                if (!isFinite(ratio) || ratio <= 0) {
                    ratio = 1;
                }

                if (content.clientWidth > 0) {
                    var newCenterX = centerX * ratio;
                    var targetScrollLeft = newCenterX - (content.clientWidth / 2);
                    if (!isFinite(targetScrollLeft)) {
                        targetScrollLeft = 0;
                    }
                    var maxScrollLeft = content.scrollWidth - content.clientWidth;
                    if (isFinite(maxScrollLeft) && maxScrollLeft >= 0) {
                        if (targetScrollLeft < 0) {
                            targetScrollLeft = 0;
                        } else if (targetScrollLeft > maxScrollLeft) {
                            targetScrollLeft = maxScrollLeft;
                        }
                    }
                    content.scrollLeft = targetScrollLeft;
                }

                if (content.clientHeight > 0) {
                    var newCenterY = centerY * ratio;
                    var targetScrollTop = newCenterY - (content.clientHeight / 2);
                    if (!isFinite(targetScrollTop)) {
                        targetScrollTop = 0;
                    }
                    var maxScrollTop = content.scrollHeight - content.clientHeight;
                    if (isFinite(maxScrollTop) && maxScrollTop >= 0) {
                        if (targetScrollTop < 0) {
                            targetScrollTop = 0;
                        } else if (targetScrollTop > maxScrollTop) {
                            targetScrollTop = maxScrollTop;
                        }
                    }
                    content.scrollTop = targetScrollTop;
                }
            }

            state.lastScale = state.scale;
            updateCursor();
        }

        state.applyScale = applyScale;

        state.reset = function () {
            state.scale = 1;
            state.pointers = {};
            state.pinchStartDistance = 0;
            state.pinchStartScale = 1;
            state.baseWidth = 0;
            state.baseHeight = 0;
            state.baseContentWidth = 0;
            state.baseContentHeight = 0;
            content.style.width = '';
            content.style.height = '';
            content.style.maxWidth = state.baseContentMaxWidth;
            content.style.maxHeight = state.baseContentMaxHeight;
            image.style.width = '';
            image.style.height = '';
            image.style.maxWidth = state.baseImageMaxWidth;
            image.style.maxHeight = state.baseImageMaxHeight;
            content.scrollLeft = 0;
            content.scrollTop = 0;
            state.dragPointerId = null;
            state.dragStartX = 0;
            state.dragStartY = 0;
            state.dragStartScrollLeft = 0;
            state.dragStartScrollTop = 0;
            state.dragging = false;
            state.lastScale = 1;
            updateCursor();
        };

        function pointerKeys() {
            return Object.keys(state.pointers);
        }

        function setPointer(event) {
            state.pointers[event.pointerId] = { x: event.clientX, y: event.clientY };
        }

        function removePointer(event) {
            delete state.pointers[event.pointerId];
        }

        function pointerValues() {
            return pointerKeys().map(function (id) {
                return state.pointers[id];
            });
        }

        function beginDrag(event) {
            if (state.scale <= 1) {
                state.dragPointerId = null;
                state.dragging = false;
                updateCursor();
                return;
            }

            state.dragPointerId = event.pointerId;
            state.dragStartX = event.clientX;
            state.dragStartY = event.clientY;
            state.dragStartScrollLeft = content.scrollLeft;
            state.dragStartScrollTop = content.scrollTop;
            state.dragging = true;
            updateCursor();
        }

        function updateDrag(event) {
            if (!state.dragging || state.dragPointerId !== event.pointerId) {
                return;
            }

            var deltaX = event.clientX - state.dragStartX;
            var deltaY = event.clientY - state.dragStartY;
            content.scrollLeft = state.dragStartScrollLeft - deltaX;
            content.scrollTop = state.dragStartScrollTop - deltaY;
        }

        function endDrag(event) {
            if (state.dragPointerId !== event.pointerId) {
                return;
            }

            state.dragPointerId = null;
            state.dragging = false;
            updateCursor();
        }

        function onWheel(event) {
            if (!event) {
                return;
            }

            if (typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            var delta = event.deltaY || 0;
            var factor = Math.exp(-delta / 400);

            if (!isFinite(factor) || factor === 0) {
                return;
            }

            var newScale = clamp(state.scale * factor, MIN_ZOOM_SCALE, MAX_ZOOM_SCALE);
            if (Math.abs(newScale - state.scale) < 0.0001) {
                return;
            }

            state.scale = newScale;
            applyScale();
        }

        function beginPinch() {
            var points = pointerValues();
            if (points.length !== 2) {
                return;
            }
            state.pinchStartDistance = Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y);
            state.pinchStartScale = state.scale;
        }

        function updatePinch() {
            var points = pointerValues();
            if (points.length !== 2 || state.pinchStartDistance === 0) {
                return;
            }

            var distance = Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y);
            if (!distance) {
                return;
            }

            var factor = distance / state.pinchStartDistance;
            if (!isFinite(factor) || factor <= 0) {
                return;
            }

            var newScale = clamp(state.pinchStartScale * factor, MIN_ZOOM_SCALE, MAX_ZOOM_SCALE);
            if (Math.abs(newScale - state.scale) < 0.0001) {
                return;
            }

            state.scale = newScale;
            applyScale();
        }

        function onPointerDown(event) {
            if (event.pointerType === 'mouse' && event.button !== 0) {
                return;
            }

            if (typeof image.setPointerCapture === 'function') {
                try { image.setPointerCapture(event.pointerId); } catch (e) {}
            }

            setPointer(event);

            var keys = pointerKeys();
            if (keys.length === 1) {
                beginDrag(event);
            } else if (keys.length === 2) {
                state.dragPointerId = null;
                state.dragging = false;
                updateCursor();
                beginPinch();
            }

            if (typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
        }

        function onPointerMove(event) {
            if (!state.pointers.hasOwnProperty(event.pointerId)) {
                return;
            }

            state.pointers[event.pointerId].x = event.clientX;
            state.pointers[event.pointerId].y = event.clientY;

            var keys = pointerKeys();
            if (keys.length === 2) {
                state.dragPointerId = null;
                state.dragging = false;
                updateCursor();
                updatePinch();
            } else if (state.dragPointerId === event.pointerId) {
                updateDrag(event);
            }

            if (typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
        }

        function onPointerEnd(event) {
            if (!state.pointers.hasOwnProperty(event.pointerId)) {
                return;
            }

            if (typeof image.releasePointerCapture === 'function') {
                try { image.releasePointerCapture(event.pointerId); } catch (e) {}
            }

            endDrag(event);
            removePointer(event);

            var keys = pointerKeys();
            if (keys.length < 2) {
                state.pinchStartDistance = 0;
                state.pinchStartScale = state.scale;
            }

            if (typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
        }

        function onPointerCancel(event) {
            if (state.pointers.hasOwnProperty(event.pointerId)) {
                if (typeof image.releasePointerCapture === 'function') {
                    try { image.releasePointerCapture(event.pointerId); } catch (e) {}
                }
                removePointer(event);
            }

            state.pinchStartDistance = 0;
            state.pinchStartScale = state.scale;
            applyScale();
            endDrag(event);

            if (typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
        }

        var wheelOptions = false;
        try {
            var opts = Object.defineProperty({}, 'passive', {
                get: function () {
                    wheelOptions = { passive: false };
                    return false;
                }
            });
            var noop = function () {};
            window.addEventListener('test', noop, opts);
            window.removeEventListener('test', noop, opts);
        } catch (e) {}

        image.addEventListener('wheel', onWheel, wheelOptions || false);

        if ('PointerEvent' in window) {
            image.addEventListener('pointerdown', onPointerDown);
            image.addEventListener('pointermove', onPointerMove);
            image.addEventListener('pointerup', onPointerEnd);
            image.addEventListener('pointercancel', onPointerCancel);
        }

        image.addEventListener('load', function () {
            state.baseWidth = 0;
            state.baseHeight = 0;
            state.baseContentWidth = 0;
            state.baseContentHeight = 0;
            state.reset();
        });

        window.addEventListener('resize', function () {
            if (modal.dataset.pluginImgurActive === 'true') {
                state.baseWidth = 0;
                state.baseHeight = 0;
                state.baseContentWidth = 0;
                state.baseContentHeight = 0;
                state.reset();
            }
        });

        state.reset();

        modal._pluginImgurZoomState = state;
        return state;
    }

    function resetZoom(modal) {
        var state = ensureZoomState(modal);
        if (state) {
            state.reset();
        }
    }

    function openModal(id, trigger) {
        var modal = document.getElementById(id);
        if (!modal) {
            return;
        }

        var activeModal = document.querySelector('.plugin-imgur-modal[data-plugin-imgur-active="true"]');
        if (activeModal && activeModal.id && activeModal.id !== id) {
            closeModal(activeModal.id);
        }

        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        modal.dataset.pluginImgurActive = 'true';

        var deferredImage = modal.querySelector('[data-plugin-imgur-modal-src]');
        if (deferredImage) {
            var actualSrc = deferredImage.getAttribute('data-plugin-imgur-modal-src');
            if (actualSrc && deferredImage.getAttribute('src') !== actualSrc) {
                deferredImage.setAttribute('src', actualSrc);
            }
            deferredImage.removeAttribute('data-plugin-imgur-modal-src');
        }

        resetZoom(modal);

        var focusTarget = modal.querySelector('[data-plugin-imgur-focus]');
        if (focusTarget && typeof focusTarget.focus === 'function') {
            focusTarget.focus();
        }

        if (trigger) {
            modal.dataset.pluginImgurTriggerId = trigger;
        } else {
            delete modal.dataset.pluginImgurTriggerId;
        }
    }

    function closeModal(id) {
        var modal = document.getElementById(id);
        if (!modal) {
            return;
        }

        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        delete modal.dataset.pluginImgurActive;

        resetZoom(modal);

        var triggerId = modal.dataset.pluginImgurTriggerId;
        if (triggerId) {
            var originalTrigger = document.getElementById(triggerId);
            if (originalTrigger && typeof originalTrigger.focus === 'function') {
                originalTrigger.focus();
            }
            delete modal.dataset.pluginImgurTriggerId;
        }
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (target && target.nodeType !== 1) {
            target = target.parentElement;
        }

        if (!target) {
            return;
        }

        var openTrigger = closest(target, '[data-plugin-imgur-open]');
        if (openTrigger) {
            event.preventDefault();
            var modalId = openTrigger.getAttribute('data-plugin-imgur-open');
            if (modalId) {
                openModal(modalId, openTrigger.id || '');
            }
            return;
        }

        var closeTrigger = closest(target, '[data-plugin-imgur-close]');
        if (closeTrigger) {
            event.preventDefault();
            var closeId = closeTrigger.getAttribute('data-plugin-imgur-close');
            if (closeId) {
                closeModal(closeId);
            }
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' || event.key === 'Esc') {
            var activeModal = document.querySelector('.plugin-imgur-modal[data-plugin-imgur-active="true"]');
            if (activeModal && activeModal.id) {
                closeModal(activeModal.id);
            }
        }
    });
})();</script>
SCRIPT;

    return $script;
}

function plugin_imgur_loading_indicator(): string
{
    return '<span class="plugin-imgur-loading-icon" aria-hidden="true"></span>';
}

function plugin_imgur_caption_style(): string
{
    static $emitted = false;

    if ($emitted) {
        return '';
    }

    $emitted = true;

    $style = <<<'STYLE'
<style>
.plugin-imgur-media-wrapper {
    position:relative;
    display:inline-block;
    line-height:0;
}
.plugin-imgur-media-wrapper--inline {
    vertical-align:middle;
}
.plugin-imgur-media-wrapper > img,
.plugin-imgur-media-wrapper > video,
.plugin-imgur-media-wrapper > a {
    display:block;
}
.plugin-imgur-media-wrapper > a > img,
.plugin-imgur-media-wrapper > img,
.plugin-imgur-media-wrapper > video {
    display:block;
    max-width:100%;
}
.plugin-imgur-caption {
    position:absolute;
    left:0;
    right:0;
    bottom:0;
    color:#fff;
    background:rgba(0,0,0,0.6);
    background:linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.65) 40%, rgba(0,0,0,0) 85%);
    padding:1.65em 0.85em 0.45em;
    font-size:0.875em;
    line-height:1.4;
    box-sizing:border-box;
    pointer-events:none;
    word-break:break-word;
}
</style>
STYLE;

    return $style;
}

function plugin_imgur_lazy_style(): string
{
    static $emitted = false;

    if ($emitted) {
        return '';
    }

    $emitted = true;

    $style = <<<'STYLE'
<style>
.plugin-imgur-loading-icon {
    display:inline-block;
    width:2.5em;
    height:2.5em;
    border:0.35em solid rgba(0,0,0,0.2);
    border-top-color:rgba(0,0,0,0.6);
    border-radius:50%;
    animation:plugin-imgur-spin 1s linear infinite;
    vertical-align:middle;
    margin-right:0.5em;
    box-sizing:border-box;
}
@keyframes plugin-imgur-spin {
    to { transform: rotate(360deg); }
}
</style>
STYLE;

    return $style;
}

function plugin_imgur_lazy_script(): string
{
    static $initialized = false;

    if ($initialized) {
        return '';
    }

    $initialized = true;

    $script = <<<'SCRIPT'
<script>(function(){
    var initialized = false;

    var ERROR_TEXT = 'imgurメディアの読み込みに失敗しました。';

    function toArray(value) {
        return Array.prototype.slice.call(value);
    }

    function markState(container, state) {
        if (!container) {
            return;
        }
        container.setAttribute('data-plugin-imgur-lazy-state', state);
    }

    function removeSpinner(container) {
        if (!container) {
            return;
        }
        var spinner = container.querySelector('.plugin-imgur-loading-icon');
        if (spinner && spinner.parentNode) {
            spinner.parentNode.removeChild(spinner);
        }
        container.removeAttribute('data-plugin-imgur-lazy-container');
    }

    function showError(container) {
        if (!container) {
            return;
        }
        var slot = container.querySelector('[data-plugin-imgur-media-slot]');
        if (slot) {
            slot.textContent = ERROR_TEXT;
        }
        markState(container, 'error');
        removeSpinner(container);
    }

    function replaceNode(target, replacement) {
        if (!target) {
            return;
        }
        var parent = target.parentNode;
        if (!parent) {
            return;
        }
        if (replacement) {
            parent.replaceChild(replacement, target);
        } else {
            parent.removeChild(target);
        }
    }

    function buildModal(modalId, mediaUrl) {
        var container = document.createElement('span');
        container.id = modalId;
        container.className = 'plugin-imgur-modal';
        container.setAttribute('role', 'dialog');
        container.setAttribute('aria-modal', 'true');
        container.setAttribute('aria-hidden', 'true');
        container.setAttribute('style', 'display:none;position:fixed;inset:0;z-index:9999;padding:1.5rem;box-sizing:border-box;background:rgba(0,0,0,0.75);align-items:center;justify-content:center;');

        var overlay = document.createElement('a');
        overlay.href = '#';
        overlay.className = 'plugin-imgur-modal__overlay';
        overlay.setAttribute('data-plugin-imgur-close', modalId);
        overlay.setAttribute('aria-hidden', 'true');
        overlay.setAttribute('style', 'position:absolute;inset:0;display:block;');

        var content = document.createElement('span');
        content.className = 'plugin-imgur-modal__content';
        content.setAttribute('role', 'document');
        content.setAttribute('style', 'position:relative;display:block;overflow:auto;touch-action:none;max-width:100%;max-height:100%;');

        var close = document.createElement('a');
        close.href = '#';
        close.className = 'plugin-imgur-modal__close';
        close.setAttribute('data-plugin-imgur-close', modalId);
        close.setAttribute('aria-label', 'Close');
        close.setAttribute('data-plugin-imgur-focus', 'true');
        close.setAttribute('style', 'position:fixed;top:1.5rem;right:1.5rem;color:#fff;text-decoration:none;font-size:2rem;line-height:1;padding:0.25em;z-index:10000;');
        close.textContent = '×';

        var image = document.createElement('img');
        image.alt = '';
        image.loading = 'lazy';
        image.src = mediaUrl;
        image.className = 'plugin-imgur-modal__image';
        image.setAttribute('style', 'display:block;touch-action:none;user-select:none;cursor:zoom-in;');

        content.appendChild(close);
        content.appendChild(image);

        container.appendChild(overlay);
        container.appendChild(content);

        return container;
    }

    function prepareClassList(additional) {
        var classNames = [];
        if (additional) {
            additional.split(/\s+/).forEach(function (name) {
                if (name && classNames.indexOf(name) === -1) {
                    classNames.push(name);
                }
            });
        }
        return classNames;
    }

    function applyClasses(element, classNames) {
        if (!element || !classNames || classNames.length === 0) {
            return;
        }
        element.className = classNames.join(' ');
    }

    function buildCaptionWrapper(contentNode, captionText, isInline) {
        var wrapper = document.createElement('span');
        wrapper.className = 'plugin-imgur-media-wrapper' + (isInline ? ' plugin-imgur-media-wrapper--inline' : '');
        wrapper.setAttribute('data-plugin-imgur-has-caption', '1');

        if (contentNode) {
            wrapper.appendChild(contentNode);
        }

        var caption = document.createElement('span');
        caption.className = 'plugin-imgur-caption';
        caption.textContent = captionText;
        wrapper.appendChild(caption);

        return wrapper;
    }

    function renderMedia(container, payload) {
        if (!payload || payload.status !== 'ok') {
            showError(container);
            return;
        }

        var extension = (payload.extension || '').toLowerCase();
        var mediaType = (payload.mediaType || '').toLowerCase();
        if (!extension) {
            showError(container);
            return;
        }

        if (mediaType === 'gifv') {
            mediaType = 'video';
        }

        if (mediaType !== 'image' && mediaType !== 'video') {
            showError(container);
            return;
        }

        var encodedId = container.getAttribute('data-plugin-imgur-encoded-id') || '';
        var mediaId = container.getAttribute('data-plugin-imgur-media-id') || '';
        var mediaUrl = 'https://i.imgur.com/' + (encodedId || encodeURIComponent(mediaId)) + '.' + extension;

        var slot = container.querySelector('[data-plugin-imgur-media-slot]');
        if (!slot) {
            removeSpinner(container);
            markState(container, 'loaded');
            return;
        }

        var styleValue = container.getAttribute('data-plugin-imgur-media-style') || '';
        var classesValue = container.getAttribute('data-plugin-imgur-media-classes') || '';
        var disableLink = container.getAttribute('data-plugin-imgur-disable-link') === '1';

        if (mediaType === 'image') {
            var classList = prepareClassList(classesValue);
            var imageElement = document.createElement('img');
            imageElement.alt = '';
            imageElement.loading = 'lazy';
            if (styleValue) {
                imageElement.setAttribute('style', styleValue);
            }
            applyClasses(imageElement, classList);

            var onLoad = function () {
                imageElement.removeEventListener('load', onLoad);
                imageElement.removeEventListener('error', onError);
                markState(container, 'loaded');
                removeSpinner(container);
            };
            var onError = function () {
                imageElement.removeEventListener('load', onLoad);
                imageElement.removeEventListener('error', onError);
                showError(container);
            };

            imageElement.addEventListener('load', onLoad);
            imageElement.addEventListener('error', onError);
            imageElement.src = mediaUrl;

            var replacement = imageElement;
            if (!disableLink) {
                var modalId = container.getAttribute('data-plugin-imgur-modal-id');
                if (modalId) {
                    var trigger = document.createElement('a');
                    trigger.href = '#';
                    trigger.id = modalId + '-trigger';
                    trigger.className = 'plugin-imgur-modal-trigger';
                    trigger.setAttribute('data-plugin-imgur-open', modalId);
                    trigger.appendChild(imageElement);
                    replacement = trigger;

                    var modalElement = buildModal(modalId, mediaUrl);
                    var modalSlot = container.querySelector('[data-plugin-imgur-modal-slot]');
                    if (modalSlot) {
                        replaceNode(modalSlot, modalElement);
                    } else {
                        container.appendChild(modalElement);
                    }
                }
            }

            var captionText = container.getAttribute('data-plugin-imgur-caption') || '';
            if (captionText) {
                replacement = buildCaptionWrapper(replacement, captionText, container.getAttribute('data-plugin-imgur-inline') === '1');
            }

            replaceNode(slot, replacement);
        } else {
            var classListVideo = prepareClassList(classesValue);
            var video = document.createElement('video');
            video.setAttribute('controls', '');
            video.setAttribute('playsinline', '');
            video.setAttribute('preload', 'metadata');
            if (styleValue) {
                video.setAttribute('style', styleValue);
            }
            applyClasses(video, classListVideo);
            video.appendChild(document.createTextNode('Your browser does not support the video tag.'));

            var onVideoReady = function () {
                video.removeEventListener('loadeddata', onVideoReady);
                video.removeEventListener('loadedmetadata', onVideoReady);
                video.removeEventListener('error', onVideoError);
                markState(container, 'loaded');
                removeSpinner(container);
            };
            var onVideoError = function () {
                video.removeEventListener('loadeddata', onVideoReady);
                video.removeEventListener('loadedmetadata', onVideoReady);
                video.removeEventListener('error', onVideoError);
                showError(container);
            };

            video.addEventListener('loadeddata', onVideoReady);
            video.addEventListener('loadedmetadata', onVideoReady);
            video.addEventListener('error', onVideoError);
            video.src = mediaUrl;

            replaceNode(slot, video);
        }
    }

    function hydrate(container) {
        if (!container || container.getAttribute('data-plugin-imgur-lazy-state') === 'loading' || container.getAttribute('data-plugin-imgur-lazy-state') === 'loaded') {
            return;
        }

        if (!window.fetch) {
            showError(container);
            return;
        }

        markState(container, 'loading');

        var mediaId = container.getAttribute('data-plugin-imgur-media-id');
        if (!mediaId) {
            showError(container);
            return;
        }

        var query = 'plugin=imgur&mode=detect&id=' + encodeURIComponent(mediaId);
        fetch('?' + query, { credentials: 'same-origin' }).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        }).then(function (data) {
            renderMedia(container, data);
        }).catch(function () {
            showError(container);
        });
    }

    function observe() {
        var containers = toArray(document.querySelectorAll('[data-plugin-imgur-lazy-container="true"]'));
        if (containers.length === 0) {
            return;
        }

        if (!('IntersectionObserver' in window)) {
            containers.forEach(hydrate);
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting || entry.intersectionRatio > 0) {
                    observer.unobserve(entry.target);
                    hydrate(entry.target);
                }
            });
        }, { rootMargin: '0px 0px 256px 0px' });

        containers.forEach(function (container) {
            observer.observe(container);
        });
    }

    function init() {
        if (initialized) {
            return;
        }
        initialized = true;
        observe();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();</script>
SCRIPT;

    return $script;
}

/**
 * @return array{status: 'ok', extension: string, mediaType: string}|array{status: 'not_found'|'network_error'}
 */
function plugin_imgur_detect_media(string $mediaId): array
{
    $encodedId = rawurlencode($mediaId);
    $groups = plugin_imgur_extension_groups();
    $networkFailure = false;

    $probeOrder = [
        'video' => $groups['video'],
        'image' => $groups['image'],
        'gifv'  => $groups['gifv'],
    ];

    foreach ($probeOrder as $mediaType => $extensions) {
        foreach ($extensions as $extension) {
            $probeResult = plugin_imgur_probe_media($encodedId, $extension);
            if ($probeResult === null) {
                $networkFailure = true;
                continue;
            }
            if ($probeResult === true) {
                return [
                    'status' => 'ok',
                    'extension' => $extension,
                    'mediaType' => $mediaType,
                ];
            }
        }
    }

    if ($networkFailure) {
        return ['status' => 'network_error'];
    }

    return ['status' => 'not_found'];
}

/**
 * @return array<string, list<string>>
 */
function plugin_imgur_extension_groups(): array
{
    return [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'avif'],
        'video' => ['mp4', 'webm'],
        'gifv'  => ['gifv'],
    ];
}

function plugin_imgur_resolve_media_type(string $extension): string
{
    foreach (plugin_imgur_extension_groups() as $type => $extensions) {
        if (in_array(strtolower($extension), $extensions, true)) {
            return $type;
        }
    }

    return '';
}

/**
 * @return true|false|null true: accessible, false: not found, null: network error
 */
function plugin_imgur_probe_media(string $encodedId, string $extension): ?bool
{
    $url = sprintf('https://i.imgur.com/%s.%s', $encodedId, $extension);
    $context = stream_context_create([
        'http' => [
            'method' => 'HEAD',
            'ignore_errors' => true,
            'timeout' => 5,
            'header' => "User-Agent: PukiWiki-Imgur-Plugin/1.0\r\n",
        ],
    ]);

    $headers = @get_headers($url, false, $context);
    if ($headers === false) {
        return null;
    }

    $statusLines = array_values(array_filter(
        $headers,
        static fn ($line): bool => is_string($line) && str_starts_with($line, 'HTTP/')
    ));

    if ($statusLines === []) {
        return null;
    }

    $lastStatusLine = (string)end($statusLines);
    if (preg_match('/\s(\d{3})\s/', $lastStatusLine, $matches) !== 1) {
        return null;
    }

    $statusCode = (int)$matches[1];

    if ($statusCode >= 200 && $statusCode < 400) {
        return true;
    }

    if ($statusCode >= 400 && $statusCode < 500) {
        return false;
    }

    return null;
}