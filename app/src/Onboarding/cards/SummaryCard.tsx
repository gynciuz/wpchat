import { Check, X } from "lucide-react";
import type { Boot, OnboardingStatus } from "../types";

interface Props {
  status: OnboardingStatus;
  boot: Boot;
  skipped: Set<string>;
}

/**
 * The last card — the capability mirror. List what works, list what's
 * still optional. End on the user's next concrete action (the chat
 * itself is one tap away via the footer button). Design principle #6
 * — give them their first small result, then one clear next step.
 */
export function SummaryCard({ status, boot }: Props) {
  const labels = labelsFor(boot.locale);
  const rows = [
    { label: labels.aiKey, ok: status.apiKey.ok },
    { label: labels.permissions, ok: status.permissions.ok },
    { label: labels.wc, ok: status.wc.active },
    { label: labels.analytics, ok: status.analytics.detected.length > 0, optional: true },
    {
      label: labels.cf,
      ok: status.integrations.cf_purge.configured,
      optional: true,
    },
    {
      label: labels.git,
      ok: status.integrations.git_sync.configured,
      optional: true,
    },
  ];

  return (
    <div className="space-y-5">
      <div className="space-y-2 text-center">
        <h2 className="text-2xl font-semibold tracking-tight">{labels.title}</h2>
        <p className="text-sm text-muted-foreground">{labels.subtitle}</p>
      </div>
      <ul className="mx-auto max-w-md space-y-1.5 text-sm">
        {rows.map((r) => (
          <li
            key={r.label}
            className="flex items-center justify-between rounded-md px-3 py-2 even:bg-secondary/15"
          >
            <span className="flex items-center gap-2">
              {r.ok ? (
                <Check className="size-4 text-emerald-400" />
              ) : (
                <X className="size-4 text-muted-foreground/60" />
              )}
              <span className={r.ok ? "text-foreground" : "text-muted-foreground"}>{r.label}</span>
            </span>
            {!r.ok && r.optional && (
              <span className="text-[10px] uppercase tracking-wider text-muted-foreground">
                {labels.optional}
              </span>
            )}
          </li>
        ))}
      </ul>
    </div>
  );
}

function labelsFor(locale?: string) {
  switch (locale) {
    case "lt":
      return {
        title: "Pasiruošę pradėti",
        subtitle: "Štai kas veikia. Neprivalomi dalykai galima įjungti vėliau iš Nustatymų.",
        aiKey: "Anthropic API raktas",
        permissions: "Reikalingos teisės",
        wc: "WooCommerce aktyvuotas",
        analytics: "Analitikos teikėjas",
        cf: "Cloudflare automatinis cache",
        git: "Git auto-commit",
        optional: "Neprivaloma",
      };
    case "ru":
      return {
        title: "Готово к работе",
        subtitle: "Вот что работает. Опциональные пункты можно подключить позже из Настроек.",
        aiKey: "API-ключ Anthropic",
        permissions: "Нужные права",
        wc: "WooCommerce активен",
        analytics: "Источник аналитики",
        cf: "Cloudflare auto-purge",
        git: "Git auto-commit",
        optional: "Опционально",
      };
    case "pl":
      return {
        title: "Gotowe",
        subtitle: "Oto co działa. Opcjonalne elementy można dodać później z Ustawień.",
        aiKey: "Klucz API Anthropic",
        permissions: "Uprawnienia",
        wc: "WooCommerce aktywny",
        analytics: "Źródło analityki",
        cf: "Cloudflare auto-purge",
        git: "Git auto-commit",
        optional: "Opcjonalne",
      };
    default:
      return {
        title: "Ready to go",
        subtitle: "Here's what's working. Optional items can be enabled later from Settings.",
        aiKey: "Anthropic API key",
        permissions: "Required capabilities",
        wc: "WooCommerce active",
        analytics: "Analytics provider",
        cf: "Cloudflare auto-purge",
        git: "Git auto-commit",
        optional: "Optional",
      };
  }
}
