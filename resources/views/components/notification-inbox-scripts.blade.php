<script>
    (function () {
        const bulkForm = document.getElementById('form-notifications-bulk');
        const selectAll = document.getElementById('select-all-notifications');
        const selectedCount = document.getElementById('selected-count');
        const deleteSelected = document.getElementById('btn-delete-selected');

        function notificationCheckboxes() {
            return Array.from(document.querySelectorAll('.notification-cb'));
        }

        function syncBulkUi() {
            const boxes = notificationCheckboxes();
            const checked = boxes.filter(cb => cb.checked).length;
            if (selectedCount) {
                selectedCount.textContent = `${checked} selected`;
            }
            if (deleteSelected) {
                deleteSelected.disabled = checked === 0;
            }
            if (selectAll) {
                selectAll.checked = checked > 0 && checked === boxes.length;
            }
        }

        selectAll?.addEventListener('change', function () {
            notificationCheckboxes().forEach(cb => { cb.checked = this.checked; });
            syncBulkUi();
        });
        notificationCheckboxes().forEach(cb => cb.addEventListener('change', syncBulkUi));
        syncBulkUi();

        bulkForm?.addEventListener('submit', function (e) {
            const checked = notificationCheckboxes().filter(cb => cb.checked);
            if (checked.length === 0) {
                e.preventDefault();
                alert('Please select at least one notification.');
                return;
            }
            if (!confirm(`Delete ${checked.length} selected notification(s)?`)) {
                e.preventDefault();
                return;
            }
            // Submit button may sit outside the form; inject hidden ids[] so POST always includes selection.
            bulkForm.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());
            checked.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = cb.value;
                bulkForm.appendChild(input);
            });
        });

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
