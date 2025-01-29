/**
 * clipboardプラグイン用スクリプト
 */

export const pluginClipboard = () => {
    const btns = document.querySelectorAll('.clipboard-button');

    for (const btn of btns) {
        btn.addEventListener('click', () => {
            const parent = btn.closest('.plugin-clipboard');

            if (parent.dataset.src === undefined) {
                getInnerText(btn, parent);
            } else {
                getSource(btn, parent);
            }
        });
    }
}

// 表示テキストのコピー
const getInnerText = (btn, parent) => {
    const target = parent.querySelector('.clipboard-display');

    if (target) {
        navigator.clipboard.writeText(target.innerText);
        showCopiedPopup(btn);
    }
}

// 変換前のテキストのコピー
const getSource = (btn, parent) => {
    const target = parent.querySelector('.clipboard-source');

    if (target) {
        const text = htmlsc_decode(target.innerText);
        navigator.clipboard.writeText(text);
        showCopiedPopup(btn);
    }
}

// コピー時のポップアップ表示
const showCopiedPopup = btn => {
    if (btn.dataset.copied === undefined && btn.dataset.msg) {
        btn.dataset.copied = "";
        setTimeout(() => {
            delete btn.dataset.copied
        }, 2000);
    }
}

// サニタイズされた文字をもとに戻す
const htmlsc_decode = text => {
    return text.replace(/(&amp;|&#39;|&quot;|&lt;|&gt;)/g, match => {
        return {
                '&amp;': '&',
                '&#39;': '\'',
                '&quot;': '"',
                '&lt;': '<',
                '&gt;': '>'
            }[match]
        });
}