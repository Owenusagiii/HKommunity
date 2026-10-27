document.addEventListener('DOMContentLoaded', () => {
    const filter = document.getElementById('quick-links-filter');
    const count = document.getElementById('quick-links-count');
    const grid = document.querySelector('.scholar-quick-links-page .quick-links-grid');

    if (!(filter instanceof HTMLSelectElement) || !(count instanceof HTMLElement) || !(grid instanceof HTMLElement)) {
        return;
    }

    const cards = Array.from(grid.querySelectorAll('.quick-link-card'));
    const latestCards = [...cards]
        .sort((first, second) => Number(second.dataset.createdAt) - Number(first.dataset.createdAt))
        .slice(0, 2);
    const latestCardSet = new Set(latestCards);

    const updateLinks = () => {
        const showLatest = filter.value === 'latest';
        let visibleCount = 0;

        cards.forEach((card) => {
            const visible = !showLatest || latestCardSet.has(card);
            card.hidden = !visible;
            if (visible) {
                visibleCount++;
            }
        });

        (showLatest ? latestCards : cards).forEach((card) => grid.append(card));
        count.textContent = `${visibleCount} ${visibleCount === 1 ? 'resource' : 'resources'}`;
    };

    filter.addEventListener('change', updateLinks);
    updateLinks();
});
