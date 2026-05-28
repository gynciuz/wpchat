import { Sparkles, TrendingUp, Users, AlertCircle, Receipt } from "lucide-react";

interface QuickChip {
  label: string;
  query: string;
  Icon: typeof Sparkles;
}

interface QuickChipsProps {
  locale?: string;
  busy: boolean;
  onSelect: (query: string) => void;
}

/**
 * Tappable preset chips above the input bar. Two-tap access to common
 * queries — Vlad uses /wpchat on his phone between clients and typing
 * Lithuanian on iOS is slow.
 *
 * Chips POST to the existing /chat endpoint with a pre-formed message
 * string. No new backend needed.
 *
 * Future: once dev-telemetry data exists (v0.4.3 plan), swap the
 * static set for the user's top-5 actual queries.
 */
export function QuickChips({ locale, busy, onSelect }: QuickChipsProps) {
  const chips = chipsFor(locale);
  return (
    <div className="-mx-1 flex items-center gap-1.5 overflow-x-auto px-1 pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
      {chips.map((c) => (
        <button
          key={c.label}
          type="button"
          onClick={() => onSelect(c.query)}
          disabled={busy}
          className="inline-flex shrink-0 items-center gap-1.5 border border-border/40 bg-secondary/40 px-2.5 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-secondary/70 disabled:opacity-60"
          style={{ borderRadius: 999 }}
        >
          <c.Icon className="size-3.5 text-muted-foreground" />
          {c.label}
        </button>
      ))}
    </div>
  );
}

function chipsFor(locale?: string): QuickChip[] {
  switch (locale) {
    case "lt":
      return [
        { label: "Paskutiniai užsakymai", query: "rodyk paskutinius 10 užsakymų", Icon: Receipt },
        { label: "Šios savaitės pardavimai", query: "kiek šią savaitę uždirbau", Icon: TrendingUp },
        { label: "Lankytojai", query: "kiek šią savaitę buvo lankytojų", Icon: Users },
        { label: "Nepanaudoti kuponai", query: "rodyk nepanaudotus dovanų kuponus", Icon: Sparkles },
        { label: "Atviros klaidos", query: "ar yra užsakymų su klaida", Icon: AlertCircle },
      ];
    case "ru":
      return [
        { label: "Последние заказы", query: "покажи последние 10 заказов", Icon: Receipt },
        { label: "Продажи за неделю", query: "сколько заработали на этой неделе", Icon: TrendingUp },
        { label: "Посетители", query: "сколько посетителей на этой неделе", Icon: Users },
        { label: "Неиспользованные купоны", query: "покажи неиспользованные подарочные купоны", Icon: Sparkles },
        { label: "Ошибки в заказах", query: "есть ли заказы с ошибкой", Icon: AlertCircle },
      ];
    case "pl":
      return [
        { label: "Ostatnie zamówienia", query: "pokaż ostatnie 10 zamówień", Icon: Receipt },
        { label: "Sprzedaż w tym tygodniu", query: "ile zarobiłem w tym tygodniu", Icon: TrendingUp },
        { label: "Odwiedzający", query: "ilu odwiedzających było w tym tygodniu", Icon: Users },
        { label: "Niewykorzystane vouchery", query: "pokaż niewykorzystane vouchery", Icon: Sparkles },
        { label: "Błędy zamówień", query: "czy są zamówienia z błędem", Icon: AlertCircle },
      ];
    default:
      return [
        { label: "Recent orders", query: "show last 10 orders", Icon: Receipt },
        { label: "This week's sales", query: "how much did I earn this week", Icon: TrendingUp },
        { label: "Visitors", query: "how many visitors this week", Icon: Users },
        { label: "Unredeemed vouchers", query: "show unredeemed gift vouchers", Icon: Sparkles },
        { label: "Order errors", query: "any orders with errors", Icon: AlertCircle },
      ];
  }
}
