import { Boxes, Sparkles } from "lucide-react";
import type { Boot, OnboardingStatus } from "../types";

export function BackendsCard({ status, boot }: { status: OnboardingStatus; boot: Boot }) {
  const labels = labelsFor(boot.locale);
  const siteKinds = status.backends.filter((b) => b.source === "site");
  const coreCount = status.backends.length - siteKinds.length;

  return (
    <div className="space-y-4">
      <div className="space-y-2 text-center">
        <div className="mx-auto inline-flex size-12 items-center justify-center rounded-full bg-secondary/40">
          <Boxes className="size-6 text-muted-foreground" />
        </div>
        <h2 className="text-2xl font-semibold tracking-tight">{labels.title}</h2>
        <p className="text-sm text-muted-foreground">
          {labels.subtitle.replace("{n}", String(coreCount))}
        </p>
      </div>

      {siteKinds.length > 0 && (
        <div className="mx-auto max-w-md space-y-2">
          <div className="text-center text-xs uppercase tracking-wide text-muted-foreground">
            {labels.custom}
          </div>
          {siteKinds.map((b) => (
            <div
              key={b.kind}
              className="rounded-lg border border-border/40 bg-secondary/20 px-3.5 py-2.5 text-left"
            >
              <div className="flex items-center gap-1.5 text-sm font-medium">
                <Sparkles className="size-3.5 text-amber-400" />
                <code className="font-mono">{b.kind}</code>
              </div>
              {b.fields.length > 0 && (
                <div className="mt-0.5 text-xs text-muted-foreground">
                  {labels.fields}: {b.fields.join(", ")}
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function labelsFor(locale?: string) {
  switch (locale) {
    case "lt":
      return {
        title: "Ką galima redaguoti pokalbiu",
        subtitle: "Iš pradžių — {n} standartiniai WordPress turinio tipai. Jūsų tema arba įskiepiai gali pridėti dar.",
        custom: "Šios svetainės papildomi tipai",
        fields: "Laukai",
      };
    case "ru":
      return {
        title: "Что чат может редактировать",
        subtitle: "Из коробки — {n} стандартных типа контента WordPress. Тема или плагины могут добавить ещё.",
        custom: "Дополнительные типы для этого сайта",
        fields: "Поля",
      };
    case "pl":
      return {
        title: "Co czat może edytować",
        subtitle: "Domyślnie — {n} standardowych typów treści WordPress. Motyw lub wtyczki mogą dodać więcej.",
        custom: "Dodatkowe typy dla tej witryny",
        fields: "Pola",
      };
    default:
      return {
        title: "What the chat can edit",
        subtitle: "Out of the box: {n} standard WordPress content types. Your theme or plugins may add more.",
        custom: "Custom kinds for this site",
        fields: "Fields",
      };
  }
}
