document.addEventListener('DOMContentLoaded', () => {
    const picker = document.getElementById('coordinator-scholar-picker');
    const search = document.getElementById('scholar-search');
    const selectedId = document.getElementById('scholar_id');
    const results = document.getElementById('scholar-search-results');
    const form = search?.closest('form');

    if (!picker || !search || !selectedId || !results || !form) {
        return;
    }

    const options = Array.from(results.querySelectorAll('.coordinator-scholar-result'));
    const noResults = results.querySelector('.coordinator-scholar-no-results');

    const closeResults = () => {
        results.hidden = true;
        search.setAttribute('aria-expanded', 'false');
    };

    const filterResults = () => {
        const query = search.value.trim().toLocaleLowerCase();
        let visibleCount = 0;

        options.forEach((option) => {
            const matches = option.dataset.searchText.toLocaleLowerCase().includes(query);
            option.hidden = !matches;
            if (matches) {
                visibleCount++;
            }
        });

        if (noResults) {
            noResults.hidden = visibleCount > 0;
        }

        results.hidden = false;
        search.setAttribute('aria-expanded', 'true');
    };

    search.addEventListener('focus', filterResults);
    search.addEventListener('input', () => {
        selectedId.value = '';
        search.setCustomValidity('');
        filterResults();
    });

    options.forEach((option) => {
        option.addEventListener('click', () => {
            selectedId.value = option.dataset.scholarId;
            search.value = option.dataset.searchText;
            search.setCustomValidity('');
            closeResults();
        });
    });

    document.addEventListener('click', (event) => {
        if (!picker.contains(event.target)) {
            closeResults();
        }
    });

    form.addEventListener('submit', (event) => {
        if (selectedId.value !== '') {
            return;
        }

        event.preventDefault();
        search.setCustomValidity('Select a scholar from the search results.');
        search.reportValidity();
    });
});
