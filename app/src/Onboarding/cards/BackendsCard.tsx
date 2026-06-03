import { useState } from "react";
import { Boxes, Check, Lock, Loader2 } from "lucide-react";
import type { Boot, OnboardingStatus } from "../types";

interface Props {
  status: OnboardingStatus;
  boot: Boot;
  onUpdateStatus?: () => Promise<void>;
}

/**
 * Backends card — design principles #2 + #5.
 *
 * Admin view (status.isAdmin === true): every kind rendered as a row
 * with a checkbox. Untick = add to site-disabled list; tick = remove.
 * Optimistic UI with rollback on error.
 *
 * Non-admin view: every kind rendered with a status badge:
 *   "Available" (allowed by site + by current user's role),
 *   "Disabled (admin)" (site policy blocks it),
 *   "Role restricted" (the WP user role lacks the required cap).
 * No checkboxes; reading-only summary of what's accessible.
 */
export function BackendsCard({ status, boot, onUpdateStatus }: Props) {
  const labels = labelsFor(boot.locale);
  const [pendingKind, setPendingKind] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [optimisticDisabled, setOptimisticDisabled] = useState<Set<string>>(
    new Set(status.disabled_kinds ?? [])
  );

  async function toggleDisabled(kind: string, currentlyDisabled: boolean) {
    if (!status.isAdmin || pendingKind) return;
    const next = new Set(optimisticDisabled);
    if (currentlyDisabled) {
      next.delete(kind);
    } else {
      next.add(kind);
    }
    setOptimisticDisabled(next);
    setPendingKind(kind);
    setError(null);
    try {
      const res = await fetch(`${boot.restUrl}onboarding/disabled-kinds`, {
        method: "POST",
        headers: { "X-WP-Nonce": boot.nonce, "Content-Type": "application/json" },
        credentials: "same-origin",
        body: JSON.stringify({ disabled: Array.from(next) }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data?.error ?? `HTTP ${res.status}`);
      await onUpdateStatus?.();
    } catch (e) {
      setOptimisticDisabled(new Set(status.disabled_kinds ?? []));
      setError(e instanceof Error ? e.message : "Save failed");
    } finally {
      setPendingKind(null);
    }
  }

  return (
    <div className="space-y-4">
      <div className="space-y-2 text-center">
        <div className="mx-auto inline-flex size-12 items-center justify-center rounded-full bg-secondary/40">
          <Boxes className="size-6 text-muted-foreground" />
        </div>
        <h2 className="text-2xl font-semibold tracking-tight">{labels.title}</h2>
        <p className="mx-auto max-w-md text-balance text-sm leading-relaxed text-muted-foreground">
          {status.isAdmin ? labels.subtitleAdmin : labels.subtitleUser}
        </p>
      </div>

      <ul className="mx-auto max-w-md space-y-1.5">
        {status.backends.map((b) => {
          const disabled = optimisticDisabled.has(b.kind);
          const isPending = pendingKind === b.kind;
          const canEdit = b.userCanEdit;
          const isCustom = b.source === "site";
          return (
            <li
              key={b.kind}
              className={
                "rounded-lg border border-border/40 px-3.5 py-2.5 " +
                (disabled ? "opacity-60" : "")
              }
            >
              <div className="flex items-center justify-between gap-3">
                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-2 text-sm font-medium">
                    <code className="font-mono">{b.kind}</code>
                    {isCustom && (
                      <span className="rounded-sm bg-amber-950/40 px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-amber-300">
                        {labels.custom}
                      </span>
                    )}
                  </div>
                  {b.fields.length > 0 && (
                    <div className="mt-0.5 truncate text-xs text-muted-foreground">
                      {labels.fields}: {b.fields.join(", ")}
                    </div>
                  )}
                </div>

                {status.isAdmin ? (
                  <button
                    type="button"
                    onClick={() => toggleDisabled(b.kind, disabled)}
                    disabled={isPending}
                    aria-label={disabled ? labels.enable : labels.disable}
                    className={
                      "flex size-7 shrink-0 items-center justify-center rounded-md border transition-colors " +
                      (disabled
                        ? "border-border/40 hover:border-foreground/40"
                        : "border-foreground/40 bg-foreground/10 hover:border-foreground/60")
                    }
                  >
                    {isPending ? (
                      <Loader2 className="size-3.5 animate-spin text-muted-foreground" />
                    ) : disabled ? (
                      <span className="size-3.5" />
                    ) : (
                      <Check className="size-3.5 text-foreground" />
                    )}
                  </button>
                ) : (
                  <StatusBadge
                    disabled={disabled}
                    canEdit={canEdit}
                    labels={labels}
                  />
                )}
              </div>
            </li>
          );
        })}
      </ul>

      {error && <p className="text-center text-xs text-destructive">{error}</p>}
    </div>
  );
}

function StatusBadge({
  disabled,
  canEdit,
  labels,
}: {
  disabled: boolean;
  canEdit: boolean;
  labels: ReturnType<typeof labelsFor>;
}) {
  if (disabled) {
    return (
      <span className="inline-flex shrink-0 items-center gap-1 rounded-md bg-muted/40 px-1.5 py-1 text-[10px] uppercase tracking-wide text-muted-foreground">
        <Lock className="size-3" />
        {labels.disabledAdmin}
      </span>
    );
  }
  if (!canEdit) {
    return (
      <span className="inline-flex shrink-0 items-center gap-1 rounded-md bg-amber-950/30 px-1.5 py-1 text-[10px] uppercase tracking-wide text-amber-300">
        {labels.roleRestricted}
      </span>
    );
  }
  return (
    <span className="inline-flex shrink-0 items-center gap-1 rounded-md bg-emerald-950/30 px-1.5 py-1 text-[10px] uppercase tracking-wide text-emerald-300">
      <Check className="size-3" />
      {labels.available}
    </span>
  );
}

function labelsFor(locale?: string) {
  switch (locale) {
    case "lt":
      return {
        title: "Ką pokalbis gali redaguoti",
        subtitleAdmin:
          "Atžymėkite turinio tipus, kurių WPChat neturėtų liesti šioje svetainėje. Nustatymai galioja visiems vartotojams.",
        subtitleUser:
          "Šie turinio tipai pasiekiami pokalbiu. Administratorius valdo sąrašą; jūsų WordPress rolė nulemia, kas rodoma kaip „Pasiekiama\".",
        custom: "Šios svetainės",
        fields: "Laukai",
        available: "Pasiekiama",
        disabledAdmin: "Atjungta (admin)",
        roleRestricted: "Trūksta teisių",
        enable: "Įjungti",
        disable: "Išjungti",
      };
    case "ru":
      return {
        title: "Что чат может редактировать",
        subtitleAdmin:
          "Снимите галочки с типов контента, к которым WPChat не должен прикасаться на этом сайте. Настройки действуют для всех.",
        subtitleUser:
          "Эти типы контента доступны через чат. Список контролирует администратор; ваша роль WordPress определяет, что показано как «Доступно».",
        custom: "Этого сайта",
        fields: "Поля",
        available: "Доступно",
        disabledAdmin: "Откл. (админ)",
        roleRestricted: "Нет прав",
        enable: "Включить",
        disable: "Отключить",
      };
    case "pl":
      return {
        title: "Co czat może edytować",
        subtitleAdmin:
          "Odznacz typy treści, których WPChat nie powinien dotykać. Ustawienia obowiązują wszystkich.",
        subtitleUser:
          "Te typy treści są dostępne przez czat. Listę kontroluje administrator; Twoja rola WordPress decyduje, co pokazuje się jako „Dostępne\".",
        custom: "Tej witryny",
        fields: "Pola",
        available: "Dostępne",
        disabledAdmin: "Wył. (admin)",
        roleRestricted: "Brak uprawnień",
        enable: "Włącz",
        disable: "Wyłącz",
      };
    default:
      return {
        title: "What the chat can edit",
        subtitleAdmin:
          "Untick any content type WPChat shouldn't touch on this site. Settings apply to everyone.",
        subtitleUser:
          "These content types are reachable via chat. The site admin controls this list; your WordPress role determines what shows as Available.",
        custom: "Site",
        fields: "Fields",
        available: "Available",
        disabledAdmin: "Disabled (admin)",
        roleRestricted: "Role restricted",
        enable: "Enable",
        disable: "Disable",
      };
  }
}
