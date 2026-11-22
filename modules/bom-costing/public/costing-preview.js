// 组价详情右栏“成本预览”实时试算 + 左栏一键保存（走模块资产路由输出，CSP 禁内联脚本）。
//
// 左栏表格里每个数量输入框都带 data-cost-input 与 data-cost-key="c<composition_id>" / "r<resource_line_id>"，
// 并通过 form="cost-qty-bulk" 挂到页面底部那个唯一的批量表单上（表格内部放不了表单元素）。
//
// 输入变化 → 防抖 → POST action=costing.preview → 服务端用未保存的草稿数量重算，
// 回一段和详情页同源的预览 HTML，就地替换右栏。全程不写库；
// 改动累积到底部“保存全部改动”（action=costing.quantities.save），一次事务写库、一次重算。
(function () {
    const live = document.getElementById('cost-preview-live');
    if (!live) return;

    const status = document.getElementById('cost-preview-status');
    const csrfField = document.querySelector('input[name="csrf_token"]');
    const inputs = Array.from(document.querySelectorAll('[data-cost-input]'));
    if (!csrfField || inputs.length === 0) return;

    const bulkForm = document.getElementById('cost-qty-bulk');
    const bulkCount = bulkForm ? bulkForm.querySelector('[data-cost-bulk-count]') : null;
    const bulkReset = bulkForm ? bulkForm.querySelector('[data-cost-bulk-reset]') : null;

    const endpoint = live.dataset.endpoint || window.location.pathname + window.location.search;
    const costingId = live.dataset.costingId || '';
    const csrfToken = csrfField.value;
    const saved = new Map(inputs.map(input => [input, (input.dataset.costSaved || input.defaultValue || '').trim()]));

    let timer = null;
    let seq = 0;
    let submitting = false;

    function setStatus(state, text) {
        if (!status) return;
        status.dataset.state = state;
        status.textContent = text;
    }

    function sameQuantity(a, b) {
        const left = parseFloat(a);
        const right = parseFloat(b);
        if (!Number.isNaN(left) && !Number.isNaN(right)) return left === right;
        return (a || '').trim() === (b || '').trim();
    }

    function isDirty(input) {
        return !sameQuantity(input.value, saved.get(input) || '');
    }

    function refreshDirtyUi() {
        let count = 0;
        inputs.forEach(input => {
            const dirty = isDirty(input);
            if (dirty) count += 1;
            if (dirty) {
                input.dataset.dirty = '1';
            } else {
                delete input.dataset.dirty;
            }
        });
        if (bulkForm) bulkForm.hidden = count === 0;
        if (bulkCount) bulkCount.textContent = String(count);
        return count;
    }

    async function refresh() {
        const token = ++seq;
        const body = new FormData();
        body.set('action', 'costing.preview');
        body.set('csrf_token', csrfToken);
        body.set('costing_id', costingId);
        inputs.forEach(input => {
            if (input.dataset.costKey) body.set('qty[' + input.dataset.costKey + ']', input.value.trim());
        });

        live.dataset.loading = '1';
        setStatus('pending', '试算中…');

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                body,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'fetch' },
            });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();
            // 只认最后一次请求的结果；中途又敲了键就丢掉旧的。
            if (token !== seq) return;

            if (data && typeof data.html === 'string') {
                live.innerHTML = data.html;
            }
            if (data && data.ok === false) {
                setStatus('error', data.message || '数量无效，右栏仍按已保存的值显示。');
            } else if (refreshDirtyUi() > 0) {
                setStatus('draft', '已在试算（未保存）：改完点底部“保存全部改动”一次写库');
            } else {
                setStatus('synced', '实时试算：与已保存数据一致');
            }
        } catch (error) {
            if (token !== seq) return;
            setStatus('error', '试算失败，右栏仍是上一次的结果。刷新页面可恢复。');
        } finally {
            if (token === seq) delete live.dataset.loading;
        }
    }

    inputs.forEach(input => {
        input.addEventListener('input', () => {
            const dirty = refreshDirtyUi();
            if (dirty > 0) setStatus('draft', '已在试算（未保存）：改完点底部“保存全部改动”一次写库');
            clearTimeout(timer);
            timer = setTimeout(refresh, 320);
        });
        input.addEventListener('blur', () => {
            clearTimeout(timer);
            timer = setTimeout(refresh, 0);
        });
    });

    // 撤销：把每个框还原成上次保存的值，右栏跟着回到已保存口径。
    if (bulkReset) {
        bulkReset.addEventListener('click', () => {
            inputs.forEach(input => {
                input.value = saved.get(input) || '';
            });
            refreshDirtyUi();
            clearTimeout(timer);
            refresh();
        });
    }

    // 连点提交只算一次；提交后不再弹“有未保存改动”提醒。
    // 注意不要在提交事件里 disable 按钮：那会把点击的那个 submit 按钮的名字/值从表单数据里剔掉，
    // “保存并确认”的 next=confirm 就丢了。
    if (bulkForm) {
        bulkForm.addEventListener('submit', event => {
            if (refreshDirtyUi() === 0 || submitting) {
                event.preventDefault();
                return;
            }
            submitting = true;
        });
    }

    // Ctrl/Cmd + S：有改动就存，省一次下拉。
    document.addEventListener('keydown', event => {
        if (!bulkForm || !(event.ctrlKey || event.metaKey) || event.key.toLowerCase() !== 's') return;
        if (refreshDirtyUi() === 0) return;
        event.preventDefault();
        if (typeof bulkForm.requestSubmit === 'function') bulkForm.requestSubmit();
    });

    // 有未保存改动还去点“确认组价”或离开页面时提醒一下，避免白改。
    window.addEventListener('beforeunload', event => {
        if (submitting || refreshDirtyUi() === 0) return;
        event.preventDefault();
        event.returnValue = '';
    });

    // 打开“编辑子材料”对话框时，把行内（可能还没保存的）数字带进去，避免两处显示不一致。
    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-dialog-open^="comp-edit-"]');
        if (!trigger) return;
        const dialog = document.getElementById(trigger.dataset.dialogOpen);
        if (!dialog) return;
        const row = document.querySelector('[data-cost-input][data-dialog-target="' + trigger.dataset.dialogOpen + '"]');
        const field = dialog.querySelector('input[name="quantity"]');
        if (row && field && row.value.trim() !== '') field.value = row.value.trim();
    });

    refreshDirtyUi();
})();
