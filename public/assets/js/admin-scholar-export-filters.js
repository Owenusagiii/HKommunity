document.addEventListener('DOMContentLoaded', () => {
    const academicYear = document.querySelector('[data-scholar-export-year]');
    const semester = document.querySelector('[data-scholar-export-semester]');

    if (!(academicYear instanceof HTMLSelectElement) || !(semester instanceof HTMLSelectElement)) {
        console.error('Unable to initialize Scholar Export filters: academic year or semester control was not found.');
        return;
    }

    const updateSemesters = () => {
        let firstAvailableSemester = null;
        let selectedSemesterIsAvailable = false;

        Array.from(semester.options).forEach((option, index) => {
            if (index === 0 && option.value === '') {
                option.hidden = false;
                option.disabled = true;
                return;
            }

            const isAvailable = option.dataset.academicYearId === academicYear.value;
            option.hidden = !isAvailable;
            option.disabled = !isAvailable;

            if (isAvailable && firstAvailableSemester === null) {
                firstAvailableSemester = option;
            }
            if (isAvailable && option.selected) {
                selectedSemesterIsAvailable = true;
            }
        });

        if (!selectedSemesterIsAvailable && firstAvailableSemester) {
            firstAvailableSemester.selected = true;
        } else if (!firstAvailableSemester) {
            semester.selectedIndex = 0;
        }
    };

    academicYear.addEventListener('change', updateSemesters);
    updateSemesters();
});
