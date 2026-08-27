import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['item'];
    static values = { duration: { type: Number, default: 5000 } };

    connect() {
        this.itemTargets.forEach((item) => this.schedule(item));
    }

    dismiss(event) {
        this.remove(event.currentTarget.closest('[data-toast-target="item"]'));
    }

    schedule(item) {
        window.setTimeout(() => this.remove(item), this.durationValue);
    }

    remove(item) {
        if (!item || item.classList.contains('toast--leaving')) {
            return;
        }

        item.classList.add('toast--leaving');
        item.addEventListener('animationend', () => item.remove(), { once: true });
    }
}
