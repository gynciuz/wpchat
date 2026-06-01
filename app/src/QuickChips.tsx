interface QuickChip {
  label: string;
  query: string;
}

interface QuickChipsProps {
  locale?: string;
  busy: boolean;
  onSelect: (query: string) => void;
}

/**
 * Text-link preset shortcuts. Underlined labels, no border, no
 * background, no icon — just type. Tap fires the preset query.
 *
 * Future: once dev-telemetry data exists, swap the static set for
 * the user's top-5 actual queries.
 */
export function QuickChips({ locale, busy, onSelect }: QuickChipsProps) {
  const chips = chipsFor(locale);
  return (
    <div className="-mx-1 flex flex-wrap items-center justify-center gap-x-4 gap-y-1.5 px-1 pb-1 text-center">
      {chips.map((c) => (
        <button
          key={c.label}
          type="button"
          onClick={() => onSelect(c.query)}
          disabled={busy}
          className="text-xs text-muted-foreground underline underline-offset-4 decoration-border/60 transition-colors hover:text-foreground hover:decoration-foreground/60 disabled:opacity-60"
        >
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
        { label: "Paskutiniai užsakymai", query: "rodyk paskutinius 10 užsakymų" },
        { label: "Šios savaitės pardavimai", query: "kiek šią savaitę uždirbau" },
        { label: "Lankytojai", query: "kiek šią savaitę buvo lankytojų" },
        { label: "Nepanaudoti kuponai", query: "rodyk nepanaudotus dovanų kuponus" },
        { label: "Atviros klaidos", query: "ar yra užsakymų su klaida" },
      ];
    case "ru":
      return [
        { label: "Последние заказы", query: "покажи последние 10 заказов" },
        { label: "Продажи за неделю", query: "сколько заработали на этой неделе" },
        { label: "Посетители", query: "сколько посетителей на этой неделе" },
        { label: "Неиспользованные купоны", query: "покажи неиспользованные подарочные купоны" },
        { label: "Ошибки в заказах", query: "есть ли заказы с ошибкой" },
      ];
    case "pl":
      return [
        { label: "Ostatnie zamówienia", query: "pokaż ostatnie 10 zamówień" },
        { label: "Sprzedaż w tym tygodniu", query: "ile zarobiłem w tym tygodniu" },
        { label: "Odwiedzający", query: "ilu odwiedzających było w tym tygodniu" },
        { label: "Niewykorzystane vouchery", query: "pokaż niewykorzystane vouchery" },
        { label: "Błędy zamówień", query: "czy są zamówienia z błędem" },
      ];
    default:
      return [
        { label: "Recent orders", query: "show last 10 orders" },
        { label: "This week's sales", query: "how much did I earn this week" },
        { label: "Visitors", query: "how many visitors this week" },
        { label: "Unredeemed vouchers", query: "show unredeemed gift vouchers" },
        { label: "Order errors", query: "any orders with errors" },
      ];
  }
}
