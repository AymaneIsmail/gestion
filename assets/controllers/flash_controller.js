import { Controller } from '@hotwired/stimulus';

/**
 * Flash controller — allows dismissing alert messages.
 * Auto-dismisses after 5 seconds.
 */
export default class extends Controller {
    connect() {
        this._timeout = setTimeout(() => this.close(), 5000);
    }

    close() {
        this.element.style.opacity = '0';
        this.element.style.transition = 'opacity 0.3s ease';

        setTimeout(() => {
            this.element.remove();
        }, 300);
    }

    disconnect() {
        clearTimeout(this._timeout);
    }
}
