import { Controller } from '@hotwired/stimulus';

/**
 * Nav controller — gère l'ouverture/fermeture du menu burger mobile.
 * Bloque le scroll du body quand le menu est ouvert.
 */
export default class extends Controller {
    static targets = ['menu', 'burger', 'overlay'];
    static classes = ['open'];

    connect() {
        this._onKeydown = this._handleKeydown.bind(this);
    }

    toggle() {
        const isOpen = this.menuTarget.classList.toggle('is-open');
        this.burgerTarget.classList.toggle('is-open', isOpen);
        this.overlayTarget.classList.toggle('is-visible', isOpen);
        document.body.classList.toggle('nav-open', isOpen);

        if (isOpen) {
            document.addEventListener('keydown', this._onKeydown);
        } else {
            document.removeEventListener('keydown', this._onKeydown);
        }
    }

    close() {
        this.menuTarget.classList.remove('is-open');
        this.burgerTarget.classList.remove('is-open');
        this.overlayTarget.classList.remove('is-visible');
        document.body.classList.remove('nav-open');
        document.removeEventListener('keydown', this._onKeydown);
    }

    _handleKeydown(event) {
        if (event.key === 'Escape') this.close();
    }

    disconnect() {
        document.removeEventListener('keydown', this._onKeydown);
        document.body.classList.remove('nav-open');
    }
}
