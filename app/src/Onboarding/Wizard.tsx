import { useEffect, useMemo, useState } from "react";
import { AnimatePresence, motion } from "motion/react";
import { ChevronLeft, ChevronRight, Sparkles, ArrowRight } from "lucide-react";
import { Button } from "@/components/ui/button";
import type { OnboardingStatus, Boot } from "./types";
import { WelcomeCard } from "./cards/WelcomeCard";
import { ApiKeyCard } from "./cards/ApiKeyCard";
import { ModelCard } from "./cards/ModelCard";
import { PermissionsCard } from "./cards/PermissionsCard";
import { WooCommerceCard } from "./cards/WooCommerceCard";
import { AnalyticsCard } from "./cards/AnalyticsCard";
import { BackendsCard } from "./cards/BackendsCard";
import { SummaryCard } from "./cards/SummaryCard";
import { ProviderCard } from "./cards/ProviderCard";

/**
 * First-run onboarding stepper. Cards are picked dynamically based on
 * what the host install actually needs — design principle #2 (one sharp
 * thing at a time) drives the per-card focus, #5 (state of mind) drives
 * what set of cards we even show (a site with everything already
 * configured doesn't get drowned in green checkmarks).
 *
 * Sequence:
 *   1. Welcome — reflect the user with their name + site, give them ONE
 *      next step (Start).
 *   2. API key (interactive, only if missing or constant-locked)
 *   3. Model (interactive, only if not already set)
 *   4. Permissions (diagnostic, only if missing)
 *   5. WooCommerce (diagnostic, only if not active)
 *   6. Analytics (diagnostic, always — a no-detected reply prompts an install)
 *   7. Content backends (diagnostic, always — shows what's available)
 *   8. Integrations (CF + Git, diagnostic, only if either is unconfigured)
 *   9. Summary — capability matrix + "Take me to the chat".
 */
