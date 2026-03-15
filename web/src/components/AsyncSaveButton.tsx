import { Check, Loader2, Save, XCircle } from 'lucide-react';
import { type ButtonHTMLAttributes, useEffect, useRef, useState } from 'react';

type AsyncSaveButtonType = ButtonHTMLAttributes<HTMLButtonElement>['type'];

interface AsyncSaveButtonProps
  extends Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'children' | 'type' | 'disabled'> {
  label: string;
  isSubmitting: boolean;
  isSuccess: boolean;
  isError?: boolean;
  disabled?: boolean;
  type?: AsyncSaveButtonType;
}

const SUCCESS_FLASH_MS = 700;
const ERROR_FLASH_MS = 900;

export function AsyncSaveButton({
  label,
  isSubmitting,
  isSuccess,
  isError = false,
  disabled = false,
  type = 'button',
  className,
  ...buttonProps
}: AsyncSaveButtonProps) {
  const [showSuccessFlash, setShowSuccessFlash] = useState(false);
  const [showErrorFlash, setShowErrorFlash] = useState(false);
  const successStartTimerRef = useRef<number | null>(null);
  const successTimerRef = useRef<number | null>(null);
  const errorStartTimerRef = useRef<number | null>(null);
  const errorTimerRef = useRef<number | null>(null);
  const previousSuccessRef = useRef(isSuccess);
  const previousErrorRef = useRef(isError);

  useEffect(() => {
    const wasSuccess = previousSuccessRef.current;
    previousSuccessRef.current = isSuccess;

    if (isSubmitting || !isSuccess || wasSuccess) {
      return;
    }

    if (successStartTimerRef.current !== null) {
      window.clearTimeout(successStartTimerRef.current);
    }

    if (successTimerRef.current !== null) {
      window.clearTimeout(successTimerRef.current);
    }

    successStartTimerRef.current = window.setTimeout(() => {
      setShowSuccessFlash(true);
      successStartTimerRef.current = null;
    }, 0);

    successTimerRef.current = window.setTimeout(() => {
      setShowSuccessFlash(false);
      successTimerRef.current = null;
    }, SUCCESS_FLASH_MS);
  }, [isSubmitting, isSuccess]);

  useEffect(() => {
    const wasError = previousErrorRef.current;
    previousErrorRef.current = isError;

    if (isSubmitting || !isError || wasError) {
      return;
    }

    if (errorStartTimerRef.current !== null) {
      window.clearTimeout(errorStartTimerRef.current);
    }

    if (errorTimerRef.current !== null) {
      window.clearTimeout(errorTimerRef.current);
    }

    errorStartTimerRef.current = window.setTimeout(() => {
      setShowSuccessFlash(false);
      setShowErrorFlash(true);
      errorStartTimerRef.current = null;
    }, 0);

    errorTimerRef.current = window.setTimeout(() => {
      setShowErrorFlash(false);
      errorTimerRef.current = null;
    }, ERROR_FLASH_MS);
  }, [isSubmitting, isError]);

  useEffect(() => {
    return () => {
      if (successStartTimerRef.current !== null) {
        window.clearTimeout(successStartTimerRef.current);
      }
      if (successTimerRef.current !== null) {
        window.clearTimeout(successTimerRef.current);
      }
      if (errorStartTimerRef.current !== null) {
        window.clearTimeout(errorStartTimerRef.current);
      }
      if (errorTimerRef.current !== null) {
        window.clearTimeout(errorTimerRef.current);
      }
    };
  }, []);

  const isDisabled = disabled || isSubmitting;
  const submittingClass = isSubmitting ? 'btn-primary-async--submitting' : '';
  const rootClassName = className === undefined
    ? `btn-primary btn-primary-async ${submittingClass}`.trim()
    : `btn-primary btn-primary-async ${submittingClass} ${className}`.trim();

  return (
    <button
      aria-busy={isSubmitting}
      className={rootClassName}
      disabled={isDisabled}
      type={type}
      {...buttonProps}
    >
      <span aria-hidden="true" className="btn-primary-async-icon">
        {isSubmitting && <Loader2 className="btn-primary-async-spinner" size={14} />}
        {!isSubmitting && showSuccessFlash && !showErrorFlash && (
          <Check className="btn-primary-async-success" size={14} />
        )}
        {!isSubmitting && showErrorFlash && <XCircle className="btn-primary-async-error" size={14} />}
        {!isSubmitting && !showSuccessFlash && !showErrorFlash && <Save size={14} />}
      </span>
      <span>{label}</span>
    </button>
  );
}
