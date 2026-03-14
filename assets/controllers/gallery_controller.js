import { Controller } from '@hotwired/stimulus';

/**
 * Gallery controller — switches main image when a thumbnail is clicked.
 */
export default class extends Controller {
    static targets = ['main', 'thumb'];

    select(event) {
        const src = event.params.src;

        this.mainTarget.src = src;

        this.thumbTargets.forEach((thumb) => {
            thumb.classList.toggle(
                'product-gallery__thumb--active',
                thumb.dataset.gallerySrcParam === src
            );
        });
    }
}
