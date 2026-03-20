import { Controller } from '@hotwired/stimulus';

/**
 * Gallery controller — switches main image when a thumbnail is clicked.
 */
export default class extends Controller {
    static targets = ['main', 'thumb', 'download'];

    select(event) {
        const src = event.params.src;
        const download = event.params.download;

        this.mainTarget.src = src;

        if (this.hasDownloadTarget && download) {
            this.downloadTarget.href = download;
        }

        this.thumbTargets.forEach((thumb) => {
            thumb.classList.toggle(
                'product-gallery__thumb--active',
                thumb.dataset.gallerySrcParam === src
            );
        });
    }
}
