import { useEffect, useRef, useState, type FormEvent, type ReactNode } from "react";
import { AnimatePresence, motion } from "motion/react";
import { Mic, MicOff, Send, Loader2, ExternalLink, LogOut, ChevronDown, History as HistoryIcon, Plus } from "lucide-react";
import ReactMarkdown from "react-markdown";
import remarkGfm from "remark-gfm";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { HistoryDrawer } from "./HistoryDrawer";

interface Boot {
  restUrl: string;
  nonce: string;
  userId: number;
  userName?: string;
  locale: string;
  siteName?: string;
  logoutUrl?: string;
}

interface ToolCall {
  name: string;
  input: Record<string, unknown>;
  output: unknown;
}

interface ChatMessage {
  role: "user" | "assistant";
  text: string;
  toolCalls?: ToolCall[];
}

interface WireMessage {
  role: "user" | "assistant";
  content: string;
}

/** Minimal browser SpeechRecognition typing — supports Safari/Chrome on iOS + macOS. */
type SpeechRecognition = {
  lang: string;
  continuous: boolean;
  interimResults: boolean;
  start: () => void;
  stop: () => void;
  abort: () => void;
  onresult: ((ev: { results: ArrayLike<{ 0: { transcript: string }; isFinal: boolean }> }) => void) | null;
  onend: (() => void) | null;
  onerror: ((ev: { error: string }) => void) | null;
};

function getRecognition(lang: string): SpeechRecognition | null {
  const Ctor =
    (window as unknown as { SpeechRecognition?: new () => SpeechRecognition }).SpeechRecognition ??
    (window as unknown as { webkitSpeechRecognition?: new () => SpeechRecognition }).webkitSpeechRecognition;
  if (!Ctor) return null;
  const r = new Ctor();
  r.lang = lang;
  r.continuous = false;
  r.interimResults = true;
  return r;
}

