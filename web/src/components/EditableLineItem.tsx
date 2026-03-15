import { Trash2 } from 'lucide-react';
import type { InputHTMLAttributes } from 'react';

interface EditableLineItemProps {
  descriptionInputProps: Omit<InputHTMLAttributes<HTMLInputElement>, 'type'>;
  amountInputProps: Omit<InputHTMLAttributes<HTMLInputElement>, 'type'>;
  onDelete: () => void;
  deleteDisabled?: boolean;
  deleteLabel?: string;
  errorMessage?: string;
}

export function EditableLineItem({
  descriptionInputProps,
  amountInputProps,
  onDelete,
  deleteDisabled = false,
  deleteLabel = 'Delete item',
  errorMessage,
}: EditableLineItemProps) {
  return (
    <div className="grid gap-1">
      <div className="flex items-center gap-2">
        <input
          {...descriptionInputProps}
          className={`field-input-compact min-w-0 flex-1 ${descriptionInputProps.className ?? ''}`.trim()}
          type="text"
        />

        <div className="relative w-28 shrink-0 sm:w-32">
          <span aria-hidden="true" className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">
            $
          </span>
          <input
            {...amountInputProps}
            className={`field-input-compact amount-numeric w-full pl-7 ${amountInputProps.className ?? ''}`.trim()}
            step="0.01"
            type="number"
          />
        </div>

        <button
          aria-label={deleteLabel}
          className="btn-destructive-outline min-w-12 justify-center px-2"
          disabled={deleteDisabled}
          onClick={onDelete}
          type="button"
        >
          <Trash2 size={14} />
        </button>
      </div>

      {errorMessage !== undefined && errorMessage.length > 0 && (
        <p className="text-xs text-destructive">{errorMessage}</p>
      )}
    </div>
  );
}
