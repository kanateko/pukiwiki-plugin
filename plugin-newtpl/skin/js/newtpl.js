/**
 * 入力サジェストの追加
 */
export const addAutoComplete = (id, src) => {
    const autocomp = new autoComplete({
        selector: '#autoComplete' + id,
        data: {
            src: src,
            cache: true,
        },
        resultsList: {
            maxResults: 25
        },
        resultItem: {
            highlight: true
        },
        events: {
            input: {
                selection: (event) => {
                    const selection = event.detail.selection.value;
                    autocomp.input.value = selection;
                }
            }
        }
    });
}

/**
 * フォーム用処理
 */
export const newtplForm = () => {
    const form = document.querySelector('.plugin-newtpl');

    preventEnterKey(form);
    showRangeValue(form);
    toggleRequired(form);
}

/**
 * Enterでの送信防止
 */
const preventEnterKey = (form) => {
    form.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            const elem = document.activeElement;

            if (elem.nodeName === 'INPUT') {
                e.preventDefault();
            }
        }
    });
}

/**
 * rangeスライダーの数字表記
 */
const showRangeValue = (form) => {
    const sliders = form.querySelectorAll('input[type="range"]');
    const show = elem => elem.nextElementSibling.innerText = elem.value;

    for (const slider of sliders) {
        show(slider);

        slider.addEventListener('input', () => {
            show(slider);
        });
    }
}

/**
 * checkboxのrequiredの切り替え
 */
const toggleRequired = (form) => {
    const checkboxes = form.querySelectorAll('input[type=checkbox]');
    const toggle = (checkbox) => {
        const group = checkbox.closest('.newtpl-post');
        const siblings = group.querySelectorAll('input[type=checkbox]');
        const unchecked = group.querySelector('input[type=checkbox]:checked') === null;

        for (const sibling of siblings) {
            sibling.required = unchecked;
        }
    }

    for (const checkbox of checkboxes) {
        const required = checkbox.closest('.newtpl-item').dataset.require === 'true';

        if (required) {
            toggle(checkbox);

            checkbox.addEventListener('change', () => {
                toggle(checkbox);
            });
        }
    }
}