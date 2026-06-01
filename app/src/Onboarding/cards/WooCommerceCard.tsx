import { ExternalLink, ShoppingBag } from "lucide-react";
import type { Boot, OnboardingStatus } from "../types";

export function WooCommerceCard({ status, boot }: { status: OnboardingStatus; boot: Boot }) {
  const labels = labelsFor(boot.locale);
  return (
    <div className="space-y-4 text-center">
      <div className="mx-auto inline-flex size-12 items-center justify-center rounded-full bg-secondary/40">
        <ShoppingBag className="size-6 text-muted-foreground" />
      </div>
      <h2 className="text-2xl font-semibold tracking-tight">{labels.title}</h2>
      <p className="mx-auto max-w-md text-balance text-sm leading-relaxed text-muted-foreground">
        {labels.subtitle}
      </p>
      <a
        href={status.wc.install_url}
        target="_blank"
        rel="noopener noreferrer"
        className="inline-flex items-center gap-1.5 text-sm font-medium text-foreground underline underline-offset-4 decoration-foreground/40 hover:decoration-foreground"
      >
        {labels.install} <ExternalLink className="size-3.5" />
      </a>
    </div>
  );
}

function labelsFor(locale?: string) {
  switch (locale) {
    case "lt":
      return {
        title: "WooCommerce neaptiktas",
        subtitle: "Užsakymų valdymo funkcijos veiks, kai bus įdiegtas ir aktyvuotas WooCommerce. Galite tęsti ir įdiegti vėliau.",
        install: "Įdiegti WooCommerce",
      };
    case "ru":
      return {
        title: "WooCommerce не найден",
        subtitle: "Управление заказами заработает после установки и активации WooCommerce. Можно пропустить и установить позже.",
        install: "Установить WooCommerce",
      };
    case "pl":
      return {
        title: "Nie znaleziono WooCommerce",
        subtitle: "Zarządzanie zamówieniami zadziała po zainstalowaniu i aktywacji WooCommerce. Możesz pominąć i zainstalować później.",
        install: "Zainstaluj WooCommerce",
      };
    default:
      return {
        title: "WooCommerce not detected",
        subtitle: "Order management features come online once WooCommerce is installed and active. You can skip this for now and install it later.",
        install: "Install WooCommerce",
      };
  }
}
