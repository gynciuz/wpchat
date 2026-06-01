import { ExternalLink, AlertCircle } from "lucide-react";
import type { Boot, OnboardingStatus } from "../types";

export function PermissionsCard({ status, boot }: { status: OnboardingStatus; boot: Boot }) {
  const labels = labelsFor(boot.locale);
  const missing = status.permissions.required.filter(
    (c) => !status.permissions.has.includes(c)
  );
  return (
    <div className="space-y-4 text-center">
      <h2 className="text-2xl font-semibold tracking-tight">{labels.title}</h2>
      <p className="text-sm text-muted-foreground">{labels.subtitle}</p>

      <div className="mx-auto inline-flex max-w-md items-start gap-3 rounded-lg border border-amber-900/40 bg-amber-950/30 px-4 py-3 text-left">
        <AlertCircle className="mt-0.5 size-4 shrink-0 text-amber-400" />
        <div className="text-xs text-amber-100">
          <div className="mb-1 font-medium">
            {labels.role}: <code>{status.permissions.role || "—"}</code>
          </div>
          <div className="text-amber-200/80">
            {labels.missing}: <code>{missing.join(", ")}</code>
          </div>
        </div>
      </div>

      <p className="text-xs text-muted-foreground">
        {labels.askAdmin}{" "}
        <a
          href={boot.siteUrl + "/wp-admin/users.php"}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center gap-1 underline underline-offset-4 decoration-foreground/30 hover:decoration-foreground"
        >
          {labels.openUsers} <ExternalLink className="size-3" />
        </a>
      </p>
    </div>
  );
}

function labelsFor(locale?: string) {
  switch (locale) {
    case "lt":
      return {
        title: "Reikalingos teisės",
        subtitle: "Norint valdyti užsakymus pokalbiu, jūsų rolei reikia WooCommerce teisių.",
        role: "Jūsų rolė",
        missing: "Trūksta",
        askAdmin: "Paprašykite administratoriaus priskirti „Shop manager\" rolę:",
        openUsers: "Atidaryti vartotojų puslapį",
      };
    case "ru":
      return {
        title: "Нужны права",
        subtitle: "Для управления заказами через чат вашей роли нужны права WooCommerce.",
        role: "Ваша роль",
        missing: "Не хватает",
        askAdmin: "Попросите администратора назначить роль «Shop manager»:",
        openUsers: "Открыть страницу пользователей",
      };
    case "pl":
      return {
        title: "Wymagane uprawnienia",
        subtitle: "Do zarządzania zamówieniami przez czat Twoja rola potrzebuje uprawnień WooCommerce.",
        role: "Twoja rola",
        missing: "Brakuje",
        askAdmin: "Poproś administratora o przypisanie roli „Shop manager\":",
        openUsers: "Otwórz stronę użytkowników",
      };
    default:
      return {
        title: "Permissions needed",
        subtitle: "To manage orders by chat, your role needs WooCommerce capabilities.",
        role: "Your role",
        missing: "Missing",
        askAdmin: "Ask an administrator to grant the Shop manager role:",
        openUsers: "Open Users page",
      };
  }
}
