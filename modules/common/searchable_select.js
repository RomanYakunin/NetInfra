class SearchableSelect {
    static instances = [];

    constructor(select) {
        this.select = select;
        this.options = Array.from(select.options).filter(opt => opt.value !== '__add_new__');
        this.addOption = select.querySelector('option[value="__add_new__"]');

        this.wrapper = document.createElement('div');
        this.wrapper.className = 'searchable-select';

        this.searchInput = document.createElement('input');
        this.searchInput.type = 'text';
        this.searchInput.className = 'searchable-select-input';
        this.searchInput.placeholder = 'Поиск...';
        this.searchInput.addEventListener('input', () => this.filterOptions());
        this.searchInput.addEventListener('focus', () => {
            this.filterOptions();
            this.dropdown.style.display = 'block';
        });
        this.searchInput.addEventListener('click', (e) => e.stopPropagation());

        this.dropdown = document.createElement('div');
        this.dropdown.className = 'searchable-select-dropdown';
        this.updateDropdown();

        this.wrapper.appendChild(this.searchInput);
        this.wrapper.appendChild(this.dropdown);

        this.select.style.display = 'none';
        this.select.parentNode.insertBefore(this.wrapper, this.select);
        this.wrapper.appendChild(this.select);

        this.syncInputWithSelect();

        this._closeHandler = (e) => {
            if (!this.wrapper.contains(e.target)) {
                this.dropdown.style.display = 'none';
                this.syncInputWithSelect();
            }
        };
        document.addEventListener('click', this._closeHandler);

        this.select.searchableInstance = this;
        SearchableSelect.instances.push(this);
    }

    syncInputWithSelect() {
        if (this.select.value && this.select.value !== '__add_new__') {
            const selectedOption = this.select.options[this.select.selectedIndex];
            if (selectedOption && selectedOption.textContent) {
                this.searchInput.value = selectedOption.textContent;
            } else {
                this.searchInput.value = '';
            }
        } else {
            this.searchInput.value = '';
        }
    }

    updateDropdown(filterText = '') {
        this.dropdown.innerHTML = '';
        const lowerFilter = filterText.toLowerCase();
        const baseOptions = this.options.filter(opt => opt.value !== '__add_new__');

        if (lowerFilter === '') {
            baseOptions
                .sort((a, b) => a.textContent.localeCompare(b.textContent))
                .forEach(opt => {
                    const div = document.createElement('div');
                    div.className = 'searchable-select-option';
                    div.textContent = opt.textContent;
                    div.addEventListener('click', () => {
                        this.searchInput.value = opt.textContent;
                        this.select.value = opt.value;
                        this.dropdown.style.display = 'none';
                        this.select.dispatchEvent(new Event('change'));
                    });
                    this.dropdown.appendChild(div);
                });
        } else {
            const scored = baseOptions.map(opt => {
                const text = opt.textContent;
                const lowerText = text.toLowerCase();
                let score = 0;
                if (lowerText.startsWith(lowerFilter)) {
                    score = 1000;
                } else if (lowerText.includes(lowerFilter)) {
                    const index = lowerText.indexOf(lowerFilter);
                    score = 500 - index;
                } else {
                    score = -1;
                }
                return { opt, score };
            });

            scored.sort((a, b) => {
                if (b.score !== a.score) return b.score - a.score;
                return a.opt.textContent.localeCompare(b.opt.textContent);
            });

            scored.forEach(item => {
                const div = document.createElement('div');
                div.className = 'searchable-select-option';
                div.textContent = item.opt.textContent;
                div.addEventListener('click', () => {
                    this.searchInput.value = item.opt.textContent;
                    this.select.value = item.opt.value;
                    this.dropdown.style.display = 'none';
                    this.select.dispatchEvent(new Event('change'));
                });
                this.dropdown.appendChild(div);
            });
        }

        if (this.addOption) {
            const addDiv = document.createElement('div');
            addDiv.className = 'searchable-select-option add-new';
            addDiv.style.fontStyle = 'normal';
            addDiv.textContent = this.addOption.textContent;
            addDiv.addEventListener('click', () => {
                this.searchInput.value = '';
                this.select.value = '__add_new__';
                this.dropdown.style.display = 'none';
                this.select.dispatchEvent(new Event('change'));
            });
            this.dropdown.appendChild(addDiv);
        }
    }

    closeDropdown() {
        this.dropdown.style.display = 'none';
        this.syncInputWithSelect();
    }

    static closeAllExcept(exceptInstance) {
        SearchableSelect.instances.forEach(inst => {
            if (inst !== exceptInstance) {
                inst.closeDropdown();
            }
        });
    }

    filterOptions() {
        SearchableSelect.closeAllExcept(this);
        this.dropdown.style.top = '';
        this.dropdown.style.bottom = '';
        this.dropdown.style.display = 'block';
        this.updateDropdown(this.searchInput.value);

        void this.dropdown.offsetHeight;

        const inputRect = this.searchInput.getBoundingClientRect();
        const viewportHeight = window.innerHeight;
        const listHeight = this.dropdown.scrollHeight;
        const spaceBelow = viewportHeight - inputRect.bottom;
        const spaceAbove = inputRect.top;

        if (listHeight > spaceBelow && spaceAbove > spaceBelow) {
            this.dropdown.style.top = 'auto';
            this.dropdown.style.bottom = '100%';
            this.dropdown.style.display = 'flex';
            this.dropdown.style.flexDirection = 'column-reverse';
        } else {
            this.dropdown.style.top = '100%';
            this.dropdown.style.bottom = 'auto';
            this.dropdown.style.display = 'block';
            this.dropdown.style.flexDirection = '';
        }
    }

    destroy() {
        document.removeEventListener('click', this._closeHandler);
        SearchableSelect.instances = SearchableSelect.instances.filter(inst => inst !== this);
        if (this.select.searchableInstance === this) {
            delete this.select.searchableInstance;
        }
    }
}