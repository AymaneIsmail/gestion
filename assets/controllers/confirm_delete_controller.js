import { Controller } from '@hotwired/stimulus';

/**
 * Confirm delete controller — shows a browser confirm dialog before submitting.
 */
export default class extends Controller {
    static values = {
        message: { type: String, default: 'Êtes-vous sûr de vouloir supprimer cet élément ?' },
    };

    confirm(event) {
        if (!window.confirm(this.messageValue)) {
            event.preventDefault();
        }
    }
}
