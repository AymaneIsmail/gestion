import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['panel']

    open() {
        this.panelTarget.classList.add('modal--open')
        document.body.style.overflow = 'hidden'
    }

    close() {
        this.panelTarget.classList.remove('modal--open')
        document.body.style.overflow = ''
    }

    backdropClick(event) {
        if (event.target === event.currentTarget) {
            this.close()
        }
    }
}
