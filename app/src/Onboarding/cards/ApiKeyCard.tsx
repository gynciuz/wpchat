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
 * Card — one API key field. No provider picker: paste any supported key and
 * ChatAdmin detects whether it's Anthropic, OpenAI, or Google Gemini from the
 * prefix, then validates + saves it. If the key is set via a wp-config
 * constant, show the locked state.
 */
export function ApiKeyCard({ status, boot, onUpdateStatus, onAdvance }: Props) {
  const labels = labelsFor(boot.locale);
  const detectedLabel =
    status.llmProvider?.options.find((o) => o.id === status.apiKey.provider)?.label ?? null;

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
        body: JSON.stringify({ key: value.trim() }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data?.error ?? `HTTP ${res.status}`);
      setSaved(true);
      await onUpdateStatus();
      // Brief beat so the "connected to X" confirmation is visible, then advance.
      setTimeout(onAdvance, 900);
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
        <p className="mx-auto max-w-md text-balance text-sm leading-relaxed text-muted-foreground">
          {labels.subtitle}
        </p>
      </div>

      <form onSubmit={save} className="mx-auto flex max-w-md flex-col gap-2">
        <input
          type="password"
          autoFocus
          value={value}
          onChange={(e) => setValue(e.target.value)}
          placeholder="sk-ant-…  ·  sk-…  ·  AIza…"
          disabled={busy || saved}
          className="h-11 w-full rounded-lg border-0 bg-secondary/30 px-3 text-base text-foreground outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-foreground/30"
        />
        <Button type="submit" disabled={!value.trim() || busy || saved} className="h-11">
          {saved ? (
            <>
              <Check className="size-4" />
              {detectedLabel ? `${labels.connected} ${detectedLabel}` : labels.saved}
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

      <p className="text-center text-xs text-muted-foreground">
        {labels.getKey}{" "}
        {KEY_LINKS.map((l, i) => (
          <span key={l.label}>
            {i > 0 && " · "}
            <a
              href={l.url}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-0.5 underline underline-offset-4 decoration-foreground/30 hover:decoration-foreground"
            >
              {l.label}
              <ExternalLink className="size-3" />
            </a>
          </span>
        ))}
      </p>
    </div>
  );
}

const KEY_LINKS = [
  { label: "Anthropic", url: "https://console.anthropic.com/settings/keys" },
  { label: "OpenAI", url: "https://platform.openai.com/api-keys" },
  { label: "Google", url: "https://aistudio.google.com/app/apikey" },
];

function labelsFor(locale?: string) {
  switch (locale) {
    case "lt":
      return {
        title: "Prijunkite DI",
        subtitle: "Įklijuokite API raktą — ChatAdmin atpažins, ar tai Anthropic, OpenAI, ar Google Gemini.",
        save: "Išsaugoti",
        saving: "Saugoma…",
        saved: "Išsaugota",
        connected: "Prijungta:",
        getKey: "Gauti raktą:",
        constantLocked: "Raktas jau nustatytas per wp-config.php konstantą.",
      };
    case "ru":
      return {
        title: "Подключите ИИ",
        subtitle: "Вставьте API-ключ — ChatAdmin определит, Anthropic это, OpenAI или Google Gemini.",
        save: "Сохранить",
        saving: "Сохраняем…",
        saved: "Сохранено",
        connected: "Подключено:",
        getKey: "Получить ключ:",
        constantLocked: "Ключ задан через константу wp-config.php.",
      };
    case "pl":
      return {
        title: "Podłącz AI",
        subtitle: "Wklej klucz API — ChatAdmin rozpozna, czy to Anthropic, OpenAI czy Google Gemini.",
        save: "Zapisz",
        saving: "Zapisuję…",
        saved: "Zapisano",
        connected: "Połączono:",
        getKey: "Pobierz klucz:",
        constantLocked: "Klucz jest ustawiony w stałej wp-config.php.",
      };
    default:
      return {
        title: "Connect your AI",
        subtitle: "Paste your API key — ChatAdmin detects whether it's Anthropic, OpenAI, or Google Gemini.",
        save: "Save",
        saving: "Saving…",
        saved: "Saved",
        connected: "Connected:",
        getKey: "Get a key:",
        constantLocked: "Key is set via wp-config.php constant.",
      };
  }
}
