import { useRef, useState, type FormEvent } from "react";
import { motion } from "motion/react";
import { Loader2, LifeBuoy, Send, X, Check, AlertCircle } from "lucide-react";
import ReactMarkdown from "react-markdown";
import remarkGfm from "remark-gfm";
import { Button } from "@/components/ui/button";

interface SupportBoot {
  restUrl: string;
  nonce: string;
  locale?: string;
}

interface HelpMessage {
  role: "user" | "assistant";
  text: string;
}

interface Props {
  boot: SupportBoot;
  conversationId: string | null;
  lastError?: string | null;
  /** Open straight into the report form (from the error banner) vs. help chat. */
  initialView?: "help" | "report";
  onClose: () => void;
}

/**
 * Help & support overlay. Two jobs:
 *  - a free help chat (POSTs /chat with mode:'support' — no tools, FAQ-grounded);
 *  - "Report a problem" → POSTs /support, which emails the developer the recent
 *    conversation + the error the user hit.
 * Reuses the main chat plumbing; no new backend stack.
 */
export function Support({ boot, conversationId, lastError, initialView = "help", onClose }: Props) {
  const t = labelsFor(boot.locale);
  const [view, setView] = useState<"help" | "report">(initialView);

  // --- help chat state ---
  const [messages, setMessages] = useState<HelpMessage[]>([]);
  const [input, setInput] = useState("");
  const [busy, setBusy] = useState(false);
  const [chatError, setChatError] = useState<string | null>(null);
  const endRef = useRef<HTMLDivElement>(null);

  // --- report state ---
  const [note, setNote] = useState("");
  const [sending, setSending] = useState(false);
  const [sent, setSent] = useState<"ok" | "fail" | null>(null);

  async function askHelp(e: FormEvent) {
    e.preventDefault();
    const text = input.trim();
    if (!text || busy) return;
    const next = [...messages, { role: "user" as const, text }];
    setMessages(next);
    setInput("");
    setBusy(true);
    setChatError(null);
    try {
      const res = await fetch(`${boot.restUrl}chat`, {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-WP-Nonce": boot.nonce },
        credentials: "same-origin",
        body: JSON.stringify({
          mode: "support",
          messages: next.map((m) => ({ role: m.role, content: m.text })),
        }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data?.error || `HTTP ${res.status}`);
      setMessages((m) => [...m, { role: "assistant", text: data.text ?? "" }]);
      requestAnimationFrame(() => endRef.current?.scrollIntoView({ behavior: "smooth" }));
    } catch (err) {
      setChatError(err instanceof Error ? err.message : "Request failed.");
    } finally {
      setBusy(false);
    }
  }

  async function submitReport(e: FormEvent) {
    e.preventDefault();
    if (sending) return;
    setSending(true);
    setSent(null);
    try {
      const res = await fetch(`${boot.restUrl}support`, {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-WP-Nonce": boot.nonce },
        credentials: "same-origin",
        body: JSON.stringify({
          note: note.trim(),
          error: lastError ?? "",
          conversation_id: conversationId ?? undefined,
        }),
      });
      const data = await res.json().catch(() => ({}));
      setSent(res.ok && data?.ok ? "ok" : "fail");
    } catch {
      setSent("fail");
    } finally {
      setSending(false);
    }
  }

  return (
    <motion.div
      className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      exit={{ opacity: 0 }}
      onClick={onClose}
    >
      <motion.div
        className="flex max-h-[80vh] w-full max-w-md flex-col overflow-hidden rounded-2xl border border-border/40 bg-background shadow-xl"
        initial={{ opacity: 0, y: 12, scale: 0.98 }}
        animate={{ opacity: 1, y: 0, scale: 1 }}
        exit={{ opacity: 0, y: 12, scale: 0.98 }}
        transition={{ duration: 0.2 }}
        onClick={(e) => e.stopPropagation()}
      >
        <header className="flex items-center justify-between border-b border-border/40 px-4 py-3">
          <span className="inline-flex items-center gap-2 text-sm font-semibold">
            <LifeBuoy className="size-4" /> {t.title}
          </span>
          <button type="button" onClick={onClose} className="text-muted-foreground hover:text-foreground">
            <X className="size-4" />
          </button>
        </header>

        <div className="flex gap-1 border-b border-border/40 px-2 py-2 text-xs">
          <button
            type="button"
            onClick={() => setView("help")}
            className={"rounded-md px-2.5 py-1 " + (view === "help" ? "bg-secondary text-foreground" : "text-muted-foreground hover:text-foreground")}
          >
            {t.tabHelp}
          </button>
          <button
            type="button"
            onClick={() => setView("report")}
            className={"rounded-md px-2.5 py-1 " + (view === "report" ? "bg-secondary text-foreground" : "text-muted-foreground hover:text-foreground")}
          >
            {t.tabReport}
          </button>
        </div>

        {view === "help" ? (
          <>
            <div className="flex-1 space-y-3 overflow-y-auto px-4 py-3">
              {messages.length === 0 && (
                <p className="text-sm text-muted-foreground">{t.helpIntro}</p>
              )}
              {messages.map((m, i) => (
                <div key={i} className={m.role === "user" ? "text-right" : ""}>
                  <div
                    className={
                      "inline-block max-w-[85%] whitespace-pre-wrap rounded-xl px-3 py-2 text-sm " +
                      (m.role === "user" ? "bg-primary/15 text-foreground" : "bg-secondary/40 text-foreground")
                    }
                  >
                    {m.role === "assistant" ? (
                      <div className="prose prose-sm prose-invert max-w-none">
                        <ReactMarkdown remarkPlugins={[remarkGfm]}>{m.text}</ReactMarkdown>
                      </div>
                    ) : (
                      m.text
                    )}
                  </div>
                </div>
              ))}
              {busy && (
                <div className="inline-flex items-center gap-2 text-xs text-muted-foreground">
                  <Loader2 className="size-3.5 animate-spin" /> {t.thinking}
                </div>
              )}
              {chatError && (
                <div className="flex items-center gap-2 text-xs text-destructive">
                  <AlertCircle className="size-3.5" /> {chatError}
                </div>
              )}
              <div ref={endRef} />
            </div>
            <form onSubmit={askHelp} className="flex gap-2 border-t border-border/40 p-3">
              <input
                value={input}
                onChange={(e) => setInput(e.target.value)}
                placeholder={t.helpPlaceholder}
                disabled={busy}
                className="h-10 flex-1 rounded-lg border-0 bg-secondary/30 px-3 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-foreground/30"
              />
              <Button type="submit" size="sm" className="h-10" disabled={busy || !input.trim()}>
                {busy ? <Loader2 className="size-4 animate-spin" /> : <Send className="size-4" />}
              </Button>
            </form>
          </>
        ) : (
          <form onSubmit={submitReport} className="flex flex-col gap-3 p-4">
            <p className="text-sm text-muted-foreground">{t.reportIntro}</p>
            {lastError && (
              <div className="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive">
                {t.reportError}: {lastError}
              </div>
            )}
            <textarea
              value={note}
              onChange={(e) => setNote(e.target.value)}
              rows={4}
              placeholder={t.reportPlaceholder}
              className="rounded-lg border-0 bg-secondary/30 px-3 py-2 text-sm outline-none placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-foreground/30"
            />
            <p className="text-[11px] leading-snug text-muted-foreground">{t.reportPrivacy}</p>
            {sent === "ok" ? (
              <div className="inline-flex items-center gap-2 text-sm text-emerald-400">
                <Check className="size-4" /> {t.reportSent}
              </div>
            ) : (
              <Button type="submit" disabled={sending} className="gap-2">
                {sending ? <Loader2 className="size-4 animate-spin" /> : <Send className="size-4" />}
                {t.reportSend}
              </Button>
            )}
            {sent === "fail" && (
              <p className="text-xs text-destructive">{t.reportFail}</p>
            )}
          </form>
        )}
      </motion.div>
    </motion.div>
  );
}

function labelsFor(locale?: string) {
  switch (locale) {
    case "lt":
      return {
        title: "Pagalba",
        tabHelp: "Klausti pagalbos",
        tabReport: "Pranešti apie problemą",
        helpIntro: "Klauskite, kaip naudotis WPChat — pvz. „kaip gauti API raktą?“ arba „kodėl neveikia užsakymų sąrašas?“.",
        helpPlaceholder: "Jūsų klausimas…",
        thinking: "Galvoju…",
        reportIntro: "Jei kažkas neveikia, parašykite trumpai — išsiųsime kūrėjui kartu su paskutiniu pokalbiu ir klaida.",
        reportError: "Klaida",
        reportPlaceholder: "Kas nutiko? Ką darėte?",
        reportPrivacy: "Bus išsiųstas jūsų paskutinis pokalbis ir svetainės informacija, kad galėtume padėti.",
        reportSend: "Siųsti kūrėjui",
        reportSent: "Ačiū! Pranešimas išsiųstas.",
        reportFail: "Nepavyko išsiųsti. Pabandykite vėliau.",
      };
    case "ru":
      return {
        title: "Помощь",
        tabHelp: "Спросить",
        tabReport: "Сообщить о проблеме",
        helpIntro: "Спросите, как пользоваться WPChat — например «как получить API-ключ?» или «почему не работает список заказов?».",
        helpPlaceholder: "Ваш вопрос…",
        thinking: "Думаю…",
        reportIntro: "Если что-то не работает, опишите кратко — отправим разработчику вместе с последним диалогом и ошибкой.",
        reportError: "Ошибка",
        reportPlaceholder: "Что случилось? Что вы делали?",
        reportPrivacy: "Будут отправлены ваш последний диалог и данные сайта, чтобы мы могли помочь.",
        reportSend: "Отправить разработчику",
        reportSent: "Спасибо! Сообщение отправлено.",
        reportFail: "Не удалось отправить. Попробуйте позже.",
      };
    case "pl":
      return {
        title: "Pomoc",
        tabHelp: "Zapytaj",
        tabReport: "Zgłoś problem",
        helpIntro: "Zapytaj, jak korzystać z WPChat — np. „jak zdobyć klucz API?” albo „czemu nie działa lista zamówień?”.",
        helpPlaceholder: "Twoje pytanie…",
        thinking: "Myślę…",
        reportIntro: "Jeśli coś nie działa, opisz krótko — wyślemy do twórcy razem z ostatnią rozmową i błędem.",
        reportError: "Błąd",
        reportPlaceholder: "Co się stało? Co robiłeś?",
        reportPrivacy: "Wyślemy twoją ostatnią rozmowę i dane witryny, abyśmy mogli pomóc.",
        reportSend: "Wyślij do twórcy",
        reportSent: "Dzięki! Zgłoszenie wysłane.",
        reportFail: "Nie udało się wysłać. Spróbuj później.",
      };
    default:
      return {
        title: "Help",
        tabHelp: "Ask for help",
        tabReport: "Report a problem",
        helpIntro: "Ask how to use WPChat — e.g. “how do I get an API key?” or “why isn't the orders list working?”.",
        helpPlaceholder: "Your question…",
        thinking: "Thinking…",
        reportIntro: "If something isn't working, describe it briefly — we'll send it to the developer with your recent chat and the error.",
        reportError: "Error",
        reportPlaceholder: "What happened? What were you doing?",
        reportPrivacy: "Your recent conversation and site info will be sent so we can help.",
        reportSend: "Send to developer",
        reportSent: "Thanks! Your report was sent.",
        reportFail: "Couldn't send. Please try again later.",
      };
  }
}