export function OnboardingWizard({ boot }: { boot: Boot }) {
  const [status, setStatus] = useState<OnboardingStatus | null>(null);
  const [stepIndex, setStepIndex] = useState(0);
  const [skippedIds, setSkippedIds] = useState<Set<string>>(new Set());
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    refreshStatus();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function refreshStatus() {
    try {
      const res = await fetch(`${boot.restUrl}onboarding/status`, {
        headers: { "X-WP-Nonce": boot.nonce },
        credentials: "same-origin",
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data?.error ?? `HTTP ${res.status}`);
      setStatus(data);
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed to load status");
    }
  }

  async function markComplete() {
    try {
      await fetch(`${boot.restUrl}onboarding/complete`, {
        method: "POST",
        headers: { "X-WP-Nonce": boot.nonce, "Content-Type": "application/json" },
        credentials: "same-origin",
      });
      window.location.href = boot.siteUrl ? `${boot.siteUrl}/wpchat` : "/wpchat";
    } catch {
      window.location.href = "/wpchat";
    }
  }

  // Compute steps dynamically based on the current status — design
  // principle #5 (state of mind, not state of app): power users with
  // everything configured skip straight to the summary; first-runners
  // see the full sequence.
  const steps = useMemo(() => buildSteps(status, boot), [status, boot]);

  if (error) {
    return <ErrorScreen message={error} onRetry={refreshStatus} />;
  }
  if (!status) {
    return <LoadingScreen />;
  }

  const current = steps[stepIndex];
  const isLast = stepIndex === steps.length - 1;

  function next() {
    if (isLast) {
      markComplete();
      return;
    }
    setStepIndex((i) => Math.min(i + 1, steps.length - 1));
  }
  function prev() {
    setStepIndex((i) => Math.max(i - 1, 0));
  }
  function skip() {
    setSkippedIds((prev) => new Set(prev).add(current.id));
    next();
  }

  return (
    // `min-h-[100dvh]` honors mobile dynamic-viewport changes (Safari
    // address bar collapse / iOS keyboard show) better than `min-h-screen`.
    // The footer is sticky so a tall card (or an open keyboard) can never
    // bury the Back/Skip/Next controls below the visible viewport.
    <div className="mx-auto flex min-h-[100dvh] max-w-2xl flex-col px-4">
      <Header total={steps.length} current={stepIndex} />

      <main className="flex flex-1 flex-col justify-center overflow-y-auto py-2">
        <AnimatePresence mode="wait" initial={false}>
          <motion.div
            key={current.id}
            initial={{ opacity: 0, y: 12, filter: "blur(6px)" }}
            animate={{ opacity: 1, y: 0, filter: "blur(0px)" }}
            exit={{ opacity: 0, y: -8, filter: "blur(4px)" }}
            transition={{ type: "spring", duration: 0.42, bounce: 0 }}
            className="w-full"
          >
            {current.render({
              status,
              boot,
              onNext: next,
              onUpdateStatus: refreshStatus,
              skipped: skippedIds,
            })}
          </motion.div>
        </AnimatePresence>
      </main>

      <Footer
        canPrev={stepIndex > 0}
        canSkip={current.skippable !== false}
        isLast={isLast}
        onPrev={prev}
        onSkip={skip}
        onNext={next}
        nextLabel={current.nextLabel ?? (isLast ? labelsFor(boot.locale).enterChat : labelsFor(boot.locale).next)}
      />
    </div>
  );
}

function Header({ total, current }: { total: number; current: number }) {
  return (
    <header
      className="sticky top-0 z-10 flex items-center justify-between bg-background pb-4"
      style={{ paddingTop: "max(env(safe-area-inset-top, 0px), 1.5rem)" }}
    >
      <div className="flex items-center gap-2 text-sm font-medium text-muted-foreground">
        <Sparkles className="size-4 text-foreground" />
        WPChat
      </div>
      <div className="flex items-center gap-1.5" aria-label={`Step ${current + 1} of ${total}`}>
        {Array.from({ length: total }).map((_, i) => (
          <span
            key={i}
            className={
              "h-1 rounded-full transition-all " +
              (i < current
                ? "w-3 bg-foreground/40"
                : i === current
                  ? "w-6 bg-foreground"
                  : "w-3 bg-foreground/10")
            }
          />
        ))}
      </div>
    </header>
  );
}

function Footer({
  canPrev,
  canSkip,
  isLast,
  onPrev,
  onSkip,
  onNext,
  nextLabel,
}: {
  canPrev: boolean;
  canSkip: boolean;
  isLast: boolean;
  onPrev: () => void;
  onSkip: () => void;
  onNext: () => void;
  nextLabel: string;
}) {
  return (
    <footer
      className="sticky bottom-0 z-10 flex items-center justify-between bg-background pt-4"
      style={{ paddingBottom: "max(env(safe-area-inset-bottom, 0px), 1rem)" }}
    >
      <Button
        type="button"
        variant="ghost"
        size="sm"
        onClick={onPrev}
        disabled={!canPrev}
        className="text-muted-foreground"
      >
        <ChevronLeft className="size-4" />
        <span className="ml-1">Back</span>
      </Button>

      <div className="flex items-center gap-2">
        {canSkip && !isLast && (
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={onSkip}
            className="text-muted-foreground"
          >
            Skip
          </Button>
        )}
        <Button type="button" size="sm" onClick={onNext} className="gap-1.5">
          {nextLabel}
          {isLast ? <ArrowRight className="size-4" /> : <ChevronRight className="size-4" />}
        </Button>
      </div>
    </footer>
  );
}

function LoadingScreen() {
  return (
    <div className="mx-auto flex min-h-screen items-center justify-center text-sm text-muted-foreground">
      Loading…
    </div>
  );
}

function ErrorScreen({ message, onRetry }: { message: string; onRetry: () => void }) {
  return (
    <div className="mx-auto flex min-h-screen max-w-md flex-col items-center justify-center gap-3 px-4 text-center">
      <div className="text-sm text-destructive">{message}</div>
      <Button onClick={onRetry} size="sm" variant="secondary">
        Try again
      </Button>
    </div>
  );
}

interface Step {
  id: string;
  skippable?: boolean;
  nextLabel?: string;
  render: (args: {
    status: OnboardingStatus;
    boot: Boot;
    onNext: () => void;
    onUpdateStatus: () => Promise<void>;
    skipped: Set<string>;
  }) => React.ReactNode;
}

function buildSteps(status: OnboardingStatus | null, boot: Boot): Step[] {
  const steps: Step[] = [];

  // Always start with the mirror — design principle #6.
  steps.push({
    id: "welcome",
    skippable: false,
    nextLabel: labelsFor(boot.locale).start,
    render: ({ status, boot, onNext }) => (
      <WelcomeCard status={status} boot={boot} onNext={onNext} />
    ),
  });

  if (!status) return steps;

  // Provider step — the choice that frames everything below. Sets
  // the tone before we ask for an API key vs send the user to a
  // waitlist.
  steps.push({
    id: "provider",
    render: ({ status, boot, onUpdateStatus, onNext }) => (
      <ProviderCard
        status={status}
        boot={boot}
        onUpdateStatus={onUpdateStatus}
        onAdvance={onNext}
      />
    ),
  });

  // If the user picked the Cloud waitlist, we don't ask for an
  // Anthropic key (they're not bringing one) or a model (we'll
  // pick on their behalf when the service opens). Continue
  // straight to the capability diagnostics + summary.
  const usingCloud = status.provider?.current === "cloud-waitlist";

  if (!usingCloud) {
    if (!status.apiKey.ok) {
      steps.push({
        id: "api-key",
        render: ({ status, boot, onUpdateStatus, onNext }) => (
          <ApiKeyCard status={status} boot={boot} onUpdateStatus={onUpdateStatus} onAdvance={onNext} />
        ),
      });
    }

    steps.push({
      id: "model",
      render: ({ status, boot, onUpdateStatus }) => (
        <ModelCard status={status} boot={boot} onUpdateStatus={onUpdateStatus} />
      ),
    });
  }

  if (!status.permissions.ok) {
    steps.push({
      id: "permissions",
      render: ({ status, boot }) => <PermissionsCard status={status} boot={boot} />,
    });
  }

  if (!status.wc.active) {
    steps.push({
      id: "wc",
      render: ({ status, boot }) => <WooCommerceCard status={status} boot={boot} />,
    });
  }

  // Analytics card always shows — even if a provider is detected, it's
  // a useful "here's how we know" moment.
  steps.push({
    id: "analytics",
    render: ({ status, boot }) => <AnalyticsCard status={status} boot={boot} />,
  });

  // Content backends always — shows what the chat can edit on this site.
  steps.push({
    id: "backends",
    render: ({ status, boot, onUpdateStatus }) => (
      <BackendsCard status={status} boot={boot} onUpdateStatus={onUpdateStatus} />
    ),
  });

  // CF auto-purge + Git auto-commit integrations are site-specific
  // (CachePurge lives in GE's child theme; GitSync needs a writable
  // git repo at ABSPATH). They're documented in the plugin README for
  // power users; they don't belong in a first-run wizard — per design
  // principle #5 (state of mind, not state of app), a fresh-install
  // user is not in "advanced sysadmin" mode.

  steps.push({
    id: "summary",
    skippable: false,
    render: ({ status, boot, skipped }) => (
      <SummaryCard status={status} boot={boot} skipped={skipped} />
    ),
  });

  return steps;
}

function labelsFor(locale?: string): { start: string; next: string; enterChat: string } {
  switch (locale) {
    case "lt": return { start: "Pradėti", next: "Toliau", enterChat: "Pradėti pokalbį" };
    case "ru": return { start: "Начать", next: "Далее", enterChat: "Открыть чат" };
    case "pl": return { start: "Zacznij", next: "Dalej", enterChat: "Otwórz czat" };
    default:   return { start: "Start", next: "Next", enterChat: "Open chat" };
  }
}
