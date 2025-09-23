(function () {
    const $ = (selector, context = document) => context.querySelector(selector);
    const $$ = (selector, context = document) => Array.from(context.querySelectorAll(selector));

    const parseJSON = (selector, fallback = {}) => {
        const node = $(selector);
        if (!node) {
            return fallback;
        }

        try {
            return JSON.parse(node.textContent || '{}');
        } catch (error) {
            console.warn('AdvancedNotes: unable to parse JSON for', selector, error);
            return fallback;
        }
    };

    const createElement = (tag, className, content) => {
        const element = document.createElement(tag);
        if (className) {
            element.className = className;
        }
        if (content !== undefined) {
            element.innerHTML = content;
        }
        return element;
    };

    document.addEventListener('DOMContentLoaded', () => {
        const form = $('#advanced-note-form');
        if (!form) {
            return;
        }

        const editor = $('#an-editor');
        const templates = parseJSON('#advanced-notes-templates', {});
        const modules = parseJSON('#advanced-notes-modules', {});
        const priorities = parseJSON('#advanced-notes-priorities', {});
        const initial = parseJSON('#advanced-notes-initial', {});

        const stepsOrder = ['basics', 'context', 'structure', 'editor'];
        const state = {
            template: initial.type || Object.keys(templates)[0] || 'meeting',
            step: initial.step && stepsOrder.includes(initial.step) ? initial.step : 'basics',
            tags: new Set(Array.isArray(initial.tags) ? initial.tags : []),
            modules: new Set(),
            stats: {
                wordCount: 0,
                readingTime: '0 min',
                blockCount: 0
            }
        };

        const stepperItems = $$('.an-stepper-item');
        const steps = $$('.an-step');
        const nextBtn = form.querySelector('[data-step-action="next"]');
        const prevBtn = form.querySelector('[data-step-action="prev"]');
        const saveBtn = $('#an-save-button');
        const currentStepInput = $('#an-current-step');
        const templateButtons = $$('.an-template-card');
        const moduleCheckboxes = $$('.an-module-checkbox');
        const tagInput = $('#note_tags');
        const tagCollection = $('#an-tag-collection');
        const removeLabelPattern = tagCollection ? (tagCollection.dataset.removeLabel || 'Remove %s') : 'Remove %s';
        const suggestionsList = $('#an-suggestions');
        const wordCountNode = $('#an-word-count');
        const readingTimeNode = $('#an-reading-time');
        const blockCountNode = $('#an-block-count');
        const priorityHint = $('#an-priority-hint');
        const collaboratorsSelect = $('#note_collaborators');
        const prioritySelect = $('#note_priority');
        const addBlockButton = $('#an-add-block');

        const getSelectedCollaborators = () => {
            if (!collaboratorsSelect) {
                return [];
            }
            return Array.from(collaboratorsSelect.selectedOptions || []).map(option => option.value);
        };

        const applyModuleState = (types = [], options = {}) => {
            const settings = { syncBlocks: true, ...options };
            const unique = Array.from(new Set(types.filter(Boolean)));
            state.modules = new Set(unique);
            moduleCheckboxes.forEach(cb => {
                const active = state.modules.has(cb.value);
                cb.checked = active;
                cb.parentElement.classList.toggle('is-active', active);
            });

            if (settings.syncBlocks) {
                syncBlocksWithModules();
            }
        };

        const getTemplateBlock = (type) => {
            const template = templates[state.template] || {};
            const templateBlock = Array.isArray(template.blocks)
                ? template.blocks.find(block => block.type === type)
                : null;
            if (templateBlock) {
                return templateBlock;
            }

            const module = modules[type] || {};
            return {
                type,
                title: module.name || type,
                content: module.placeholder || `<p>${module.description || ''}</p>`
            };
        };

        const findBlock = (type) => editor.querySelector(`.an-block[data-type="${type}"]`);

        const addBlock = (block = {}) => {
            const wrapper = createElement('section', 'an-block');
            wrapper.dataset.type = block.type || 'custom';
            const isCustomBlock = block.custom === true || block.custom === 'true';
            if (isCustomBlock) {
                wrapper.dataset.custom = 'true';
            }

            const header = createElement('div', 'an-block-header');
            const title = createElement('span', 'an-block-title', block.title || (modules[block.type] && modules[block.type].name) || block.type || 'Sección');
            const removeButton = createElement('button', 'btn btn-sm btn-outline-danger', '<i class="fa-solid fa-trash"></i>');
            removeButton.type = 'button';
            removeButton.addEventListener('click', () => {
                wrapper.remove();
                updateStats();
            });

            header.append(title, removeButton);

            const body = createElement('div', 'an-block-body');
            body.contentEditable = 'true';
            body.innerHTML = block.content || '';
            body.addEventListener('input', updateStats);

            wrapper.append(header, body);
            editor.append(wrapper);

            return wrapper;
        };

        const renderBlocks = (blocks = [], options = {}) => {
            const settings = { syncModules: true, ...options };
            editor.innerHTML = '';
            if (settings.syncModules) {
                const blockTypes = blocks.map(block => block && block.type).filter(Boolean);
                if (blockTypes.length) {
                    applyModuleState(blockTypes, { syncBlocks: false });
                } else {
                    applyModuleState([], { syncBlocks: false });
                }
            }
            blocks.forEach(block => addBlock(block));
            updateStats();
        };

        const syncBlocksWithModules = () => {
            const currentBlocks = $$('.an-block', editor);
            currentBlocks.forEach(block => {
                const type = block.dataset.type;
                const isCustom = block.dataset.custom === 'true';
                if (type && !state.modules.has(type) && !isCustom) {
                    block.remove();
                }
            });

            state.modules.forEach(type => {
                if (!findBlock(type)) {
                    addBlock(getTemplateBlock(type));
                }
            });

            updateStats();
        };

        const highlightTemplate = (templateKey) => {
            templateButtons.forEach(button => {
                button.classList.toggle('is-active', button.dataset.template === templateKey);
            });
        };

        const updatePriorityHint = () => {
            const priorityValue = prioritySelect ? prioritySelect.value : 'medium';
            const description = priorities[priorityValue] ? priorities[priorityValue].description : '';
            if (priorityHint) {
                priorityHint.textContent = description || '';
            }
        };

        const updateSuggestions = (templateKey) => {
            suggestionsList.innerHTML = '';
            const template = templates[templateKey] || {};
            const items = Array.isArray(template.recommended_modules) ? template.recommended_modules : [];
            if (!items.length) {
                if (template.description) {
                    const fallback = createElement('li', null, template.description);
                    suggestionsList.appendChild(fallback);
                }
                return;
            }

            items.forEach(module => {
                const li = createElement('li', null, module.description || module.name || module.type);
                suggestionsList.appendChild(li);
            });
        };

        const renderTags = () => {
            if (!tagCollection) {
                return;
            }
            tagCollection.innerHTML = '';
            Array.from(state.tags).forEach(tag => {
                const pill = createElement('span', 'an-tag');
                pill.textContent = tag;
                const remove = createElement('button');
                remove.type = 'button';
                remove.innerHTML = '<i class="fa-solid fa-xmark"></i>';
                const formattedLabel = removeLabelPattern.includes('%s')
                    ? removeLabelPattern.replace('%s', tag)
                    : `${removeLabelPattern} ${tag}`;
                remove.setAttribute('aria-label', formattedLabel);
                remove.addEventListener('click', () => {
                    state.tags.delete(tag);
                    renderTags();
                });
                pill.appendChild(remove);
                tagCollection.appendChild(pill);
            });
        };

        const addTag = (tag) => {
            if (!tag) {
                return;
            }
            const clean = tag.trim();
            if (!clean) {
                return;
            }
            state.tags.add(clean);
            renderTags();
        };

        const applyTemplate = (templateKey) => {
            if (!templateKey || !templates[templateKey]) {
                return;
            }

            state.template = templateKey;
            highlightTemplate(templateKey);
            updateSuggestions(templateKey);

            const template = templates[templateKey];
            let tagsUpdated = false;
            if (Array.isArray(template.default_tags)) {
                template.default_tags.forEach(tag => {
                    if (typeof tag === 'string' && tag.trim()) {
                        const sizeBefore = state.tags.size;
                        state.tags.add(tag.trim());
                        if (state.tags.size !== sizeBefore) {
                            tagsUpdated = true;
                        }
                    }
                });
            }
            if (tagsUpdated) {
                renderTags();
            }

            const moduleTypes = Array.isArray(template.recommended_modules)
                ? template.recommended_modules.map(item => {
                    if (!item) {
                        return null;
                    }
                    if (typeof item === 'string') {
                        return item;
                    }
                    return item.type || null;
                }).filter(Boolean)
                : [];

            applyModuleState(moduleTypes, { syncBlocks: false });

            if (Array.isArray(template.blocks) && template.blocks.length) {
                renderBlocks(template.blocks, { syncModules: false });
            } else {
                editor.innerHTML = '';
                updateStats();
            }

            syncBlocksWithModules();
        };

        const updateStats = () => {
            const text = (editor.textContent || '').trim();
            const words = text ? text.split(/\s+/).filter(Boolean) : [];
            const wordCount = words.length;
            const blockCount = editor.querySelectorAll('.an-block').length;
            const readingMinutes = Math.max(1, Math.ceil(wordCount / 200));

            state.stats.wordCount = wordCount;
            state.stats.blockCount = blockCount;
            state.stats.readingTime = `${readingMinutes} min`;

            if (wordCountNode) {
                wordCountNode.textContent = wordCount;
            }
            if (readingTimeNode) {
                readingTimeNode.textContent = state.stats.readingTime;
            }
            if (blockCountNode) {
                blockCountNode.textContent = blockCount;
            }
        };

        const executeCommand = (command, value) => {
            editor.focus();
            if (command === 'createLink') {
                const url = window.prompt('https://');
                if (url) {
                    document.execCommand('createLink', false, url);
                }
                return;
            }

            if (command === 'highlight') {
                document.execCommand('backColor', false, '#fff59d');
                return;
            }

            if (command === 'formatBlock') {
                document.execCommand('formatBlock', false, value || 'p');
                return;
            }

            document.execCommand(command, false, value || null);
        };

        const insertChecklistBlock = () => {
            const moduleInfo = modules.actions || {};
            state.modules.add('actions');
            applyModuleState(Array.from(state.modules), { syncBlocks: false });
            addBlock({
                type: 'actions',
                title: moduleInfo.name || 'Checklist',
                content: `<ul class="list-unstyled"><li>⟡ ${moduleInfo.placeholder || ''}</li></ul>`
            });
            syncBlocksWithModules();
            updateStats();
        };

        const insertDecisionBlock = () => {
            const moduleInfo = modules.decisions || {};
            state.modules.add('decisions');
            applyModuleState(Array.from(state.modules), { syncBlocks: false });
            addBlock({
                type: 'decisions',
                title: moduleInfo.name || 'Decision',
                content: `<p>${moduleInfo.placeholder || ''}</p>`
            });
            syncBlocksWithModules();
            updateStats();
        };

        const insertTable = () => {
            const html = '<table class="table table-sm"><thead><tr><th>Idea</th><th>Notas</th></tr></thead><tbody><tr><td></td><td></td></tr></tbody></table>';
            document.execCommand('insertHTML', false, html);
            updateStats();
        };

        const addCustomBlock = () => {
            const defaultTitle = addBlockButton ? (addBlockButton.dataset.defaultTitle || 'Custom block') : 'Custom block';
            const defaultPlaceholder = addBlockButton ? (addBlockButton.dataset.defaultPlaceholder || '') : '';
            const wrapper = addBlock({
                type: `custom-${Date.now()}`,
                title: defaultTitle,
                content: '',
                custom: true
            });
            const bodyNode = wrapper ? $('.an-block-body', wrapper) : null;
            if (bodyNode) {
                bodyNode.textContent = defaultPlaceholder;
            }
            updateStats();
            if (editor) {
                editor.focus();
            }
        };

        const setStep = (step) => {
            const index = stepsOrder.indexOf(step);
            if (index === -1) {
                return;
            }

            state.step = step;
            if (currentStepInput) {
                currentStepInput.value = step;
            }

            steps.forEach(node => node.classList.toggle('is-active', node.dataset.step === step));
            stepperItems.forEach(item => item.classList.toggle('is-active', item.dataset.target === step));

            if (prevBtn) {
                prevBtn.disabled = index === 0;
            }
            if (nextBtn) {
                nextBtn.classList.toggle('d-none', index === stepsOrder.length - 1);
            }
            if (saveBtn) {
                saveBtn.classList.toggle('d-none', index !== stepsOrder.length - 1);
            }
        };

        const goToNextStep = () => {
            const index = stepsOrder.indexOf(state.step);
            if (index < stepsOrder.length - 1) {
                setStep(stepsOrder[index + 1]);
            }
        };

        const goToPreviousStep = () => {
            const index = stepsOrder.indexOf(state.step);
            if (index > 0) {
                setStep(stepsOrder[index - 1]);
            }
        };

        const collectBlocks = () =>
            $$('.an-block', editor).map(block => {
                const titleNode = $('.an-block-title', block);
                const bodyNode = $('.an-block-body', block);
                const payload = {
                    type: block.dataset.type || 'custom',
                    title: titleNode ? titleNode.textContent.trim() : '',
                    content: bodyNode ? bodyNode.innerHTML : ''
                };
                if (block.dataset.custom === 'true') {
                    payload.custom = true;
                }
                return payload;
            });

        const collectSuggestions = () =>
            $$('#an-suggestions li').map(item => item.textContent.trim()).filter(Boolean);

        const buildPayload = () => {
            const titleField = $('#note_title');
            const statusField = $('#note_status');
            const dueField = $('#note_due_date');
            const summaryField = $('#note_summary');

            const payload = {
                idnote: initial.idnote || null,
                title: titleField ? titleField.value.trim() : '',
                type: state.template,
                status: statusField ? statusField.value : 'draft',
                priority: prioritySelect ? prioritySelect.value : 'medium',
                dueDate: dueField ? dueField.value : '',
                tags: Array.from(state.tags),
                collaborators: getSelectedCollaborators(),
                summary: summaryField ? summaryField.value.trim() : '',
                content: {
                    html: editor.innerHTML,
                    blocks: collectBlocks(),
                    wordCount: state.stats.wordCount,
                    readingTime: state.stats.readingTime
                },
                metadata: {
                    modules: Array.from(state.modules),
                    suggestions: collectSuggestions(),
                    stats: state.stats
                }
            };

            const payloadInput = $('#note_payload');
            if (payloadInput) {
                payloadInput.value = JSON.stringify(payload);
            }
            return true;
        };

        // Event wiring
        stepperItems.forEach(item => item.addEventListener('click', () => setStep(item.dataset.target)));
        if (nextBtn) {
            nextBtn.addEventListener('click', goToNextStep);
        }
        if (prevBtn) {
            prevBtn.addEventListener('click', goToPreviousStep);
        }

        templateButtons.forEach(button => {
            button.addEventListener('click', () => applyTemplate(button.dataset.template));
        });

        moduleCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                if (checkbox.checked) {
                    state.modules.add(checkbox.value);
                } else {
                    state.modules.delete(checkbox.value);
                }
                checkbox.parentElement.classList.toggle('is-active', checkbox.checked);
                syncBlocksWithModules();
            });
        });

        if (addBlockButton) {
            addBlockButton.addEventListener('click', addCustomBlock);
        }

        if (tagInput) {
            tagInput.addEventListener('keydown', event => {
                if (['Enter', 'Tab', ','].includes(event.key)) {
                    event.preventDefault();
                    addTag(tagInput.value);
                    tagInput.value = '';
                }
            });
            tagInput.addEventListener('blur', () => {
                addTag(tagInput.value);
                tagInput.value = '';
            });
        }

        const toolbar = $('.an-editor-toolbar');
        if (toolbar) {
            toolbar.addEventListener('click', event => {
                const target = event.target.closest('[data-command]');
                if (!target) {
                    return;
                }
                event.preventDefault();
                executeCommand(target.dataset.command, target.dataset.value);
                updateStats();
            });
        }

        const insertChecklistButton = $('#an-insert-checklist');
        if (insertChecklistButton) {
            insertChecklistButton.addEventListener('click', insertChecklistBlock);
        }
        const insertDecisionButton = $('#an-insert-decision');
        if (insertDecisionButton) {
            insertDecisionButton.addEventListener('click', insertDecisionBlock);
        }
        const insertTableButton = $('#an-insert-table');
        if (insertTableButton) {
            insertTableButton.addEventListener('click', insertTable);
        }

        if (editor) {
            editor.addEventListener('input', updateStats);
        }

        if (prioritySelect) {
            prioritySelect.addEventListener('change', updatePriorityHint);
        }

        form.addEventListener('submit', event => {
            if (!buildPayload()) {
                event.preventDefault();
            }
        });

        // Initial setup
        highlightTemplate(state.template);
        updateSuggestions(state.template);
        renderTags();
        updatePriorityHint();

        if (collaboratorsSelect && Array.isArray(initial.collaborators)) {
            const selected = new Set(initial.collaborators);
            Array.from(collaboratorsSelect.options).forEach(option => {
                option.selected = selected.has(option.value);
            });
        }

        const initialModules = Array.isArray((initial.metadata || {}).modules)
            ? initial.metadata.modules
            : [];
        const initialContent = initial.content || {};

        if (Array.isArray(initialContent.blocks) && initialContent.blocks.length) {
            applyModuleState(initialModules.length ? initialModules : initialContent.blocks.map(block => block.type), { syncBlocks: false });
            renderBlocks(initialContent.blocks);
        } else if (initialContent.html) {
            editor.innerHTML = initialContent.html;
            const blockNodes = $$('.an-block', editor);
            if (blockNodes.length) {
                const detectedModules = blockNodes.map(block => block.dataset.type).filter(Boolean);
                applyModuleState(detectedModules, { syncBlocks: false });
            } else if (initialModules.length) {
                applyModuleState(initialModules);
            }
        } else {
            applyTemplate(state.template);
        }

        if (!state.modules.size && initialModules.length) {
            applyModuleState(initialModules);
        }

        setStep(state.step);
        updateStats();
    });
})();
