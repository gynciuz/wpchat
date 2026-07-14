import type { Boot, OnboardingStatus } from "../types";

interface Props {
  status: OnboardingStatus;
  boot: Boot;
  onNext: () => void;
}

/**
 * Card 1 — the mirror. Show the user themselves: their name, their
 * site, ONE concrete thing this chat will let them do. No product
 * tour, no feature grid. Design principles #1 (anchor to human) and
 * #6 (reflect the user).
 */
export function WelcomeCard({ status, boot }: Props) {
  const greeting = greetingFor(boot.locale, status.user.first_name || status.user.display_name);
  const concrete = concreteFor(boot.locale, status.site.name);

  return (
    <div className="space-y-6 text-center">
      <div className="space-y-2">
        <h1 className="text-balance text-3xl font-semibold leading-tight tracking-tight text-foreground sm:text-4xl">
          {greeting}
        </h1>
        <p className="text-balance text-base leading-relaxed text-muted-foreground">
          {concrete}
        </p>
      </div>
    </div>
  );
}

function greetingFor(locale: string | undefined, name: string): string {
  const n = name.trim() || "there";
  switch (locale) {
    case "lt": return `Sveiki, ${n}.`;
    case "ru": return `Здравствуйте, ${n}.`;
    case "pl": return `Witaj, ${n}.`;
    default:   return `Hi, ${n}.`;
  }
}

function concreteFor(locale: string | undefined, site: string): string {
  switch (locale) {
    case "lt":
      return `Pasiruoškime ChatAdmin ${site} svetainei — 2 minutės, kad galėtumėte tvarkyti užsakymus pokalbiu.`;
    case "ru":
      return `Настроим ChatAdmin для ${site} — 2 минуты, чтобы вы могли управлять заказами через чат.`;
    case "pl":
      return `Skonfigurujmy ChatAdmin dla ${site} — 2 minuty, by zarządzać zamówieniami przez czat.`;
    default:
      return `Let's get ChatAdmin ready for ${site} — 2 minutes to manage orders by chat.`;
  }
}
