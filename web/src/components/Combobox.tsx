import { useId, useMemo, useRef, useState } from 'react';

export interface ComboboxOption {
  value: string;
  label: string;
}

interface ComboboxProps {
  id?: string;
  name?: string;
  value: string;
  onChange: (value: string) => void;
  options: ComboboxOption[];
  placeholder?: string;
  ariaLabel?: string;
  disabled?: boolean;
  required?: boolean;
}

export function Combobox({
  id,
  name,
  value,
  onChange,
  options,
  placeholder,
  ariaLabel,
  disabled,
  required,
}: ComboboxProps) {
  const generatedId = useId();
  const inputId = id ?? `combobox-${generatedId}`;
  const listboxId = `${inputId}-listbox`;
  const [open, setOpen] = useState(false);
  const [activeIndex, setActiveIndex] = useState(-1);
  const rootRef = useRef<HTMLDivElement | null>(null);
  const inputRef = useRef<HTMLInputElement | null>(null);

  const filteredOptions = useMemo(() => {
    if (!value) {
      return options.slice(0, 50);
    }
    const lower = value.toLowerCase();
    return options.filter((option) => option.label.toLowerCase().includes(lower)).slice(0, 50);
  }, [options, value]);

  const activeOption = activeIndex >= 0 ? filteredOptions[activeIndex] : null;
  const activeOptionId = activeOption ? `${inputId}-option-${activeIndex}` : undefined;

  function openWithStartingIndex() {
    setOpen(true);
    if (filteredOptions.length === 0) {
      setActiveIndex(-1);
      return;
    }
    const selectedIndex = filteredOptions.findIndex((option) => option.value === value);
    setActiveIndex(selectedIndex >= 0 ? selectedIndex : 0);
  }

  function selectOption(option: ComboboxOption) {
    onChange(option.value);
    setOpen(false);
    setActiveIndex(-1);
  }

  return (
    <div ref={rootRef} className="relative">
      <input
        id={inputId}
        name={name}
        ref={inputRef}
        className="field-input"
        type="text"
        value={value}
        placeholder={placeholder}
        aria-label={ariaLabel}
        role="combobox"
        aria-autocomplete="list"
        aria-expanded={open}
        aria-controls={listboxId}
        aria-activedescendant={activeOptionId}
        autoComplete="off"
        required={required}
        disabled={disabled}
        onChange={(event) => {
          onChange(event.target.value);
          openWithStartingIndex();
        }}
        onFocus={() => {
          if (!disabled) {
            openWithStartingIndex();
          }
        }}
        onClick={() => {
          if (!disabled) {
            openWithStartingIndex();
          }
        }}
        onBlur={(event) => {
          const relatedTarget = event.relatedTarget as Node | null;
          if (relatedTarget && rootRef.current?.contains(relatedTarget)) {
            return;
          }
          setOpen(false);
          setActiveIndex(-1);
        }}
        onKeyDown={(event) => {
          if (event.key === 'Escape') {
            setOpen(false);
            setActiveIndex(-1);
            return;
          }

          if (event.key === 'Tab') {
            setOpen(false);
            return;
          }

          if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (!open) {
              openWithStartingIndex();
              return;
            }
            setActiveIndex((current) => {
              if (filteredOptions.length === 0) {
                return -1;
              }
              if (current < 0) {
                return 0;
              }
              return (current + 1) % filteredOptions.length;
            });
            return;
          }

          if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (!open) {
              openWithStartingIndex();
              return;
            }
            setActiveIndex((current) => {
              if (filteredOptions.length === 0) {
                return -1;
              }
              if (current < 0) {
                return filteredOptions.length - 1;
              }
              return (current - 1 + filteredOptions.length) % filteredOptions.length;
            });
            return;
          }

          if (event.key === 'Enter' && open && activeIndex >= 0) {
            event.preventDefault();
            selectOption(filteredOptions[activeIndex]);
          }
        }}
      />
      {open && (
        <div
          id={listboxId}
          role="listbox"
          className="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-card border border-border bg-background text-sm shadow-md"
        >
          {filteredOptions.length === 0 ? (
            <div className="px-3 py-3 text-sm text-muted-foreground" role="status">
              No matches found
            </div>
          ) : (
            filteredOptions.map((option, index) => (
              <button
                id={`${inputId}-option-${index}`}
                key={option.value}
                type="button"
                role="option"
                aria-selected={option.value === value}
                className={`flex min-h-12 w-full items-center px-3 py-3 text-left ${
                  index === activeIndex ? 'bg-surface' : 'hover:bg-surface'
                }`}
                tabIndex={-1}
                onMouseDown={(event) => {
                  event.preventDefault();
                  selectOption(option);
                }}
              >
                {option.label}
              </button>
            ))
          )}
        </div>
      )}
    </div>
  );
}

