import { Controller } from '@hotwired/stimulus';

// Filters an already-rendered table entirely client-side (no request, no page reload).
// Each filter input carries data-list-filter-field="x" matching a data-x attribute on
// every row; text inputs do a substring match, selects match a whole word within the
// row's (possibly space-separated, e.g. several dependency managers) value.
export default class extends Controller {
    static targets = ['filter', 'row', 'empty'];

    connect() {
        this.filter();
    }

    filter() {
        let visibleCount = 0;

        this.rowTargets.forEach((row) => {
            const matches = this.filterTargets.every((filter) => this.matches(row, filter));

            row.hidden = !matches;

            if (matches) {
                visibleCount++;
            }
        });

        if (this.hasEmptyTarget) {
            this.emptyTarget.hidden = visibleCount > 0;
        }
    }

    reset() {
        this.filterTargets.forEach((filter) => {
            filter.value = '';
        });

        this.filter();
    }

    matches(row, filter) {
        const value = filter.value.trim().toLowerCase();

        if (value === '') {
            return true;
        }

        const rowValue = (row.dataset[filter.dataset.listFilterField] || '').toLowerCase();

        return filter.tagName === 'SELECT'
            ? rowValue.split(/\s+/).includes(value)
            : rowValue.includes(value);
    }
}
