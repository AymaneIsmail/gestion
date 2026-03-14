import { Controller } from '@hotwired/stimulus';

/**
 * Stock controller — gère les boutons +/- et la saisie directe de quantité.
 * Communique avec le serveur via fetch (JSON).
 * Met à jour l'UI de façon optimiste, revient en arrière en cas d'erreur.
 */
export default class extends Controller {
    static targets = ['quantity', 'input', 'decrement', 'badge'];
    static values  = {
        incrementUrl: String,
        decrementUrl: String,
        setUrl:       String,
    };

    _pending = false;

    increment() {
        if (this._pending) return;
        this._send(this.incrementUrlValue, {});
    }

    decrement() {
        if (this._pending) return;
        const current = this._current();
        if (current <= 0) return;
        this._send(this.decrementUrlValue, {});
    }

    // Déclenché quand l'utilisateur quitte le champ de saisie directe
    commitInput() {
        if (this._pending) return;
        const value = parseInt(this.inputTarget.value, 10);
        if (isNaN(value) || value < 0) {
            this.inputTarget.value = this._current();
            return;
        }
        this._send(this.setUrlValue, { quantity: value });
    }

    // Sélectionne tout le texte du champ au focus pour faciliter la saisie
    selectInput() {
        this.inputTarget.select();
    }

    // Valide sur Entrée, annule sur Échap
    handleKey(event) {
        if (event.key === 'Enter')  { event.preventDefault(); this.inputTarget.blur(); }
        if (event.key === 'Escape') { this.inputTarget.value = this._current(); this.inputTarget.blur(); }
    }

    _csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    }

    async _send(url, body) {
        this._pending = true;
        this._setLoading(true);

        const previous = this._current();

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this._csrfToken(),
                },
                body: new URLSearchParams(body),
            });

            if (!response.ok) throw new Error(`HTTP ${response.status}`);

            const { quantity, inStock } = await response.json();
            this._update(quantity, inStock);
        } catch {
            // Annulation optimiste
            this._update(previous, previous > 0);
            this.element.classList.add('stock-row--error');
            setTimeout(() => this.element.classList.remove('stock-row--error'), 1500);
        } finally {
            this._pending = false;
            this._setLoading(false);
        }
    }

    _current() {
        return parseInt(this.quantityTarget.textContent, 10) || 0;
    }

    _update(quantity, inStock) {
        this.quantityTarget.textContent = quantity;

        if (this.hasInputTarget) {
            this.inputTarget.value = quantity;
        }

        // Désactiver le bouton - si stock = 0
        if (this.hasDecrementTarget) {
            this.decrementTarget.disabled = quantity <= 0;
        }

        // Badge de statut (En stock / Rupture)
        if (this.hasBadgeTarget) {
            this.badgeTarget.dataset.inStock = inStock ? '1' : '0';
            this.badgeTarget.textContent = inStock ? 'En stock' : 'Rupture';
        }

        // Animation flash sur la quantité
        this.quantityTarget.classList.remove('flash');
        void this.quantityTarget.offsetWidth; // reflow
        this.quantityTarget.classList.add('flash');
    }

    _setLoading(loading) {
        this.element.classList.toggle('stock-row--loading', loading);
    }
}
