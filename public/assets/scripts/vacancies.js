class JobVacancyManager {
    constructor() {
        this.searchInput = document.getElementById('searchInput');
        this.sortSelect = document.getElementById('sortSelect');
        this.viewToggle = document.getElementById('viewToggle');
        this.vacancyCards = document.querySelectorAll('.vacancy-card');
        this.resultsInfo = document.getElementById('resultsInfo');
        this.resultCount = document.getElementById('resultCount');
        this.noResults = document.getElementById('noResults');
        this.vacanciesContainer = document.getElementById('vacanciesContainer');
        
        this.allCards = Array.from(this.vacancyCards);
        this.init();
    }

    init() {
        this.searchInput.addEventListener('input', () => this.debounce(() => this.filterAndSort(), 300));
        this.sortSelect.addEventListener('change', () => this.filterAndSort());
        this.viewToggle.addEventListener('change', () => this.filterAndSort());

        this.updateDeadlineBadges();
        
        this.filterAndSort();
    }

    updateDeadlineBadges() {
        const now = new Date();
        
        this.vacancyCards.forEach(card => {
            const deadlineStr = card.dataset.deadline;
            if (deadlineStr) {
                const deadline = new Date(deadlineStr);
                const daysDiff = Math.ceil((deadline - now) / (1000 * 60 * 60 * 24));
                const badge = card.querySelector('.deadline-badge');
                
                if (daysDiff <= 3 && daysDiff >= 0) {
                    badge.classList.add('urgent');
                } else {
                    badge.classList.remove('urgent');
                }
            }
        });
    }

    filterAndSort() {
        const searchTerm = this.searchInput.value.toLowerCase().trim();
        const sortBy = this.sortSelect.value;
        const viewFilter = this.viewToggle.value;
        
        let visibleCards = [];
        
        this.allCards.forEach(card => {
            let isVisible = true;
            
            if (searchTerm) {
                const title = card.dataset.title;
                const skills = card.dataset.skills;
                const description = card.querySelector('.vacancy-description').textContent.toLowerCase();
                
                isVisible = title.includes(searchTerm) || 
                          skills.includes(searchTerm) || 
                          description.includes(searchTerm);
            }
            
            if (isVisible && viewFilter !== 'all') {
                if (viewFilter === 'recent') {
                    const cardDate = new Date(card.dataset.date);
                    const now = new Date();
                    const daysDiff = (now - cardDate) / (1000 * 60 * 60 * 24);
                    isVisible = daysDiff <= 7;
                } else if (viewFilter === 'urgent') {
                    const deadlineBadge = card.querySelector('.deadline-badge');
                    isVisible = deadlineBadge && deadlineBadge.classList.contains('urgent');
                }
            }
            
            if (isVisible) {
                visibleCards.push(card);
            }
            
            if (isVisible) {
                card.classList.remove('hidden');
                card.style.display = 'block';
            } else {
                card.classList.add('hidden');
                card.style.display = 'none';
            }
        });
        
        this.sortCards(visibleCards, sortBy);
        
        this.updateResultsInfo(visibleCards.length);
        
        this.toggleNoResults(visibleCards.length === 0);
    }

    sortCards(cards, sortBy) {
        cards.sort((a, b) => {
            switch (sortBy) {
                case 'date_desc':
                    return new Date(b.dataset.date) - new Date(a.dataset.date);
                case 'date_asc':
                    return new Date(a.dataset.date) - new Date(b.dataset.date);
                case 'title_asc':
                    return a.dataset.title.localeCompare(b.dataset.title);
                case 'title_desc':
                    return b.dataset.title.localeCompare(a.dataset.title);
                case 'deadline_asc':
                    return new Date(a.dataset.deadline) - new Date(b.dataset.deadline);
                default:
                    return 0;
            }
        });
        
        cards.forEach(card => {
            this.vacanciesContainer.appendChild(card);
        });
    }

    updateResultsInfo(count) {
        this.resultCount.textContent = count;
        const totalCards = this.allCards.length;
        
        if (count === totalCards) {
            this.resultsInfo.innerHTML = `Showing <span id="resultCount">${count}</span> job vacancies`;
        } else {
            this.resultsInfo.innerHTML = `Showing <span id="resultCount">${count}</span> of ${totalCards} job vacancies`;
        }
    }

    toggleNoResults(show) {
        this.noResults.style.display = show ? 'block' : 'none';
        this.vacanciesContainer.style.display = show ? 'none' : 'block';
    }

    debounce(func, wait) {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(func, wait);
    }

    addVacancy(vacancyData) {
        const card = this.createVacancyCard(vacancyData);
        this.vacanciesContainer.appendChild(card);
        this.allCards.push(card);
        this.vacancyCards = document.querySelectorAll('.vacancy-card');
        this.filterAndSort();
    }

    refresh() {
        this.vacancyCards = document.querySelectorAll('.vacancy-card');
        this.allCards = Array.from(this.vacancyCards);
        this.updateDeadlineBadges();
        this.filterAndSort();
    }

    capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    window.jobManager = new JobVacancyManager();
});

function updateVacancyCount() {
    if (window.jobManager) {
        window.jobManager.filterAndSort();
    }
}

function refreshVacancies() {
    if (window.jobManager) {
        window.jobManager.refresh();
    }
}