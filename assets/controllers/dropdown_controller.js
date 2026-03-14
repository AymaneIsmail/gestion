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
            document.addEventListener('click', this._onOutsideClick);
            document.addEventListener('keydown', this._onKeydown);
        } else {
            this._removeListeners();
        }
    }

    close() {
        this.menuTarget.classList.remove('is-open');
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
