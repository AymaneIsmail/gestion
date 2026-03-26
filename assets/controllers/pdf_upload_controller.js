import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['input', 'label', 'dropzone', 'overlay', 'submitBtn']

    pick() {
        this.inputTarget.click()
    }

    preview() {
        const file = this.inputTarget.files[0]
        if (file) {
            this.labelTarget.textContent = file.name
            this.dropzoneTarget.classList.add('img-dropzone--selected')
        }
    }

    submit() {
        if (!this.inputTarget.files.length) return
        this.overlayTarget.classList.add('pdf-loading-overlay--visible')
        this.submitBtnTarget.disabled = true
    }
}
