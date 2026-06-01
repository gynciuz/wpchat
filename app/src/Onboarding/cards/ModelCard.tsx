import { useState } from "react";
import { Check, Loader2 } from "lucide-react";
import type { Boot, OnboardingStatus } from "../types";

interface Props {
  status: OnboardingStatus;
  boot: Boot;
  onUpdateStatus: () => Promise<void>;
}

/**
 * Card — model picker. Three options, current one highlighted. Tap
 * to save inline. Design principle #2 — one sharp thing.
 */
export function ModelCard({ status, boot, onUpdateStatus }: Props) {
  const labels = labelsFor(boot.locale);
  const [busy, setBusy] = useState<string | null>(null);
  const [err, setErr] = useState<string | null>(null);

  async function pick(model: string) {
    if (busy || model === status.model.current) return;
    setBusy(model);
    setErr(null);
    try {
      const res = await fetch(`${boot.restUrl}onboarding/model`, {
        method: "POST",
        headers: { "X-WP-Nonce": boot.nonce, "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ model }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data?.error ?? `HTTP ${res.status}`);
      await onUpdateStatus();
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Save failed");
    } finally {
      setBusy(null);
    }
  }

  return (
    <div className="space-y-5">
      <div className="space-y-2 text-center">
        <h2 className="text-2xl font-semibold tracking-tight">{labels.title}</h2>
        <p className="text-sm text-muted-foreground">{labels.subtitle}</p>
      </div>

      <div className="mx-auto flex max-w-md flex-col gap-2">
        {status.model.options.map((m) => {
          const selected = status.model.current === m.id;
          const isBusy = busy === m.id;
          return (
            <button
              key={m.id}
              type="button"
              onClick={() => pick(m.id)}
              disabled={busy !== null}
              className={
                "flex w-full items-center justify-between rounded-lg border px-3.5 py-3 text-left text-sm transition-colors " +
                (selected
                  ? "border-foreground/40 bg-foreground/5"
                  : "border-border/40 hover:bg-secondary/40")
              }
            >
              <span className={selected ? "font-medium text-foreground" : "text-foreground"}>
                {m.label}
              </span>
              {isBusy ? (
                <Loader2 className="size-4 animate-spin text-muted-foreground" />
              ) : selected ? (
                <Check className="size-4 text-emerald-400" />
              ) : (
                <span className="size-4" />
              )}
            </button>
          );
        })}
        {err && <p className="text-center text-xs text-destructive">{err}</p>}
      </div>
    </div>
  );
}

function labelsFor(locale?: string) {
  switch (locale) {
    case "lt":
      return { title: "Pasirinkite modelį", subtitle: "Sonnet — geriausias balansas. Galite pakeisti vėliau." };
    case "ru":
      return { title: "Выберите модель", subtitle: "Sonnet — оптимальный баланс. Можно изменить позже." };
    case "pl":
      return { title: "Wybierz model", subtitle: "Sonnet — najlepszy balans. Można zmienić później." };
    default:
      return { title: "Pick a model", subtitle: "Sonnet is the best balance. You can change this later." };
  }
}
