import { useEffect, useRef, useState, type FormEvent, type ReactNode } from "react";
import { AnimatePresence, motion } from "motion/react";
import { Send, Loader2, ExternalLink, LogOut, ChevronDown, History as HistoryIcon, Plus, Check, X } from "lucide-react";
import ReactMarkdown from "react-markdown";
import remarkGfm from "remark-gfm";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { HistoryDrawer } from "./HistoryDrawer";
import { OrdersTable, extractOrders } from "./OrdersTable";
import { QuickChips } from "./QuickChips";
import { cn } from "@/lib/utils";

interface Boot {
  restUrl: string;
  nonce: string;
  userId: number;
  userName?: string;
  locale: string;
  siteName?: string;
  siteUrl?: string;
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
  /** Inline thumbnail rendered next to a user message that uploaded an image. */
  attachmentPreviewUrl?: string;
}

interface PendingAttachment {
  file: File;
  previewUrl: string; // object URL revoked when cleared
}

interface UploadResult {
  attachment_id: number;
  url: string;
  width: number;
  height: number;
  mime_type: string;
  filename: string;
}

interface WireMessage {
  role: "user" | "assistant";
  content: string;
}

export function Chat({ boot }: { boot?: Boot }) {
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [input, setInput] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [attachment, setAttachment] = useState<PendingAttachment | null>(null);
  const [attachmentUploading, setAttachmentUploading] = useState(false);
  const [conversationId, setConversationId] = useState<string | null>(null);
  const [historyOpen, setHistoryOpen] = useState(false);
  const [historyRefreshKey, setHistoryRefreshKey] = useState(0);
  const [loadingConversation, setLoadingConversation] = useState(false);
  const endRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages, busy]);

  function clearAttachment() {
    if (attachment) URL.revokeObjectURL(attachment.previewUrl);
    setAttachment(null);
  }

  function pickAttachment(file: File) {
    if (attachment) URL.revokeObjectURL(attachment.previewUrl);
    setAttachment({ file, previewUrl: URL.createObjectURL(file) });
  }

  async function uploadAttachment(file: File): Promise<UploadResult> {
    if (!boot) throw new Error("Not ready");
    const fd = new FormData();
    fd.append("file", file);
    const res = await fetch(`${boot.restUrl}upload`, {
      method: "POST",
      headers: { "X-WP-Nonce": boot.nonce },
      credentials: "same-origin",
      body: fd,
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data?.error || `Upload failed (HTTP ${res.status})`);
    return data as UploadResult;
  }

  async function sendText(rawText: string) {
    const text = rawText.trim();
    if (!text && !attachment) return;
    if (busy || loadingConversation || !boot) return;

    let finalText = text;
    let previewForUser: string | undefined;

    // If there's a pending attachment, upload it before composing the
    // message. The marker line on the first line tells the LLM the
    // attachment id to use; the user sees the thumbnail beside their
    // bubble instead of the raw marker.
    if (attachment) {
      setAttachmentUploading(true);
      try {
        const uploaded = await uploadAttachment(attachment.file);
        finalText = `[Uploaded ${uploaded.filename} → attachment ${uploaded.attachment_id}]` +
          (text ? `\n${text}` : "");
        previewForUser = uploaded.url;
      } catch (err) {
        setError(err instanceof Error ? err.message : "Upload failed");
        setAttachmentUploading(false);
        return;
      }
      setAttachmentUploading(false);
      clearAttachment();
    }

    const newUser: ChatMessage = {
      role: "user",
      text: finalText,
      attachmentPreviewUrl: previewForUser,
    };
    const history: WireMessage[] = [...messages, newUser].map((m) => ({
      role: m.role,
      content: m.text,
    }));

    setMessages((m) => [...m, newUser]);
    setInput("");
    setBusy(true);
    setError(null);
    await postChat(history);
  }

  async function handleSend(e: FormEvent) {
    e.preventDefault();
    await sendText(input);
  }

  async function postChat(history: WireMessage[]) {
    if (!boot) return;

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
        {messages.length === 0 && !busy && (
          <EmptyHero
            locale={boot?.locale}
            input={input}
            setInput={setInput}
            onSend={handleSend}
            busy={busy || loadingConversation || attachmentUploading}
            attachment={attachment}
            attachmentUploading={attachmentUploading}
            onAttachPick={pickAttachment}
            onAttachClear={clearAttachment}
            onChipSelect={(q) => sendText(q)}
          />
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
                <div className="flex max-w-[88%] flex-col items-end gap-1.5 self-end">
                  {m.attachmentPreviewUrl && (
                    <a
                      href={m.attachmentPreviewUrl}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="block overflow-hidden border border-border/40"
                      style={{ borderRadius: 10 }}
                    >
                      <img
                        src={m.attachmentPreviewUrl}
                        alt="uploaded"
                        className="block h-32 w-auto object-cover"
                      />
                    </a>
                  )}
                  <div
                    className="whitespace-pre-wrap bg-primary px-3.5 py-2.5 text-sm leading-relaxed text-primary-foreground tabular-nums"
                    style={{ borderRadius: 10 }}
                  >
                    {stripUploadMarker(m.text)}
                  </div>
                </div>
              ) : (
                <>
                  {boot && m.toolCalls && extractOrders(m.toolCalls).length > 0 && (
                    <OrdersTable
                      orders={extractOrders(m.toolCalls)}
                      restUrl={boot.restUrl}
                      nonce={boot.nonce}
                      siteUrl={boot.siteUrl ?? ""}
                    />
                  )}
                  {/* When OrdersTable is rendered, strip any markdown table the
                      LLM included in its prose so the user doesn't see the same
                      data twice. Defensive — the system prompt already tells
                      the LLM not to emit a table, but models drift. */}
                  <AssistantBubble
                    text={
                      m.toolCalls && extractOrders(m.toolCalls).length > 0
                        ? stripMarkdownTables(m.text)
                        : m.text
                    }
                  />
                </>
              )}
              {m.toolCalls && m.toolCalls.length > 0 && (
                <ToolCallDisclosure calls={m.toolCalls} />
              )}
              {m.role === "assistant" && isPendingConfirmation(m, i, messages) && !busy && (
                <ConfirmCancelButtons
                  onConfirm={() => sendText("taip")}
                  onCancel={() => sendText("ne")}
                  locale={boot?.locale}
                />
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

      {messages.length > 0 && (
        <QuickChips
          locale={boot?.locale}
          busy={busy || loadingConversation}
          onSelect={(q) => sendText(q)}
        />
      )}

      <AnimatePresence>
        {messages.length > 0 && attachment && (
          <motion.div
            key="att-chip"
            initial={{ opacity: 0, y: 6 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: 6 }}
            transition={{ duration: 0.18 }}
            className="flex items-center gap-2 self-stretch border border-border/40 bg-secondary/40 px-2 py-1.5"
            style={{ borderRadius: 10 }}
          >
            <img
              src={attachment.previewUrl}
              alt=""
              className="size-10 shrink-0 object-cover"
              style={{ borderRadius: 6 }}
            />
            <div className="min-w-0 flex-1 text-xs">
              <div className="truncate font-medium text-foreground">{attachment.file.name}</div>
              <div className="text-[10.5px] text-muted-foreground">
                {attachmentUploading
                  ? "Įkeliama…"
                  : `${(attachment.file.size / 1024).toFixed(0)} KB · paspauskite Siųsti, kad pridėtumėte`}
              </div>
            </div>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              onClick={clearAttachment}
              disabled={attachmentUploading}
              aria-label="Remove attachment"
              className="size-7 text-muted-foreground"
            >
              <X className="size-4" />
            </Button>
          </motion.div>
        )}
      </AnimatePresence>

      {messages.length > 0 && (
      <form onSubmit={handleSend} className="flex items-center gap-2">
        <InlineInput
          value={input}
          onChange={setInput}
          placeholder={busy ? "Waiting for assistant…" : "Type…"}
          disabled={busy}
          onAttachPick={pickAttachment}
          attachDisabled={busy || attachmentUploading}
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
      )}

      <footer className="flex items-center justify-between text-xs text-muted-foreground">
        <span className="inline-flex items-center gap-2">
          {boot?.userName ? `${boot.userName} · ` : ""}user {boot?.userId ?? "?"}
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

/**
 * Remove GFM-style markdown tables from a piece of assistant text.
 * A markdown table is a contiguous run of lines starting with `|` and
 * containing a header-separator row (|---|---|). We drop the entire run
 * so the surrounding prose stays intact.
 */
function stripMarkdownTables(text: string): string {
  if (!text.includes("|")) return text;
  const lines = text.split("\n");
  const out: string[] = [];
  let i = 0;
  while (i < lines.length) {
    const line = lines[i];
    const looksLikeTableRow = /^\s*\|.*\|\s*$/.test(line);
    const nextIsSeparator =
      looksLikeTableRow &&
      i + 1 < lines.length &&
      /^\s*\|[\s|:-]+\|\s*$/.test(lines[i + 1]);
    if (nextIsSeparator) {
      // Skip the header, separator, and every following row that's still
      // a |-cell| line. Empty/non-pipe line ends the table.
      i += 2;
      while (i < lines.length && /^\s*\|.*\|\s*$/.test(lines[i])) i++;
      continue;
    }
    out.push(line);
    i++;
  }
  return out.join("\n").replace(/\n{3,}/g, "\n\n").trim();
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
              className="text-primary underline underline-offset-2 decoration-primary/60 hover:decoration-primary"
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

interface InlineInputProps {
  value: string;
  onChange: (v: string) => void;
  placeholder?: string;
  disabled?: boolean;
  onAttachPick: (file: File) => void;
  attachDisabled?: boolean;
  inputRef?: React.Ref<HTMLInputElement>;
  autoFocus?: boolean;
  large?: boolean;
}

/**
 * Text input with the attach (+) button inline on the right and no
 * surrounding border. Hidden file <input> sits in the wrapper so a tap
 * on the + opens the native picker.
 */
function InlineInput(props: InlineInputProps) {
  const fileRef = useRef<HTMLInputElement>(null);
  return (
    <div
      className={cn(
        "relative flex flex-1 items-center bg-secondary/30",
        props.large ? "h-11" : "h-10"
      )}
      style={{ borderRadius: 10 }}
    >
      <input
        ref={fileRef}
        type="file"
        accept="image/jpeg,image/png,image/webp"
        className="hidden"
        onChange={(e) => {
          const f = e.target.files?.[0];
          if (f) props.onAttachPick(f);
          e.target.value = "";
        }}
      />
      <input
        ref={props.inputRef}
        type="text"
        value={props.value}
        onChange={(e) => props.onChange(e.target.value)}
        placeholder={props.placeholder}
        disabled={props.disabled}
        autoFocus={props.autoFocus}
        className={cn(
          "h-full w-full min-w-0 bg-transparent pl-3 pr-10 text-foreground outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50",
          props.large ? "text-base" : "text-sm"
        )}
      />
      <button
        type="button"
        onClick={() => fileRef.current?.click()}
        disabled={props.attachDisabled}
        aria-label="Attach image"
        className="absolute right-1.5 top-1/2 flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted/60 hover:text-foreground disabled:opacity-50"
      >
        <Plus className="size-4" />
      </button>
    </div>
  );
}

interface EmptyHeroProps {
  locale?: string;
  input: string;
  setInput: (v: string) => void;
  onSend: (e: FormEvent) => void;
  busy: boolean;
  attachment: PendingAttachment | null;
  attachmentUploading: boolean;
  onAttachPick: (file: File) => void;
  onAttachClear: () => void;
  onChipSelect: (q: string) => void;
}

/**
 * Empty-state hero: large outcome-focused title with the input centered
 * underneath, auto-focused. QuickChips below for tap-to-fill shortcuts.
 * Shown only when `messages.length === 0` — the chat reverts to the
 * regular bottom-anchored input as soon as the conversation starts.
 */
function EmptyHero(props: EmptyHeroProps) {
  const { confirm: _c, title, placeholder } = heroLabelsFor(props.locale);
  void _c;
  const inputRef = useRef<HTMLInputElement>(null);

  // Auto-focus on mount so the cursor is ready. iOS Safari only honors
  // focus() if it's called inside a user gesture — but the chat is reached
  // from a logged-in session via a tap, so the page itself counts.
  useEffect(() => {
    inputRef.current?.focus();
  }, []);

  return (
    <div className="my-auto flex w-full flex-col items-center gap-5 self-center px-2">
      <h2 className="text-center text-2xl font-semibold leading-tight tracking-tight text-foreground sm:text-3xl">
        {title}
      </h2>

      {props.attachment && (
        <div
          className="flex w-full max-w-xl items-center gap-2 border border-border/40 bg-secondary/40 px-2 py-1.5"
          style={{ borderRadius: 10 }}
        >
          <img
            src={props.attachment.previewUrl}
            alt=""
            className="size-10 shrink-0 object-cover"
            style={{ borderRadius: 6 }}
          />
          <div className="min-w-0 flex-1 text-xs">
            <div className="truncate font-medium text-foreground">{props.attachment.file.name}</div>
            <div className="text-[10.5px] text-muted-foreground">
              {props.attachmentUploading
                ? "Įkeliama…"
                : `${(props.attachment.file.size / 1024).toFixed(0)} KB`}
            </div>
          </div>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={props.onAttachClear}
            disabled={props.attachmentUploading}
            aria-label="Remove attachment"
            className="size-7 text-muted-foreground"
          >
            <X className="size-4" />
          </Button>
        </div>
      )}

      <form onSubmit={props.onSend} className="flex w-full max-w-xl items-center gap-2">
        <InlineInput
          inputRef={inputRef}
          value={props.input}
          onChange={props.setInput}
          placeholder={placeholder}
          disabled={props.busy}
          onAttachPick={props.onAttachPick}
          attachDisabled={props.busy}
          autoFocus
          large
        />
        <Button
          type="submit"
          size="icon"
          disabled={(!props.input.trim() && !props.attachment) || props.busy}
          className="size-11 shrink-0"
          aria-label="Send message"
        >
          <Send className="size-4" />
        </Button>
      </form>

      <QuickChips
        locale={props.locale}
        busy={props.busy}
        onSelect={props.onChipSelect}
      />
    </div>
  );
}

function heroLabelsFor(locale?: string): { title: string; placeholder: string; confirm: string } {
  switch (locale) {
    case "lt":
      return {
        title: "Kokio rezultato siekiate?",
        placeholder: "Rašykite arba kalbėkite…",
        confirm: "Patvirtinti",
      };
    case "ru":
      return {
        title: "Какого результата хотите?",
        placeholder: "Введите или скажите…",
        confirm: "Подтвердить",
      };
    case "pl":
      return {
        title: "Jaki rezultat chcesz osiągnąć?",
        placeholder: "Napisz lub powiedz…",
        confirm: "Potwierdź",
      };
    default:
      return {
        title: "What outcome do you want?",
        placeholder: "Type or speak…",
        confirm: "Confirm",
      };
  }
}

/**
 * Strip the "[Uploaded foo.jpg → attachment N]" marker line from a
 * user-bubble's visible text. The marker is metadata for the LLM, not
 * something the user needs to see again under the thumbnail they
 * already picked.
 */
function stripUploadMarker(text: string): string {
  return text.replace(/^\[Uploaded[^\]]+→\s*attachment\s+\d+\][ \t]*\n?/u, "").trim();
}

/**
 * Decide whether an assistant message is in "awaiting confirmation" state:
 * it called a preview_* tool (or the older preview_team_member_role_change)
 * AND no later message has called an apply_* tool. If true, the UI renders
 * Confirm/Cancel buttons under that bubble so the user doesn't have to type
 * one of taip/gerai/ok/etc.
 */
function isPendingConfirmation(message: ChatMessage, index: number, all: ChatMessage[]): boolean {
  if (message.role !== "assistant") return false;
  const calls = message.toolCalls ?? [];
  const hasPreview = calls.some((c) => c.name.startsWith("preview_"));
  if (!hasPreview) return false;
  // If a later assistant message already applied or the user explicitly cancelled, no buttons.
  for (let j = index + 1; j < all.length; j++) {
    const next = all[j];
    if (next.role === "assistant" && next.toolCalls?.some((c) => c.name.startsWith("apply_"))) {
      return false;
    }
  }
  return index === all.length - 1; // only show on the latest assistant message
}

function ConfirmCancelButtons({
  onConfirm,
  onCancel,
  locale,
}: {
  onConfirm: () => void;
  onCancel: () => void;
  locale?: string;
}) {
  const labels = labelsFor(locale);
  return (
    <motion.div
      initial={{ opacity: 0, y: 4 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.22 }}
      className="flex items-center gap-2 self-start"
    >
      <Button type="button" size="sm" onClick={onConfirm} className="gap-1.5">
        <Check className="size-4" />
        {labels.confirm}
      </Button>
      <Button type="button" size="sm" variant="secondary" onClick={onCancel} className="gap-1.5">
        <X className="size-4" />
        {labels.cancel}
      </Button>
    </motion.div>
  );
}

function labelsFor(locale?: string): { confirm: string; cancel: string } {
  switch (locale) {
    case "lt": return { confirm: "Patvirtinti", cancel: "Atšaukti" };
    case "ru": return { confirm: "Подтвердить", cancel: "Отмена" };
    case "pl": return { confirm: "Potwierdź", cancel: "Anuluj" };
    default:   return { confirm: "Confirm", cancel: "Cancel" };
  }
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
