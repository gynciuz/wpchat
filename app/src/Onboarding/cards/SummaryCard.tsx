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
  const cloud = status.provider?.current === "cloud-waitlist";
  const rows = [
    {
      // The provider label is informational; readiness = a working key, which
      // everyone needs today (Cloud isn't live yet — it's just a waitlist).
      label: cloud ? labels.providerCloud : labels.providerByo,
      ok: status.apiKey.ok,
    },
    { label: labels.aiKey, ok: status.apiKey.ok },
    { label: labels.permissions, ok: status.permissions.ok },
    { label: labels.wc, ok: status.wc.active },
    { label: labels.analytics, ok: status.analytics.detected.length > 0, optional: true },
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
      <p className="mx-auto max-w-md text-center text-[11px] leading-snug text-muted-foreground">
        {labels.privacy}{" "}
        <a
          href="https://github.com/gynciuz/wpchat/blob/main/PRIVACY.md"
          target="_blank"
          rel="noreferrer"
          className="underline underline-offset-2 hover:text-foreground"
        >
          {labels.privacyLink}
        </a>
      </p>
    </div>
  );
}

function labelsFor(locale?: string) {
  switch (locale) {
    case "lt":
      return {
        title: "Pasiruošę pradėti",
        subtitle: "Štai kas veikia. Neprivalomi dalykai galima įjungti vėliau iš Nustatymų.",
        providerByo: "Tiekėjas: Anthropic (jūsų raktas)",
        providerCloud: "Tiekėjas: WPChat Cloud (laukimo sąraše)",
        aiKey: "Anthropic API raktas",
        permissions: "Reikalingos teisės",
        wc: "WooCommerce aktyvuotas",
        analytics: "Analitikos teikėjas",
        optional: "Neprivaloma",
        privacy: "Jūsų užklausos (gali būti užsakymų/klientų duomenys) siunčiamos Anthropic atsakymui sugeneruoti.",
        privacyLink: "Privatumo informacija",
      };
    case "ru":
      return {
        title: "Готово к работе",
        subtitle: "Вот что работает. Опциональные пункты можно подключить позже из Настроек.",
        providerByo: "Провайдер: Anthropic (ваш ключ)",
        providerCloud: "Провайдер: WPChat Cloud (в листе ожидания)",
        aiKey: "API-ключ Anthropic",
        permissions: "Нужные права",
        wc: "WooCommerce активен",
        analytics: "Источник аналитики",
        optional: "Опционально",
        privacy: "Ваши запросы (возможны данные заказов/клиентов) отправляются в Anthropic для генерации ответа.",
        privacyLink: "О конфиденциальности",
      };
    case "pl":
      return {
        title: "Gotowe",
        subtitle: "Oto co działa. Opcjonalne elementy można dodać później z Ustawień.",
        providerByo: "Dostawca: Anthropic (Twój klucz)",
        providerCloud: "Dostawca: WPChat Cloud (lista oczekujących)",
        aiKey: "Klucz API Anthropic",
        permissions: "Uprawnienia",
        wc: "WooCommerce aktywny",
        analytics: "Źródło analityki",
        optional: "Opcjonalne",
        privacy: "Twoje zapytania (mogą zawierać dane zamówień/klientów) są wysyłane do Anthropic w celu wygenerowania odpowiedzi.",
        privacyLink: "Informacje o prywatności",
      };
    default:
      return {
        title: "Ready to go",
        subtitle: "Here's what's working. Optional items can be enabled later from Settings.",
        providerByo: "Provider: Anthropic (your key)",
        providerCloud: "Provider: WPChat Cloud (waitlisted)",
        aiKey: "Anthropic API key",
        permissions: "Required capabilities",
        wc: "WooCommerce active",
        analytics: "Analytics provider",
        optional: "Optional",
        privacy: "Your requests (which can include order/customer data) are sent to Anthropic to generate replies.",
        privacyLink: "Privacy details",
      };
  }
}
