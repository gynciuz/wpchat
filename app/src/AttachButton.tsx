import { useRef } from "react";
import { Paperclip } from "lucide-react";
import { Button } from "@/components/ui/button";

interface AttachButtonProps {
  onPick: (file: File) => void;
  disabled?: boolean;
}

/**
 * Paperclip button that opens the native file picker. The actual upload
 * happens in Chat.tsx — this component just gets the File out.
 *
 * Only allows image/jpeg, image/png, image/webp. The backend enforces
 * the same list with a 415; this client-side filter is just to avoid
 * showing irrelevant files in the picker on mobile.
 */
export function AttachButton({ onPick, disabled }: AttachButtonProps) {
  const inputRef = useRef<HTMLInputElement>(null);

  return (
    <>
      <input
        ref={inputRef}
        type="file"
        accept="image/jpeg,image/png,image/webp"
        className="hidden"
        onChange={(e) => {
          const file = e.target.files?.[0];
          if (file) onPick(file);
          // Reset so picking the same file twice still fires onChange.
          e.target.value = "";
        }}
      />
      <Button
        type="button"
        variant="secondary"
        size="icon"
        onClick={() => inputRef.current?.click()}
        disabled={disabled}
        aria-label="Attach image"
        className="size-10 shrink-0"
      >
        <Paperclip className="size-4" />
      </Button>
    </>
  );
}
