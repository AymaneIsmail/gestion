import { Controller } from '@hotwired/stimulus';

const STEPS = [
    { icon: '🔍', label: 'Recherche sur Fragrantica…' },
    { icon: '📖', label: 'Lecture des notes olfactives…' },
    { icon: '✍️', label: 'Rédaction de la description…' },
    { icon: '🏭', label: 'Recherche des infos fabricant…' },
];

export default class extends Controller {
    static values = { url: String };
    static targets = ['button', 'textarea', 'spinner', 'overlay', 'overlayIcon', 'overlayLabel'];

    #stepInterval = null;

    async generate() {
        // Sur la page création, on lit les champs du formulaire
        const body = this.urlValue
            ? null
            : this.#buildFormBody();

        if (body !== null && !body.get('name')) {
            alert('Renseignez au minimum le nom du produit avant de générer une description.');
            return;
        }

        this.buttonTarget.disabled = true;
        this.spinnerTarget.hidden = false;
        this.overlayTarget.hidden = false;
        this.#startSteps();

        try {
            const url = this.urlValue || this.element.dataset.generateDescriptionNewUrlValue;
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: body ?? undefined,
            });

            const contentType = response.headers.get('content-type') ?? '';
            if (!contentType.includes('application/json')) {
                throw new Error(`Réponse inattendue du serveur (HTTP ${response.status}). Vérifiez que vous êtes bien connecté.`);
            }

            const data = await response.json();

            if (response.status === 429) {
                throw new Error(data.error ?? 'Quota IA dépassé, réessayez plus tard.');
            }

            if (!response.ok) {
                throw new Error(data.error ?? 'Erreur serveur');
            }

            this.textareaTarget.value = data.description;
            this.textareaTarget.dispatchEvent(new Event('input'));
        } catch (err) {
            alert(err.message);
        } finally {
            this.#stopSteps();
            this.buttonTarget.disabled = false;
            this.spinnerTarget.hidden = true;
            this.overlayTarget.hidden = true;
        }
    }

    #startSteps() {
        let i = 0;
        this.#showStep(STEPS[0]);
        this.#stepInterval = setInterval(() => {
            i = (i + 1) % STEPS.length;
            this.#showStep(STEPS[i]);
        }, 3000);
    }

    #showStep({ icon, label }) {
        this.overlayIconTarget.textContent = icon;
        this.overlayLabelTarget.textContent = label;
    }

    #stopSteps() {
        clearInterval(this.#stepInterval);
        this.#stepInterval = null;
    }

    #buildFormBody() {
        const form = this.element.closest('form');
        const body = new FormData();
        body.append('name',      form.querySelector('[name$="[name]"]')?.value?.trim()      ?? '');
        body.append('reference', form.querySelector('[name$="[reference]"]')?.value?.trim() ?? '');

        const categorySelect = form.querySelector('[name$="[category]"]');
        const categoryText   = categorySelect?.options[categorySelect.selectedIndex]?.text ?? '';
        body.append('category', categoryText === '-- Aucune catégorie --' ? '' : categoryText);

        return body;
    }
}
