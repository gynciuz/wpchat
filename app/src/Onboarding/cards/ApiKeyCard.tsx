import { useState } from "react";
import { Loader2, Check, ExternalLink } from "lucide-react";
import { Button } from "@/components/ui/button";
import type { Boot, OnboardingStatus } from "../types";

interface Props {
  status: OnboardingStatus;
  boot: Boot;
  onUpdateStatus: () => Promise<void>;
  onAdvance: () => void;
}

/**
 * Card — the active provider's API key. Interactive: paste, validate, save.
 * Provider-aware: the title, link, placeholder, and the `provider` sent to the
 * backend all come from the chosen LLM provider. If the key is set via a
 * wp-config constant, show the locked state.
 */
export function ApiKeyCard({ status, boot, onUpdateStatus, onAdvance }: Props) {
  const providerId = status.apiKey.provider || status.llmProvider?.current || "anthropic";
  const providerLabel =
    status.llmProvider?.options.find((o) => o.id === providerId)?.label ?? "AI";
  const keyHelp = status.apiKey.keyHelp ?? { url: "", placeholder: "", regex: "" };
  const labels = labelsFor(boot.locale, providerLabel);

  const [value, setValue] = useState("");
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  async function save(e: React.FormEvent) {
    e.preventDefault();
    if (!value.trim() || busy) return;
    setBusy(true);
    setErr(null);
    try {
      const res = await fetch(`${boot.restUrl}onboarding/api-key`, {
        method: "POST",
        headers: { "X-WP-Nonce": boot.nonce, "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ key: value.trim(), provider: providerId }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data?.error ?? `HTTP ${res.status}`);
      setSaved(true);
      await onUpdateStatus();
      setTimeout(onAdvance, 700);
    } catch (e) {
      setErr(e instanceof Error ? e.message : "Save failed");
    } finally {
      setBusy(false);
    }
  }

  if (status.apiKey.source === "constant") {
    return (
      <div className="space-y-4 text-center">
        <h2 className="text-2xl font-semibold tracking-tight">{labels.title}</h2>
        <p className="text-sm text-muted-foreground">{labels.constantLocked}</p>
        <div className="inline-flex items-center gap-2 rounded-lg bg-secondary/40 px-3 py-2 text-sm">
          <Check className="size-4 text-emerald-400" />
          <code className="font-mono">{status.apiKey.masked}</code>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-5">
      <div className="space-y-2 text-center">
        <h2 className="text-2xl font-semibold tracking-tight">{labels.title}</h2>
        <p className="text-sm leading-relaxed text-muted-foreground">{labels.subtitle}</p>
      </div>

      <form onSubmit={save} className="mx-auto flex max-w-md flex-col gap-2">
        <input
          type="password"
          autoFocus
          value={value}
          onChange={(e) => setValue(e.target.value)}
          placeholder={keyHelp.placeholder || "…"}
          disabled={busy || saved}
          className="h-11 w-full rounded-lg border-0 bg-secondary/30 px-3 text-base text-foreground outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-foreground/30"
        />
        <Button type="submit" disabled={!value.trim() || busy || saved} className="h-11">
          {saved ? (
            <>
              <Check className="size-4" />
              {labels.saved}
            </>
          ) : busy ? (
            <>
              <Loader2 className="size-4 animate-spin" />
              {labels.saving}
            </>
          ) : (
            labels.save
          )}
        </Button>
        {err && <p className="text-center text-xs text-destructive">{err}</p>}
      </form>

      {keyHelp.url && (
        <p className="text-center text-xs text-muted-foreground">
          <a
            href={keyHelp.url}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1 underline underline-offset-4 decoration-foreground/30 hover:decoration-foreground"
          >
            {labels.getKey} <ExternalLink className="size-3" />
          </a>
        </p>
      )}
    </div>
  );
}

function labelsFor(locale: string | undefined, provider: string) {
  switch (locale) {
    case "lt":
      return {
        title: `${provider} API raktas`,
        subtitle: `Įklijuokite ${provider} API raktą. WPChat naudoja jį visiems pokalbiams.`,
        save: "Išsaugoti",
        saving: "Saugoma…",
        saved: "Išsaugota",
        getKey: `Gauti ${provider} raktą`,
        constantLocked: "Raktas jau nustatytas per wp-config.php konstantą.",
      };
    case "ru":
      return {
        title: `API-ключ ${provider}`,
        subtitle: `Вставьте ключ ${provider}. WPChat использует его для всех сообщений.`,
        save: "Сохранить",
        saving: "Сохраняем…",
        saved: "Сохранено",
        getKey: `Получить ключ ${provider}`,
        constantLocked: "Ключ задан через константу wp-config.php.",
      };
    case "pl":
      return {
        title: `Klucz API ${provider}`,
        subtitle: `Wklej klucz ${provider}. WPChat używa go do każdej wiadomości.`,
        save: "Zapisz",
        saving: "Zapisuję…",
        saved: "Zapisano",
        getKey: `Pobierz klucz ${provider}`,
        constantLocked: "Klucz jest ustawiony w stałej wp-config.php.",
      };
    default:
      return {
        title: `${provider} API key`,
        subtitle: `Paste your ${provider} API key. WPChat uses it for every message.`,
        save: "Save",
        saving: "Saving…",
        saved: "Saved",
        getKey: `Get a ${provider} key`,
        constantLocked: "Key is set via wp-config.php constant.",
      };
  }
}
