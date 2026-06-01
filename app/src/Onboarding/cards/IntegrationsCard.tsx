import { useState } from "react";
import { Check, Copy } from "lucide-react";
import type { Boot, OnboardingStatus } from "../types";

export function IntegrationsCard({ status, boot }: { status: OnboardingStatus; boot: Boot }) {
  const labels = labelsFor(boot.locale);
  const cf = status.integrations.cf_purge;
  const git = status.integrations.git_sync;
  return (
    <div className="space-y-4">
      <div className="space-y-2 text-center">
        <h2 className="text-2xl font-semibold tracking-tight">{labels.title}</h2>
        <p className="text-sm text-muted-foreground">{labels.subtitle}</p>
      </div>
      <div className="mx-auto flex max-w-md flex-col gap-3">
        <IntegrationRow
          label={labels.cf}
          description={labels.cfWhat}
          configured={cf.configured}
          snippet={cf.snippet}
          configuredLabel={labels.configured}
        />
        <IntegrationRow
          label={labels.git}
          description={labels.gitWhat}
          configured={git.configured}
          snippet={git.snippet}
          configuredLabel={labels.configured}
        />
      </div>
    </div>
  );
}

function IntegrationRow({
  label,
  description,
  configured,
  snippet,
  configuredLabel,
}: {
  label: string;
  description: string;
  configured: boolean;
  snippet: string;
  configuredLabel: string;
}) {
  const [copied, setCopied] = useState(false);

  function copy() {
    navigator.clipboard?.writeText(snippet).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    });
  }

  return (
    <div className="rounded-lg border border-border/40 px-3.5 py-3">
      <div className="flex items-center justify-between gap-2">
        <div className="font-medium">{label}</div>
        {configured ? (
          <span className="inline-flex items-center gap-1 text-xs text-emerald-400">
            <Check className="size-3.5" />
            {configuredLabel}
          </span>
        ) : (
          <span className="text-xs text-muted-foreground">{description}</span>
        )}
      </div>
      {!configured && (
        <button
          type="button"
          onClick={copy}
          className="mt-2 flex w-full items-center justify-between gap-2 rounded-md bg-background/40 px-2.5 py-2 text-left font-mono text-[11px] text-muted-foreground hover:bg-background/60"
        >
          <pre className="overflow-x-auto whitespace-pre-wrap">{snippet}</pre>
          <span className="shrink-0 text-[10px] text-muted-foreground">
            {copied ? <Check className="size-3.5" /> : <Copy className="size-3.5" />}
          </span>
        </button>
      )}
    </div>
  );
}

function labelsFor(locale?: string) {
  switch (locale) {
    case "lt":
      return {
        title: "Papildomi integracijos (neprivaloma)",
        subtitle: "Šių nereikia, kad pokalbis veiktų — bet juos įjungus, redagavimai bus matomi greičiau.",
        cf: "Cloudflare automatinis cache išvalymas",
        cfWhat: "Spustelėjus išsaugoti, viešas URL atsinaujina sekundėmis.",
        git: "Git auto-commit į svetainės repozitoriją",
        gitWhat: "Pokalbio redagavimai įsipareigoja ir nukeliami į pagrindinę.",
        configured: "Sukonfigūruota",
      };
    case "ru":
      return {
        title: "Дополнительные интеграции (опционально)",
        subtitle: "Не нужны для работы чата — но с ними изменения видны быстрее.",
        cf: "Cloudflare — автоочистка кэша",
        cfWhat: "Публичный URL обновляется за секунды после сохранения.",
        git: "Git auto-commit в репозиторий сайта",
        gitWhat: "Изменения чата коммитятся и пушатся в main.",
        configured: "Настроено",
      };
    case "pl":
      return {
        title: "Dodatkowe integracje (opcjonalne)",
        subtitle: "Czat działa bez nich — z nimi zmiany pojawiają się szybciej.",
        cf: "Cloudflare — automatyczne czyszczenie cache",
        cfWhat: "Publiczny URL odświeża się w sekundy po zapisie.",
        git: "Git auto-commit do repozytorium witryny",
        gitWhat: "Edycje z czatu są commitowane i pushowane do main.",
        configured: "Skonfigurowane",
      };
    default:
      return {
        title: "Optional integrations",
        subtitle: "Not required for chat to work — but they make edits visible faster.",
        cf: "Cloudflare auto cache purge",
        cfWhat: "Public URL refreshes in seconds after a save.",
        git: "Git auto-commit to your site's repo",
        gitWhat: "Chat edits commit and push to main automatically.",
        configured: "Configured",
      };
  }
}
