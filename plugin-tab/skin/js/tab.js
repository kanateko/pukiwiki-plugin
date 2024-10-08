/**
 * tabプラグイン用の関数
 */

// イベントリスナー追加
export const pluginTab = () => {
    const tabs = document.querySelectorAll('.plugin-tab[data-group]');

    for (const tab of tabs) {
        tab.addEventListener('input', (e) => {
            const ct = e.currentTarget;
            const id = ct.id;
            const group = ct.dataset.group;
            const inputs = ct.querySelectorAll('.tab-switch');
            let index = 0;

            for (let i = 0; i < inputs.length; i++) {
                if (inputs[i].checked) {
                    index = i;
                    break;
                }
            }

            syncGroupedTabs(id, group, index);
        });
    }
}

// 同一グループのタブを切り替え
const syncGroupedTabs = (id, group, index) => {
    const tabs = document.querySelectorAll('.plugin-tab[data-group="' + group + '"]:not(#' + id + ')');

    for (const tab of tabs) {
        const inputs = tab.querySelectorAll('.tab-switch');
        const len = inputs.length;

        index = index < len - 1 ? index : len - 1;

        for (let i = 0; i < len; i++) {
            inputs[i].checked = i == index;
        }
    }
}