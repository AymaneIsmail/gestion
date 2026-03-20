import { Controller } from '@hotwired/stimulus';

/**
 * Dropdown controller — toggles a menu open/closed.
 * Closes on outside click or Escape key.
 */
export default class extends Controller {
    static targets = ['menu'];

    connect() {
        this._onOutsideClick = this._handleOutsideClick.bind(this);
        this._onKeydown = this._handleKeydown.bind(this);
    }

    toggle() {
        const isOpen = this.menuTarget.classList.toggle('is-open');

        if (isOpen) {
            this._adjustPosition();
            document.addEventListener('click', this._onOutsideClick);
            document.addEventListener('keydown', this._onKeydown);
        } else {
            this._removeListeners();
        }
    }

    _adjustPosition() {
        const menu = this.menuTarget;
        // Reset pour recalculer
        menu.style.right = '';
        menu.style.left = '';

        const rect = menu.getBoundingClientRect();
        if (rect.right > window.innerWidth) {
            // Déborde à droite → aligner à droite du trigger
            menu.style.left = 'auto';
            menu.style.right = '0';
        } else if (rect.left < 0) {
            // Déborde à gauche → aligner à gauche du trigger
            menu.style.right = 'auto';
            menu.style.left = '0';
        }
    }

    close() {
        this.menuTarget.classList.remove('is-open');
        this.menuTarget.style.right = '';
        this.menuTarget.style.left = '';
        this._removeListeners();
    }

    _handleOutsideClick(event) {
        if (!this.element.contains(event.target)) {
            this.close();
        }
    }

    _handleKeydown(event) {
        if (event.key === 'Escape') {
            this.close();
        }
    }

    _removeListeners() {
        document.removeEventListener('click', this._onOutsideClick);
        document.removeEventListener('keydown', this._onKeydown);
    }

    disconnect() {
        this._removeListeners();
    }
}
