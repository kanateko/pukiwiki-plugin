// 要修正項目を色付け
function markInvalids() {
    const invalids = document.querySelectorAll('.mailform-invalid');
    if (invalids) {
        for (const invalid of invalids) {
            const input = document.querySelector('[name="' + invalid.dataset.name +'"');
            input.dataset.invalid = 'true';
        }
    }
}

// checkboxのグループ内でのrequired対応
function groupValidation(chk) {
    const name = chk.name;
    const group = document.querySelectorAll('.mailform-checkbox[name="' + name + '"]');
    const checked = document.querySelectorAll('.mailform-checkbox[name="' + name + '"]:checked');
    const stats = checked.length > 0 ? false : true;

    for (const t of group) {
        t.required = stats;
    }
}

// 文字数制限
function lengthObserver(txt) {
    const max = txt.dataset.max;
    const length = txt.value.length;
    const remain = txt.nextElementSibling;
    const cnfBtn = document.querySelector('.mailform-confirm');

    remain.innerText = max - length;
    if (length > max) {
        txt.dataset.over = "true";
        cnfBtn.disabled = true;
    } else {
        txt.dataset.over = "";
        cnfBtn.disabled = false;
    }
}

// 送信中のオーバーレイ表示
function lockScreen() {
    const overlay = document.querySelector('.mailform-progress');
    overlay.style.opacity = '1';
    overlay.style.pointerEvents = 'auto';
}

