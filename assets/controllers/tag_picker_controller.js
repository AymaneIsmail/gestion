import { Controller } from '@hotwired/stimulus';

/**
 * Tag picker controller.
 * Gère le compteur de tags sélectionnés et les animations.
 * Le rendu visuel (chip sélectionné/désélectionné) est géré par CSS pur via :checked.
 */
export default class extends Controller {
    static targets = ['grid', 'count'];

    connect() {
        this._updateCount();
    }

    // Appelé à chaque changement de checkbox (via event delegation sur le grid)
    toggle() {
        this._updateCount();
    }

    _updateCount() {
        if (!this.hasCountTarget || !this.hasGridTarget) return;

        const checkboxes = this.gridTarget.querySelectorAll('input[type="checkbox"]');
        const checked = [...checkboxes].filter(cb => cb.checked).length;
        const total = checkboxes.length;

        if (checked === 0) {
            this.countTarget.textContent = '';
            this.countTarget.removeAttribute('data-active');
        } else {
            this.countTarget.textContent = `${checked} / ${total} sélectionné${checked > 1 ? 's' : ''}`;
            this.countTarget.setAttribute('data-active', '');
        }
    }
}
