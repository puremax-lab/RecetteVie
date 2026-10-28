import { Controller } from '@hotwired/stimulus';

/*
 * Shows a live character count under a text field, e.g. "12 characters · minimum 20".
 *
 * <textarea data-controller="char-counter"
 *           data-char-counter-min-value="20"
 *           data-char-counter-max-value="0"
 *           data-char-counter-label-value="%count% characters"
 *           data-char-counter-min-label-value="minimum 20">
 */
export default class extends Controller {
    static values = { min: Number, max: Number, label: String, minLabel: String };

    connect() {
        this.counter = document.createElement('div');
        this.counter.className = 'char-counter form-text';
        this.counter.setAttribute('aria-live', 'polite');
        this.element.insertAdjacentElement('afterend', this.counter);

        this.update = this.update.bind(this);
        this.element.addEventListener('input', this.update);
        this.update();
    }

    disconnect() {
        this.element.removeEventListener('input', this.update);
        this.counter.remove();
    }

    update() {
        const count = this.element.value.trim().length;
        const max = this.maxValue ? ` / ${this.maxValue}` : '';
        this.counter.textContent = `${this.labelValue.replace('%count%', count + max)} · ${this.minLabelValue}`;

        // Empty is fine (the other language can be translated); too short is not.
        const tooShort = count > 0 && count < this.minValue;
        this.counter.classList.toggle('is-short', tooShort);
        this.counter.classList.toggle('is-ok', count >= this.minValue);
    }
}
