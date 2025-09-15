/**
 * switchプラグイン用の関数
 */

// rangeとselectにイベントリスナー追加
export const pluginSwitch = () => {
    const ranges = document.querySelectorAll('.switch-range');
    const selects = document.querySelectorAll('.switch-select');

    // rangeによる操作
    for (const range of ranges) {
        switchByRange(range);
    }

    // selectによる操作
    for (const select of selects) {
        switchBySelect(select);
    }
}

// rangeによる操作
const switchByRange = range => {
    const min = range.dataset.min;
    const step = range.dataset.step;
    const group = range.dataset.group;
    const groupedElements = document.querySelectorAll('[data-group="' + group + '"]:not(#' + range.id + ')');
    const output = range.nextElementSibling;

    // 操作があった際
    range.addEventListener('input', (e) => {
        const ct = e.currentTarget;
        const index = (ct.value - min) / step;

        // 表示される数字を変更する
        output.innerHTML = Number(ct.value).toLocaleString();

        // 同グループの表示を切り替える
        for (const el of groupedElements) {
            switchSelectedElement(el, index);
        }
    });
}

// selectによる操作
const switchBySelect = select => {
    const group = select.dataset.group;
    const groupedElements = document.querySelectorAll('[data-group="' + group + '"]:not(#' + select.id + ')');

    select.addEventListener('input', (e) => {
        const ct = e.currentTarget;
        const index = ct.selectedIndex;

        // 同グループの表示を切り替える
        for (const el of groupedElements) {
            switchSelectedElement(el, index);
        }
    });
}

// 表示内容の切り替え
const switchSelectedElement = (el, index) => {
    if (el.classList.contains('switch-range')) {
        // range
        el.value = Number(el.dataset.step) * index + Number(el.dataset.min);
        el.nextElementSibling.innerHTML = Number(el.value).toLocaleString();
    } else if (el.classList.contains('switch-number')) {
        // number
        const numMin = el.dataset.min === '-INF' ? -Infinity : Number(el.dataset.min);
        const numMax = el.dataset.max === 'INF' ? Infinity : Number(el.dataset.max);
        const numStep = Number(el.dataset.step);
        const isPositive = numStep > 0
        let currentValue = isPositive ? numMin + numStep * index : numMax + numStep * index;

        currentValue = currentValue < numMin ? numMin : currentValue > numMax ? numMax : currentValue;
        el.innerHTML = currentValue.toLocaleString();
    } else {
        // select, default
        const items = el.querySelectorAll('.switch-item');
        const lastIndex = items.length - 1

        // 要素数の最大を上限に
        if (index > lastIndex) {
            index = lastIndex;
        }

        for (let i = 0; i < items.length; i++) {
            const item = items[i];

            if (el.classList.contains('switch-select')) {
                // select属性の切り替え (select)
                if (index == i) {
                    item.selected = true;
                } else {
                    item.selected = false;
                }
            } else if (el.classList.contains('switch-default')) {
                // data-selectの切り替え (default)
                if (index == i) {
                    item.dataset.selected = "";
                } else {
                    delete item.dataset.selected;
                }
            }
        }
    }
}