import { Controller } from '@hotwired/stimulus';

/**
 * tag-filter — dropdown multi-select pour filtrer les stock-rows par tag,
 * sans rechargement de page. Synchronise l'état avec l'URL (replaceState).
 */
export default class extends Controller {
    static targets = ['trigger', 'panel', 'checkbox', 'count', 'row', 'empty'];

    connect() {
        // Restaure les tags depuis l'URL au chargement
        const params = new URLSearchParams(window.location.search);
        const tagIds = params.getAll('tag[]');
        this.checkboxTargets.forEach(cb => {
            cb.checked = tagIds.includes(cb.value);
        });

        this._filter();
        this._updateTrigger();

        // Ferme le panel si clic en dehors
        this._onOutside = (e) => {
            if (!this.element.contains(e.target)) this._close();
        };
        document.addEventListener('click', this._onOutside);
    }

    disconnect() {
        document.removeEventListener('click', this._onOutside);
    }

    toggle(e) {
        e.stopPropagation();
        this.panelTarget.classList.toggle('tag-filter-panel--open');
    }

    change() {
        this._filter();
        this._updateTrigger();
        this._syncUrl();
    }

    // ── Privé ──────────────────────────────────────────────

    _close() {
        this.panelTarget.classList.remove('tag-filter-panel--open');
    }

    _selected() {
        return this.checkboxTargets.filter(cb => cb.checked).map(cb => cb.value);
    }

    _filter() {
        const selected = this._selected();
        let visible = 0;

        this.rowTargets.forEach(row => {
            const ids = (row.dataset.tagIds || '').split(',').filter(Boolean);
            const show = selected.length === 0 || selected.some(id => ids.includes(id));
            row.hidden = !show;
            if (show) visible++;
        });

        if (this.hasEmptyTarget) {
            this.emptyTarget.hidden = visible > 0;
        }
    }

    _updateTrigger() {
        const count = this._selected().length;
        if (this.hasCountTarget) {
            this.countTarget.textContent = count;
            this.countTarget.hidden = count === 0;
        }
        this.triggerTarget.dataset.active = count > 0 ? '1' : '0';
    }

    _syncUrl() {
        const url = new URL(window.location.href);
        url.searchParams.delete('tag[]');
        this._selected().forEach(id => url.searchParams.append('tag[]', id));
        history.replaceState({}, '', url);
    }
}
