import { Controller } from '@hotwired/stimulus';

/**
 * Image preview controller.
 * Affiche un aperçu des images sélectionnées avant l'envoi.
 * Permet aussi de retirer une image de la sélection.
 */
export default class extends Controller {
    static targets = ['input', 'grid', 'empty', 'count'];

    // DataTransfer maintenu en mémoire pour pouvoir retirer des fichiers
    _transfer = new DataTransfer();

    connect() {
        // Si des fichiers sont déjà dans l'input (retour arrière du navigateur)
        if (this.inputTarget.files?.length) {
            this._syncFromInput();
        }
    }

    pick() {
        this.inputTarget.click();
    }

    change() {
        // Ajoute les nouveaux fichiers à ceux déjà en attente (sans doublon par nom+taille)
        const existing = new Set(
            [...this._transfer.files].map(f => `${f.name}-${f.size}`)
        );

        for (const file of this.inputTarget.files) {
            if (!existing.has(`${file.name}-${file.size}`)) {
                this._transfer.items.add(file);
            }
        }

        // Réassigne le FileList combiné à l'input
        this.inputTarget.files = this._transfer.files;
        this._render();
    }

    remove(event) {
        const index = parseInt(event.currentTarget.dataset.index, 10);

        // Reconstruit le DataTransfer sans le fichier supprimé
        const next = new DataTransfer();
        [...this._transfer.files].forEach((file, i) => {
            if (i !== index) next.items.add(file);
        });

        this._transfer = next;
        this.inputTarget.files = this._transfer.files;
        this._render();
    }

    _syncFromInput() {
        this._transfer = new DataTransfer();
        for (const file of this.inputTarget.files) {
            this._transfer.items.add(file);
        }
        this._render();
    }

    _render() {
        const files = [...this._transfer.files];
        this.gridTarget.innerHTML = '';

        if (files.length === 0) {
            this._showEmpty();
            return;
        }

        this._hideEmpty();

        if (this.hasCountTarget) {
            this.countTarget.textContent = `${files.length} fichier${files.length > 1 ? 's' : ''} sélectionné${files.length > 1 ? 's' : ''}`;
        }

        files.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'img-preview-item';
            item.dataset.index = index;

            // Thumb + bouton retirer
            const thumb = document.createElement('div');
            thumb.className = 'img-preview-thumb';

            const img = document.createElement('img');
            img.className = 'img-preview-img';
            img.alt = file.name; // textContent safe, pas de HTML

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'img-preview-remove';
            removeBtn.dataset.index = index;
            removeBtn.dataset.action = 'image-preview#remove';
            removeBtn.setAttribute('aria-label', 'Retirer cette image');
            removeBtn.innerHTML = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>';

            thumb.appendChild(img);
            thumb.appendChild(removeBtn);

            const name = document.createElement('p');
            name.className = 'img-preview-name';
            name.title = file.name;
            name.textContent = file.name; // textContent, jamais innerHTML

            const sizeEl = document.createElement('p');
            sizeEl.className = 'img-preview-size';
            sizeEl.textContent = this._formatSize(file.size);

            item.appendChild(thumb);
            item.appendChild(name);
            item.appendChild(sizeEl);

            // Charge l'aperçu via FileReader
            const reader = new FileReader();
            reader.onload = (e) => {
                img.src = e.target.result;
                img.classList.add('is-loaded');
            };
            reader.readAsDataURL(file);

            this.gridTarget.appendChild(item);
        });
    }

    _showEmpty() {
        if (this.hasEmptyTarget) this.emptyTarget.hidden = false;
        if (this.hasCountTarget) this.countTarget.textContent = '';
    }

    _hideEmpty() {
        if (this.hasEmptyTarget) this.emptyTarget.hidden = true;
    }

    _formatSize(bytes) {
        if (bytes < 1024) return `${bytes} o`;
        if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} Ko`;
        return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`;
    }
}
