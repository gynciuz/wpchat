import { useState } from "react";
import { Check, Cloud, Key, Loader2, Mail } from "lucide-react";
import { Button } from "@/components/ui/button";
import type { Boot, OnboardingStatus } from "../types";

interface Props {
  status: OnboardingStatus;
  boot: Boot;
  onUpdateStatus: () => Promise<void>;
  onAdvance: () => void;
}

/**
 * Provider step — the choice that frames the rest of onboarding.
 * Design principle #6: reflect what the user is choosing FOR
 * themselves (pay Anthropic directly vs let WPChat handle it).
 *
 * Two big tappable choices. Cloud is currently a waitlist —
 * picking it captures an optional email so we can ping when the
 * subscription tier actually opens. The Wizard reads the persisted
 * choice and either continues to the BYO API-key + model flow, or
 * skips both for the Cloud-waitlist path.
 */
export function ProviderCard({ status, boot, onUpdateStatus, onAdvance }: Props) {
  const labels = labelsFor(boot.locale);
  const [picking, setPicking] = useState<"byo" | "cloud-waitlist" | null>(null);
  const [email, setEmail] = useState(boot.userName ? "" : "");
  const [emailMode, setEmailMode] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const current = status.provider.current;

  async function pick(provider: "byo" | "cloud-waitlist", optEmail?: string) {
    if (picking) return;
    setPicking(provider);
    setError(null);
    try {
      const body: Record<string, string> = { provider };
      if (optEmail) body.email = optEmail;
      const res = await fetch(`${boot.restUrl}onboarding/provider`, {
        method: "POST",
        headers: { "X-WP-Nonce": boot.nonce, "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify(body),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data?.error ?? `HTTP ${res.status}`);
      await onUpdateStatus();
      // Small celebration window so the user sees the new selection
      // before the wizard moves them on.
      setTimeout(onAdvance, 500);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Save failed");
    } finally {
      setPicking(null);
    }
  }

  function submitWaitlist(e: React.FormEvent) {
    e.preventDefault();
    pick("cloud-waitlist", email.trim() || undefined);
  }

  return (
    <div className="space-y-5">
      <div className="space-y-2 text-center">
        <h2 className="text-2xl font-semibold tracking-tight">{labels.title}</h2>
        <p className="mx-auto max-w-md text-balance text-sm leading-relaxed text-muted-foreground">
          {labels.subtitle}
        </p>
      </div>

      <div className="mx-auto grid max-w-xl gap-3 sm:grid-cols-2">
        <button
          type="button"
          onClick={() => pick("byo")}
          disabled={picking !== null}
          className={
            "flex h-full flex-col items-start gap-2 rounded-xl border p-4 text-left transition-colors " +
            (current === "byo"
              ? "border-foreground/40 bg-foreground/5"
              : "border-border/40 hover:bg-secondary/40")
          }
        >
          <div className="flex w-full items-center justify-between">
            <Key className="size-5 text-foreground" />
            {picking === "byo" ? (
              <Loader2 className="size-4 animate-spin text-muted-foreground" />
            ) : current === "byo" ? (
              <Check className="size-4 text-emerald-400" />
            ) : null}
          </div>
          <div className="space-y-1">
            <div className="text-sm font-semibold">{labels.byoTitle}</div>
            <div className="text-xs leading-snug text-muted-foreground">{labels.byoBody}</div>
          </div>
          <div className="text-[10px] uppercase tracking-wide text-muted-foreground">
            {labels.byoPrice}
          </div>
        </button>

        <button
          type="button"
          onClick={() => setEmailMode(true)}
          disabled={picking !== null}
          className={
            "flex h-full flex-col items-start gap-2 rounded-xl border p-4 text-left transition-colors " +
            (current === "cloud-waitlist"
              ? "border-foreground/40 bg-foreground/5"
              : "border-border/40 hover:bg-secondary/40")
          }
        >
          <div className="flex w-full items-center justify-between">
            <Cloud className="size-5 text-foreground" />
            {picking === "cloud-waitlist" ? (
              <Loader2 className="size-4 animate-spin text-muted-foreground" />
            ) : current === "cloud-waitlist" ? (
              <Check className="size-4 text-emerald-400" />
            ) : (
              <span className="rounded-sm bg-amber-950/40 px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-amber-300">
                {labels.comingSoon}
              </span>
            )}
          </div>
          <div className="space-y-1">
            <div className="text-sm font-semibold">{labels.cloudTitle}</div>
            <div className="text-xs leading-snug text-muted-foreground">{labels.cloudBody}</div>
          </div>
          <div className="text-[10px] uppercase tracking-wide text-muted-foreground">
            {labels.cloudPrice}
          </div>
        </button>
      </div>

      {emailMode && (
        <form
          onSubmit={submitWaitlist}
          className="mx-auto flex max-w-md flex-col gap-2"
        >
          <label className="flex items-center gap-2 text-xs text-muted-foreground">
            <Mail className="size-3.5" />
            {labels.waitlistPrompt}
          </label>
          <div className="flex gap-2">
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              autoFocus
              placeholder="you@example.com"
              disabled={picking !== null}
              className="h-10 flex-1 rounded-lg border-0 bg-secondary/30 px-3 text-sm text-foreground outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-foreground/30"
            />
            <Button type="submit" disabled={picking !== null} size="sm" className="h-10">
              {picking === "cloud-waitlist" ? (
                <Loader2 className="size-4 animate-spin" />
              ) : (
                labels.join
              )}
            </Button>
          </div>
          <button
            type="button"
            onClick={() => pick("cloud-waitlist")}
            disabled={picking !== null}
            className="text-center text-[11px] text-muted-foreground underline underline-offset-4 decoration-muted-foreground/40 hover:decoration-foreground"
          >
            {labels.skipEmail}
          </button>
        </form>
      )}

      {error && <p className="text-center text-xs text-destructive">{error}</p>}
    </div>
  );
}

function labelsFor(locale?: string) {
  switch (locale) {
    case "lt":
      return {
        title: "Kaip mokėsite už pokalbį?",
        subtitle:
          "Pasirinkite, kaip WPChat susisieks su DI. Galite pakeisti vėliau iš Nustatymų.",
        byoTitle: "Savo Anthropic API raktas",
        byoBody:
          "Įklijuokite savo raktą iš console.anthropic.com — sąskaitas tvarkote tiesiogiai su Anthropic.",
        byoPrice: "Nemokama (pagal jūsų Anthropic sąskaitą)",
        cloudTitle: "WPChat Cloud",
        cloudBody:
          "Be API nustatymų — viskas iš karto. Mokėjimas per Stripe. Šiuo metu kuriama; prisijunkite prie laukimo sąrašo.",
        cloudPrice: "€10/mėn — €5 vertės žetonų",
        comingSoon: "Kuriama",
        waitlistPrompt: "Pranešime, kai atidarysime:",
        join: "Užsiregistruoti",
        skipEmail: "Praleisti el. paštą, tiesiog įsidėti sąrašą",
      };
    case "ru":
      return {
        title: "Как вы будете оплачивать чат?",
        subtitle:
          "Выберите, как WPChat подключается к ИИ. Можно изменить позже в Настройках.",
        byoTitle: "Свой ключ Anthropic API",
        byoBody:
          "Вставьте свой ключ с console.anthropic.com — счета напрямую от Anthropic.",
        byoPrice: "Бесплатно (по вашему счёту Anthropic)",
        cloudTitle: "WPChat Cloud",
        cloudBody:
          "Без настройки API. Оплата через Stripe. Сейчас в разработке; присоединяйтесь к листу ожидания.",
        cloudPrice: "€10/мес — токенов на €5",
        comingSoon: "Скоро",
        waitlistPrompt: "Напишем, когда откроется:",
        join: "Записаться",
        skipEmail: "Без email, просто в лист",
      };
    case "pl":
      return {
        title: "Jak chcesz płacić za czat?",
        subtitle:
          "Wybierz, jak WPChat łączy się z AI. Można zmienić później w Ustawieniach.",
        byoTitle: "Własny klucz Anthropic",
        byoBody:
          "Wklej swój klucz z console.anthropic.com — rozliczenie bezpośrednio z Anthropic.",
        byoPrice: "Bezpłatnie (rozliczenie Anthropic)",
        cloudTitle: "WPChat Cloud",
        cloudBody:
          "Bez konfiguracji API. Płatność przez Stripe. W przygotowaniu; dołącz do listy oczekujących.",
        cloudPrice: "€10/m-c — tokeny na €5",
        comingSoon: "Wkrótce",
        waitlistPrompt: "Powiadomimy, gdy będzie gotowe:",
        join: "Dołącz",
        skipEmail: "Bez emaila, dopisz mnie do listy",
      };
    default:
      return {
        title: "How will you pay for the chat?",
        subtitle:
          "Pick how WPChat reaches the AI. You can switch later from Settings.",
        byoTitle: "Bring your own Anthropic key",
        byoBody:
          "Paste a key from console.anthropic.com — billing goes directly through your Anthropic account.",
        byoPrice: "Free (charged on your Anthropic bill)",
        cloudTitle: "WPChat Cloud",
        cloudBody:
          "No API setup. Stripe billing. Building it now — join the waitlist and we'll email when it opens.",
        cloudPrice: "€10/mo — €5 of tokens",
        comingSoon: "Coming soon",
        waitlistPrompt: "We'll email when it opens:",
        join: "Join waitlist",
        skipEmail: "Skip email, just add me to the list",
      };
  }
}