export function Chat({ boot }: { boot?: Boot }) {
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [input, setInput] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [listening, setListening] = useState(false);
  const [conversationId, setConversationId] = useState<string | null>(null);
  const [historyOpen, setHistoryOpen] = useState(false);
  const [historyRefreshKey, setHistoryRefreshKey] = useState(0);
  const [loadingConversation, setLoadingConversation] = useState(false);
  const recRef = useRef<SpeechRecognition | null>(null);
  const endRef = useRef<HTMLDivElement>(null);

  // Speech recognition language preference. Vlad/wife → ru-RU; otherwise system locale.
  const speechLang = (boot?.locale === "lt" ? "lt-LT" : boot?.locale === "ru" ? "ru-RU" : "en-US");

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages, busy]);

  function toggleVoice() {
    if (listening) {
      recRef.current?.stop();
      return;
    }
    const rec = getRecognition(speechLang);
    if (!rec) {
      setError("Voice input not supported in this browser.");
      return;
    }
    recRef.current = rec;
    setListening(true);
    rec.onresult = (ev) => {
      let txt = "";
      for (let i = 0; i < ev.results.length; i++) {
        txt += ev.results[i][0].transcript;
      }
      setInput(txt);
    };
    rec.onend = () => setListening(false);
    rec.onerror = (ev) => {
      setListening(false);
      if (ev.error === "no-speech" || ev.error === "aborted") {
        return;
      }
      if (ev.error === "not-allowed" || ev.error === "service-not-allowed") {
        setError(
          "Microphone access denied. Click the lock/site-info icon in your browser address bar, set Microphone to Allow, then reload."
        );
        return;
      }
      setError(`Voice error: ${ev.error}`);
    };
    rec.start();
  }

  async function handleSend(e: FormEvent) {
    e.preventDefault();
    const text = input.trim();
    if (!text || busy || loadingConversation || !boot) return;
    if (listening) recRef.current?.stop();

    const newUser: ChatMessage = { role: "user", text };
    const history: WireMessage[] = [...messages, newUser].map((m) => ({
      role: m.role,
      content: m.text,
    }));

    setMessages((m) => [...m, newUser]);
    setInput("");
    setBusy(true);
    setError(null);

    try {
      const res = await fetch(`${boot.restUrl}chat`, {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-WP-Nonce": boot.nonce },
        credentials: "same-origin",
        body: JSON.stringify({
          messages: history,
          conversation_id: conversationId ?? undefined,
        }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data?.error || `HTTP ${res.status}`);

      if (data.conversation_id) {
        setConversationId(data.conversation_id);
      }
      setMessages((m) => [
        ...m,
        {
          role: "assistant",
          text: data.text ?? "(no response)",
          toolCalls: data.tool_calls ?? [],
        },
      ]);
      setHistoryRefreshKey((k) => k + 1);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Request failed.");
    } finally {
      setBusy(false);
    }
  }

  async function loadConversation(id: string) {
    if (!boot) return;
    setLoadingConversation(true);
    setError(null);
    try {
      const res = await fetch(`${boot.restUrl}conversations/${id}`, {
        headers: { "X-WP-Nonce": boot.nonce },
        credentials: "same-origin",
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data?.error || `HTTP ${res.status}`);
      const loaded: ChatMessage[] = (data.messages ?? []).map((m: { role: string; content: string; tool_calls?: ToolCall[] }) => ({
        role: m.role === "assistant" ? "assistant" : "user",
        text: m.content,
        toolCalls: m.tool_calls ?? [],
      }));
      setMessages(loaded);
      setConversationId(id);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to load conversation.");
    } finally {
      setLoadingConversation(false);
    }
  }

  function startNewChat() {
    setMessages([]);
    setConversationId(null);
    setError(null);
  }

  return (
    <div className="mx-auto flex min-h-screen max-w-3xl flex-col gap-4 px-4 py-6 sm:py-8">
      <header className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={() => setHistoryOpen(true)}
            aria-label="Open chat history"
            className="size-9 text-muted-foreground"
          >
            <HistoryIcon className="size-4" />
          </Button>
          <h1 className="text-2xl font-semibold tracking-tight">WPChat</h1>
          <Badge variant="secondary" className="hidden sm:inline-flex">
            v0.4
          </Badge>
          {boot?.siteName && (
            <span className="hidden text-sm text-muted-foreground sm:inline">
              · {boot.siteName}
            </span>
          )}
        </div>
        <div className="flex items-center gap-1">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={startNewChat}
            disabled={messages.length === 0 && conversationId === null}
            className="text-muted-foreground"
          >
            <Plus className="size-4" />
            <span className="ml-1.5 hidden sm:inline">New chat</span>
          </Button>
          {boot?.logoutUrl && (
            <Button asChild variant="ghost" size="sm" className="text-muted-foreground">
              <a href={boot.logoutUrl}>
                <LogOut className="size-4" />
                <span className="ml-2 hidden sm:inline">Logout</span>
              </a>
            </Button>
          )}
        </div>
      </header>

      {boot && (
        <HistoryDrawer
          open={historyOpen}
          onClose={() => setHistoryOpen(false)}
          onSelect={loadConversation}
          onNewChat={startNewChat}
          restUrl={boot.restUrl}
          nonce={boot.nonce}
          refreshKey={historyRefreshKey}
          currentConversationId={conversationId}
        />
      )}

      <div className="flex flex-1 flex-col gap-3 overflow-hidden">
        {messages.length === 0 && (
          <EmptyState />
        )}

        <AnimatePresence initial={false}>
          {messages.map((m, i) => (
            <motion.div
              key={i}
              layout
              initial={{ opacity: 0, y: 8, filter: "blur(5px)" }}
              animate={{ opacity: 1, y: 0, filter: "blur(0px)" }}
              exit={{ opacity: 0, y: -12, filter: "blur(4px)" }}
              transition={{ type: "spring", duration: 0.42, bounce: 0 }}
              className="flex flex-col gap-2"
            >
              {m.role === "user" ? (
                <div
                  className="max-w-[88%] self-end whitespace-pre-wrap bg-primary px-3.5 py-2.5 text-sm leading-relaxed text-primary-foreground tabular-nums"
                  style={{ borderRadius: 10 }}
                >
                  {m.text}
                </div>
              ) : (
                <AssistantBubble text={m.text} />
              )}
              {m.toolCalls && m.toolCalls.length > 0 && (
                <ToolCallDisclosure calls={m.toolCalls} />
              )}
            </motion.div>
          ))}
        </AnimatePresence>

        <AnimatePresence>
          {busy && (
            <motion.div
              key="thinking"
              initial={{ opacity: 0, y: 8 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -12 }}
              transition={{ type: "spring", duration: 0.42, bounce: 0 }}
              className="flex items-center gap-2 self-start bg-secondary px-3 py-2 text-sm text-muted-foreground"
              style={{ borderRadius: 10 }}
            >
              <Loader2 className="size-3.5 animate-spin" />
              <span>Thinking…</span>
            </motion.div>
          )}
        </AnimatePresence>

        {error && (
          <div
            className="self-stretch border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive"
            style={{ borderRadius: 10 }}
          >
            {error}
          </div>
        )}

        <div ref={endRef} />
      </div>

      <form onSubmit={handleSend} className="flex items-center gap-2">
        <Button
          type="button"
          variant={listening ? "destructive" : "secondary"}
          size="icon"
          onClick={toggleVoice}
          disabled={busy}
          aria-label={listening ? "Stop voice input" : "Start voice input"}
          className="size-10 shrink-0"
        >
          <AnimatePresence mode="wait" initial={false}>
            {listening ? (
              <motion.span
                key="off"
                initial={{ opacity: 0, scale: 0.6, filter: "blur(4px)" }}
                animate={{ opacity: 1, scale: 1, filter: "blur(0px)" }}
                exit={{ opacity: 0, scale: 0.6, filter: "blur(4px)" }}
                transition={{ duration: 0.18 }}
                className="inline-flex"
              >
                <MicOff className="size-4" />
              </motion.span>
            ) : (
              <motion.span
                key="on"
                initial={{ opacity: 0, scale: 0.6, filter: "blur(4px)" }}
                animate={{ opacity: 1, scale: 1, filter: "blur(0px)" }}
                exit={{ opacity: 0, scale: 0.6, filter: "blur(4px)" }}
                transition={{ duration: 0.18 }}
                className="inline-flex"
              >
                <Mic className="size-4" />
              </motion.span>
            )}
          </AnimatePresence>
        </Button>

        <Input
          type="text"
          value={input}
          onChange={(e) => setInput(e.target.value)}
          placeholder={listening ? "Listening…" : busy ? "Waiting for assistant…" : "Type or speak…"}
          disabled={busy}
          className="flex-1 h-10"
        />

        <Button
          type="submit"
          size="icon"
          disabled={!input.trim() || busy}
          className="size-10 shrink-0"
          aria-label="Send message"
        >
          <AnimatePresence mode="wait" initial={false}>
            {busy ? (
              <motion.span
                key="busy"
                initial={{ opacity: 0, scale: 0.6, filter: "blur(4px)" }}
                animate={{ opacity: 1, scale: 1, filter: "blur(0px)" }}
                exit={{ opacity: 0, scale: 0.6, filter: "blur(4px)" }}
                transition={{ duration: 0.18 }}
                className="inline-flex"
              >
                <Loader2 className="size-4 animate-spin" />
              </motion.span>
            ) : (
              <motion.span
                key="send"
                initial={{ opacity: 0, scale: 0.6, filter: "blur(4px)" }}
                animate={{ opacity: 1, scale: 1, filter: "blur(0px)" }}
                exit={{ opacity: 0, scale: 0.6, filter: "blur(4px)" }}
                transition={{ duration: 0.18 }}
                className="inline-flex"
              >
                <Send className="size-4" />
              </motion.span>
            )}
          </AnimatePresence>
        </Button>
      </form>

      <footer className="flex items-center justify-between text-xs text-muted-foreground">
        <span>
          {boot?.userName ? `${boot.userName} · ` : ""}user {boot?.userId ?? "?"} · {speechLang}
        </span>
        <a
          href="/wp-admin/admin.php?page=wpchat-settings"
          className="inline-flex items-center gap-1 hover:text-foreground"
        >
          Settings <ExternalLink className="size-3" />
        </a>
      </footer>
    </div>
  );
}

function AssistantBubble({ text }: { text: string }) {
  return (
    <div
      className="max-w-[92%] self-start bg-secondary/70 px-3.5 py-2.5 text-sm leading-relaxed text-secondary-foreground tabular-nums"
      style={{ borderRadius: 10 }}
    >
      <ReactMarkdown
        remarkPlugins={[remarkGfm]}
        components={{
          p: ({ children }: { children?: ReactNode }) => (
            <p className="my-1 first:mt-0 last:mb-0">{children}</p>
          ),
          ul: ({ children }: { children?: ReactNode }) => (
            <ul className="my-2 ml-4 list-disc space-y-1">{children}</ul>
          ),
          ol: ({ children }: { children?: ReactNode }) => (
            <ol className="my-2 ml-4 list-decimal space-y-1">{children}</ol>
          ),
          strong: ({ children }: { children?: ReactNode }) => (
            <strong className="font-semibold text-foreground">{children}</strong>
          ),
          code: ({ children, ...props }: { children?: ReactNode; className?: string }) => {
            const isBlock = (props.className ?? "").includes("language-");
            return isBlock ? (
              <pre className="my-2 overflow-x-auto rounded bg-background/60 p-2 text-xs">
                <code>{children}</code>
              </pre>
            ) : (
              <code className="rounded bg-background/60 px-1 py-0.5 text-[0.85em]">
                {children}
              </code>
            );
          },
          table: ({ children }: { children?: ReactNode }) => (
            <div className="my-2 overflow-x-auto">
              <table className="w-full border-collapse text-xs">{children}</table>
            </div>
          ),
          thead: ({ children }: { children?: ReactNode }) => (
            <thead className="bg-background/40">{children}</thead>
          ),
          th: ({ children }: { children?: ReactNode }) => (
            <th className="border border-border/60 px-2 py-1.5 text-left font-semibold">
              {children}
            </th>
          ),
          td: ({ children }: { children?: ReactNode }) => (
            <td className="border border-border/40 px-2 py-1.5 align-top">{children}</td>
          ),
          a: ({ children, href }: { children?: ReactNode; href?: string }) => (
            <a
              href={href}
              target="_blank"
              rel="noopener noreferrer"
              className="text-primary underline-offset-2 hover:underline"
            >
              {children}
            </a>
          ),
          hr: () => <hr className="my-3 border-border/40" />,
        }}
      >
        {text}
      </ReactMarkdown>
    </div>
  );
}

function EmptyState() {
  return (
    <div className="my-auto flex flex-col items-center gap-3 self-center text-center text-sm text-muted-foreground">
      <p className="font-medium text-foreground">Pasakyk, ką padaryti</p>
      <ul className="space-y-1 text-xs">
        <li>"rodyk 5 paskutinius užsakymus"</li>
        <li>"užsakymą 2833 panaudotas, dalinai 30 eur, liko 20"</li>
        <li>"найди заказ номер 2833"</li>
      </ul>
    </div>
  );
}

function ToolCallDisclosure({ calls }: { calls: ToolCall[] }) {
  const [open, setOpen] = useState(false);
  return (
    <div
      className="self-start max-w-[88%] border bg-muted/40 px-3 py-2 text-xs"
      style={{ borderRadius: 10 }}
    >
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="flex w-full items-center gap-1.5 text-muted-foreground hover:text-foreground transition-colors"
      >
        <ChevronDown
          className={"size-3 transition-transform " + (open ? "rotate-0" : "-rotate-90")}
        />
        {calls.length} tool call{calls.length > 1 ? "s" : ""}
      </button>
      {open && (
        <motion.div
          initial={{ opacity: 0, height: 0 }}
          animate={{ opacity: 1, height: "auto" }}
          className="mt-2 space-y-2 overflow-hidden"
        >
          {calls.map((tc, j) => (
            <div key={j} className="bg-background/60 p-2 font-mono" style={{ borderRadius: 6 }}>
              <div className="font-semibold text-foreground">{tc.name}</div>
              <div className="text-muted-foreground truncate">
                input: {JSON.stringify(tc.input)}
              </div>
              <div className="mt-1 max-h-40 overflow-auto text-muted-foreground tabular-nums">
                output: {JSON.stringify(tc.output, null, 2)}
              </div>
            </div>
          ))}
        </motion.div>
      )}
    </div>
  );
}
