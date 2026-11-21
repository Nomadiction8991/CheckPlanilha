(() => {
    const patterns = {
        cpf: {
            inputMode: 'numeric',
            maxDigits: 11,
            format(value) {
                const digits = onlyDigits(value).slice(0, 11);

                if (digits.length <= 3) {
                    return digits;
                }

                if (digits.length <= 6) {
                    return `${digits.slice(0, 3)}.${digits.slice(3)}`;
                }

                if (digits.length <= 9) {
                    return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`;
                }

                return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9)}`;
            },
        },
        cnpj: {
            inputMode: 'text',
            character: /[0-9A-Z]/i,
            format(value) {
                const identifier = String(value ?? '').toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 14);

                if (identifier.length <= 2) {
                    return identifier;
                }

                if (identifier.length <= 5) {
                    return `${identifier.slice(0, 2)}.${identifier.slice(2)}`;
                }

                if (identifier.length <= 8) {
                    return `${identifier.slice(0, 2)}.${identifier.slice(2, 5)}.${identifier.slice(5)}`;
                }

                if (identifier.length <= 12) {
                    return `${identifier.slice(0, 2)}.${identifier.slice(2, 5)}.${identifier.slice(5, 8)}/${identifier.slice(8)}`;
                }

                return `${identifier.slice(0, 2)}.${identifier.slice(2, 5)}.${identifier.slice(5, 8)}/${identifier.slice(8, 12)}-${identifier.slice(12)}`;
            },
        },
        cep: {
            inputMode: 'numeric',
            maxDigits: 8,
            format(value) {
                const digits = onlyDigits(value).slice(0, 8);

                if (digits.length <= 5) {
                    return digits;
                }

                return `${digits.slice(0, 5)}-${digits.slice(5)}`;
            },
        },
        phone: {
            inputMode: 'numeric',
            maxDigits: 11,
            format(value) {
                const digits = onlyDigits(value).slice(0, 11);

                if (digits.length <= 2) {
                    return digits;
                }

                const areaCode = digits.slice(0, 2);
                const number = digits.slice(2);

                if (digits.length <= 6) {
                    return `(${areaCode}) ${number}`;
                }

                if (digits.length <= 10) {
                    return `(${areaCode}) ${number.slice(0, 4)}-${number.slice(4)}`;
                }

                return `(${areaCode}) ${number.slice(0, 5)}-${number.slice(5)}`;
            },
        },
    };

    const boundInputs = new WeakSet();

    function onlyDigits(value) {
        return String(value ?? '').replace(/\D+/g, '');
    }

    function characterCount(value, pattern) {
        const character = pattern.character || /\d/;
        return [...value].filter((valueCharacter) => character.test(valueCharacter)).length;
    }

    function caretPositionFromCharacters(value, charactersBeforeCaret, pattern) {
        if (charactersBeforeCaret <= 0) {
            return 0;
        }

        let charactersSeen = 0;
        const character = pattern.character || /\d/;

        for (let index = 0; index < value.length; index += 1) {
            if (character.test(value[index])) {
                charactersSeen += 1;

                if (charactersSeen >= charactersBeforeCaret) {
                    return index + 1;
                }
            }
        }

        return value.length;
    }

    function applyMask(input) {
        const maskName = String(input.dataset.mask || '').trim();
        const pattern = patterns[maskName];

        if (!pattern) {
            return;
        }

        const currentValue = input.value;
        const caretStart = typeof input.selectionStart === 'number' ? input.selectionStart : currentValue.length;
        const charactersBeforeCaret = characterCount(currentValue.slice(0, caretStart), pattern);
        const formattedValue = pattern.format(currentValue);

        if (formattedValue !== currentValue) {
            input.value = formattedValue;
        }

        if (typeof input.setSelectionRange === 'function' && document.activeElement === input) {
            const nextCaret = caretPositionFromCharacters(formattedValue, charactersBeforeCaret, pattern);
            input.setSelectionRange(nextCaret, nextCaret);
        }
    }

    function bindInput(input) {
        if (boundInputs.has(input)) {
            return;
        }

        const maskName = String(input.dataset.mask || '').trim();
        const pattern = patterns[maskName];

        if (!pattern) {
            return;
        }

        boundInputs.add(input);

        if (!input.hasAttribute('inputmode') && pattern.inputMode) {
            input.setAttribute('inputmode', pattern.inputMode);
        }

        applyMask(input);

        input.addEventListener('input', () => {
            applyMask(input);
        });

        input.addEventListener('blur', () => {
            applyMask(input);
        });
    }

    function refresh(root = document) {
        root.querySelectorAll('[data-mask]').forEach((input) => {
            if (input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement) {
                bindInput(input);
            }
        });
    }

    window.LegacyInputMask = {
        refresh,
        bindInput,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            refresh();
        });
    } else {
        refresh();
    }
})();
