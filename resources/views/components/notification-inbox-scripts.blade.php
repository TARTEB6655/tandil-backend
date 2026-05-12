<script>
    (function () {
        const selectAll = document.getElementById('select-all-notifications');
        const selectedCount = document.getElementById('selected-count');
        const deleteSelected = document.getElementById('btn-delete-selected');
        const checkboxes = Array.from(document.querySelectorAll('.notification-cb'));
        function syncBulkUi() {
            const checked = checkboxes.filter(cb => cb.checked).length;
            if (selectedCount) selectedCount.textContent = `${checked} selected`;
            if (deleteSelected) deleteSelected.disabled = checked === 0;
            if (selectAll) selectAll.checked = checked > 0 && checked === checkboxes.length;
        }
        selectAll?.addEventListener('change', function () {
            checkboxes.forEach(cb => { cb.checked = this.checked; });
            syncBulkUi();
        });
        checkboxes.forEach(cb => cb.addEventListener('change', syncBulkUi));
        syncBulkUi();
        document.querySelectorAll('.notification-row[data-open-url]').forEach(function (row) {
            row.addEventListener('click', function (e) {
                if (e.target.closest('input, button, form, label')) return;
                const link = row.querySelector('.js-open-notification');
                if (link) window.location.href = link.getAttribute('href');
            });
        });
        document.querySelectorAll('.js-delete-notification').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const action = this.getAttribute('data-delete-url');
                if (!action) return;
                if (!confirm('Are you sure you want to delete this notification?')) return;
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = action;
                const token = document.createElement('input');
                token.type = 'hidden';
                token.name = '_token';
                token.value = @json(csrf_token());
                form.appendChild(token);
                const method = document.createElement('input');
                method.type = 'hidden';
                method.name = '_method';
                method.value = 'DELETE';
                form.appendChild(method);
                document.body.appendChild(form);
                form.submit();
            });
        });
    })();
</script>
