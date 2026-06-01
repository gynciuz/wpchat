import { Check, ExternalLink, TrendingUp } from "lucide-react";
import type { Boot, OnboardingStatus } from "../types";

export function AnalyticsCard({ status, boot }: { status: OnboardingStatus; boot: Boot }) {
  const labels = labelsFor(boot.locale);
  const detected = status.analytics.detected;
  const hasOne = detected.length > 0;

  return (
    <div className="space-y-4 text-center">
      <div className="mx-auto inline-flex size-12 items-center justify-center rounded-full bg-secondary/40">
        <TrendingUp className="size-6 text-muted-foreground" />
      </div>
      <h2 className="text-2xl font-semibold tracking-tight">
        {hasOne ? labels.titleHave : labels.titleNone}
      </h2>

      {hasOne ? (
        <div className="mx-auto inline-flex max-w-md items-center gap-2 rounded-lg bg-emerald-950/30 px-4 py-2.5 text-sm text-emerald-100">
          <Check className="size-4 text-emerald-400" />
          {detected.map((d) => d.name).join(" · ")}
        </div>
      ) : (
        <>
          <p className="mx-auto max-w-md text-balance text-sm leading-relaxed text-muted-foreground">
            {labels.subtitleNone}
          </p>
          <div className="mx-auto flex max-w-md flex-col gap-2 text-left">
            {status.analytics.recommended.map((r) => (
              <a
                key={r.id}
                href={r.install_url}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center justify-between rounded-lg border border-border/40 px-3.5 py-2.5 text-sm transition-colors hover:bg-secondary/40"
              >
                <span className="font-medium">{r.name}</span>
                <ExternalLink className="size-3.5 text-muted-foreground" />
              </a>
            ))}
          </div>
        </>
      )}
    </div>
  );
}

function labelsFor(locale?: string) {
  switch (locale) {
    case "lt":
      return {
        titleHave: "Analitika prijungta",
        titleNone: "Analitika neprijungta",
        subtitleNone:
          "Pokalbio klausimas „kiek šią savaitę buvo lankytojų\" pradės veikti, kai prijungsite vieną iš šių įrankių. Rekomenduojama — Google Site Kit (nemokama).",
      };
    case "ru":
      return {
        titleHave: "Аналитика подключена",
        titleNone: "Аналитика не подключена",
        subtitleNone:
          "Вопрос «сколько посетителей на этой неделе» заработает после подключения одного из этих инструментов. Рекомендуется — Google Site Kit (бесплатно).",
      };
    case "pl":
      return {
        titleHave: "Analityka połączona",
        titleNone: "Analityka niepołączona",
        subtitleNone:
          "Pytanie „ilu było odwiedzających\" zadziała po połączeniu jednego z tych narzędzi. Polecane — Google Site Kit (bezpłatny).",
      };
    default:
      return {
        titleHave: "Analytics connected",
        titleNone: "No analytics provider yet",
        subtitleNone:
          "The 'how many visitors this week' question will start working once one of these is connected. Recommended — Google Site Kit (free).",
      };
  }
}
