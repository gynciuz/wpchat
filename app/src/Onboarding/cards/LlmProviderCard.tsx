import { useState } from "react";
import { Check, Loader2 } from "lucide-react";
import type { Boot, OnboardingStatus } from "../types";

interface Props {
  status: OnboardingStatus;
  boot: Boot;
  onUpdateStatus: () => Promise<void>;
}

/**
 * Card — which LLM provider runs the chat (Anthropic / OpenAI / Gemini).
 * Distinct from the billing ProviderCard. Picking one updates the active
 * provider so the next (API-key + model) steps ask for the right key.
 */
export function LlmProviderCard({ status, boot, onUpdateStatus }: Props) {
  const labels = labelsFor(boot.locale);
  const [busy, setBusy] = useState<string | null>(null);
  const [err, setErr] = useState<string | null>(null);
  const locked = status.llmProvider?.locked;

  async function pick(provider: string) {
    if (busy || locked || provider === status.llmProvider.current) return;
    setBusy(provider);
    setErr(null);
    try {
      const res = await fetch(`${boot.restUrl}onboarding/llm-provider`, {
        method: "POST",
        headers: { "X-WP-Nonce": boot.nonce, "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ provider }),
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
        <p className="mx-auto max-w-md text-balance text-sm text-muted-foreground">{labels.subtitle}</p>
      </div>

      <div className="mx-auto flex max-w-md flex-col gap-2">
        {(status.llmProvider?.options ?? []).map((p) => {
          const selected = status.llmProvider.current === p.id;
          const isBusy = busy === p.id;
          return (
            <button
              key={p.id}
              type="button"
              onClick={() => pick(p.id)}
              disabled={busy !== null || locked}
              className={
                "flex w-full items-center justify-between rounded-lg border px-3.5 py-3 text-left text-sm transition-colors " +
                (selected ? "border-foreground/40 bg-foreground/5" : "border-border/40 hover:bg-secondary/40") +
                (locked ? " opacity-60" : "")
              }
            >
              <span className="font-medium">{p.label}</span>
              {isBusy ? (
                <Loader2 className="size-4 animate-spin text-muted-foreground" />
              ) : selected ? (
                <Check className="size-4 text-emerald-400" />
              ) : null}
            </button>
          );
        })}
      </div>

      {locked && <p className="text-center text-xs text-muted-foreground">{labels.locked}</p>}
      {err && <p className="text-center text-xs text-destructive">{err}</p>}
    </div>
  );
}

function labelsFor(locale?: string) {
  switch (locale) {
    case "lt":
      return {
        title: "Kurį DI naudosite?",
        subtitle: "Pasirinkite DI tiekėją. Toliau įklijuosite to tiekėjo API raktą.",
        locked: "Nustatyta per WPCHAT_LLM_PROVIDER wp-config.php.",
      };
    case "ru":
      return {
        title: "Какой ИИ использовать?",
        subtitle: "Выберите провайдера ИИ. Далее вставите его API-ключ.",
        locked: "Задано через WPCHAT_LLM_PROVIDER в wp-config.php.",
      };
    case "pl":
      return {
        title: "Którego AI użyć?",
        subtitle: "Wybierz dostawcę AI. Następnie wkleisz jego klucz API.",
        locked: "Ustawione przez WPCHAT_LLM_PROVIDER w wp-config.php.",
      };
    default:
      return {
        title: "Which AI should run the chat?",
        subtitle: "Pick your AI provider. Next you'll paste that provider's API key.",
        locked: "Set via WPCHAT_LLM_PROVIDER in wp-config.php.",
      };
  }
}
