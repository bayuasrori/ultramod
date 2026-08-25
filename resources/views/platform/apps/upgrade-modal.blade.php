<div class="modal fade" id="upgradeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="upgradeModalTitle">Upgrade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="upgradeModalBody">
                <div class="text-muted">Resolving upgrade plan…</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" id="upgradeModalForm" class="d-inline">
                    @csrf
                    <input type="hidden" name="force" id="upgradeModalForce" value="0">
                    <button type="submit" class="btn btn-primary" id="upgradeModalConfirm" disabled>Confirm upgrade</button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const modalElement = document.getElementById('upgradeModal');
    const modal = new bootstrap.Modal(modalElement);
    const body = document.getElementById('upgradeModalBody');
    const title = document.getElementById('upgradeModalTitle');
    const form = document.getElementById('upgradeModalForm');
    const force = document.getElementById('upgradeModalForce');
    const confirm = document.getElementById('upgradeModalConfirm');

    const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));

    const list = (items) => '<ul class="mb-0 small">' + items.map((i) => '<li>' + escape(i) + '</li>').join('') + '</ul>';

    function render(plan) {
        const blocks = [];

        plan.blockers.forEach((b) => blocks.push('<div class="alert alert-danger py-2 mb-2">' + escape(b) + '</div>'));
        plan.warnings.forEach((w) => blocks.push('<div class="alert alert-warning py-2 mb-2">' + escape(w) + '</div>'));

        plan.apps.forEach((app) => {
            const rows = [];

            rows.push('<dt class="col-sm-4">Version</dt><dd class="col-sm-8">' +
                escape(app.from_version) + ' &rarr; <strong>' + escape(app.to_version) + '</strong>' +
                (app.reapply ? ' <span class="badge text-bg-secondary">reapply</span>' : '') + '</dd>');

            if (app.reason === 'dependency') {
                rows.push('<dt class="col-sm-4">Included because</dt><dd class="col-sm-8">it depends on another app in this upgrade</dd>');
            }

            rows.push('<dt class="col-sm-4">Migrations</dt><dd class="col-sm-8">' +
                (app.pending_migrations.length ? list(app.pending_migrations) : '<span class="text-muted">none pending</span>') + '</dd>');

            rows.push('<dt class="col-sm-4">Upgrade steps</dt><dd class="col-sm-8">' +
                (app.steps.length ? list(app.steps.map((s) => s.phase + ' · ' + s.step)) : '<span class="text-muted">none</span>') + '</dd>');

            if (app.permissions_added.length || app.permissions_removed.length) {
                rows.push('<dt class="col-sm-4">Permissions</dt><dd class="col-sm-8">' +
                    (app.permissions_added.length ? '<div class="text-success small">+ ' + escape(app.permissions_added.join(', ')) + '</div>' : '') +
                    (app.permissions_removed.length ? '<div class="text-danger small">&minus; ' + escape(app.permissions_removed.join(', ')) + '</div>' : '') +
                    '</dd>');
            }

            blocks.push(
                '<div class="card mb-2"><div class="card-body py-2">' +
                '<h6 class="card-title mb-2">' + escape(app.name) + ' <code>' + escape(app.app_id) + '</code></h6>' +
                '<dl class="row mb-0">' + rows.join('') + '</dl>' +
                '</div></div>'
            );
        });

        if (!plan.apps.length && !plan.blockers.length) {
            blocks.push('<div class="text-muted">Nothing to upgrade.</div>');
        }

        body.innerHTML = blocks.join('');
        confirm.disabled = plan.blocked || !plan.apps.length;
    }

    document.querySelectorAll('[data-upgrade-plan]').forEach((button) => {
        button.addEventListener('click', function () {
            title.textContent = button.dataset.upgradeTitle || 'Upgrade';
            form.action = button.dataset.upgradeAction;
            force.value = button.dataset.upgradeForce === '1' ? '1' : '0';
            body.innerHTML = '<div class="text-muted">Resolving upgrade plan…</div>';
            confirm.disabled = true;
            modal.show();

            fetch(button.dataset.upgradePlan, { headers: { 'Accept': 'application/json' } })
                .then((response) => response.json())
                .then(render)
                .catch(() => {
                    body.innerHTML = '<div class="alert alert-danger mb-0">Could not resolve the upgrade plan.</div>';
                });
        });
    });
})();
</script>
@endpush
