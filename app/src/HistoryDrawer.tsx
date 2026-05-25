import { useEffect, useState } from "react";
import { AnimatePresence, motion } from "motion/react";
import { History, Plus, X, MessageSquare, Loader2 } from "lucide-react";
import { Button } from "@/components/ui/button";

interface ConversationListItem {
  conversation: string;
  label: string;
  last_activity: string;
  message_count: number;
}

interface HistoryDrawerProps {
  open: boolean;
  onClose: () => void;
  onSelect: (conversationId: string) => void;
  onNewChat: () => void;
  restUrl: string;
  nonce: string;
  /** Bump this when a new message lands so the list refetches. */
  refreshKey?: number;
  currentConversationId?: string | null;
}

export function HistoryDrawer({
  open,
  onClose,
  onSelect,
  onNewChat,
  restUrl,
  nonce,
  refreshKey,
  currentConversationId,
}: HistoryDrawerProps) {
  const [items, setItems] = useState<ConversationListItem[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) return;
    let cancelled = false;
    setLoading(true);
    setError(null);
    fetch(`${restUrl}conversations`, {
      headers: { "X-WP-Nonce": nonce },
      credentials: "same-origin",
    })
      .then(async (r) => {
        const data = await r.json();
        if (!r.ok) throw new Error(data?.error || `HTTP ${r.status}`);
        if (!cancelled) setItems(data.conversations ?? []);
      })
      .catch((e) => {
        if (!cancelled) setError(e instanceof Error ? e.message : "Failed to load history.");
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [open, restUrl, nonce, refreshKey]);

  return (
    <AnimatePresence>
      {open && (
        <>
          <motion.div
            key="backdrop"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.18 }}
            onClick={onClose}
            className="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm"
          />
          <motion.aside
            key="drawer"
            initial={{ x: "-100%" }}
            animate={{ x: 0 }}
            exit={{ x: "-100%" }}
            transition={{ type: "spring", duration: 0.42, bounce: 0 }}
            className="fixed inset-y-0 left-0 z-50 flex w-[min(360px,90vw)] flex-col border-r border-border/40 bg-background"
          >
            <div className="flex items-center justify-between border-b border-border/40 px-4 py-3">
              <div className="flex items-center gap-2 text-sm font-semibold">
                <History className="size-4" />
                History
              </div>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                onClick={onClose}
                aria-label="Close history"
                className="size-8"
              >
                <X className="size-4" />
              </Button>
            </div>

            <div className="border-b border-border/40 px-3 py-2">
              <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={() => {
                  onNewChat();
                  onClose();
                }}
                className="w-full justify-start"
              >
                <Plus className="size-4" />
                <span className="ml-2">New chat</span>
              </Button>
            </div>

            <div className="flex-1 overflow-y-auto px-2 py-2 text-sm">
              {loading && (
                <div className="flex items-center gap-2 px-2 py-3 text-muted-foreground">
                  <Loader2 className="size-3.5 animate-spin" />
                  Loading…
                </div>
              )}
              {error && (
                <div className="px-2 py-3 text-destructive">{error}</div>
              )}
              {!loading && !error && items.length === 0 && (
                <div className="px-2 py-6 text-center text-xs text-muted-foreground">
                  No conversations yet — they appear here after your first message.
                </div>
              )}
              {!loading &&
                !error &&
                items.map((it) => {
                  const isCurrent = it.conversation === currentConversationId;
                  return (
                    <button
                      key={it.conversation}
                      type="button"
                      onClick={() => {
                        onSelect(it.conversation);
                        onClose();
                      }}
                      className={
                        "flex w-full flex-col gap-1 rounded px-2.5 py-2 text-left transition-colors hover:bg-muted/50 " +
                        (isCurrent ? "bg-muted/60" : "")
                      }
                      style={{ borderRadius: 8 }}
                    >
                      <div className="flex items-start gap-2">
                        <MessageSquare className="mt-0.5 size-3.5 shrink-0 text-muted-foreground" />
                        <span className="line-clamp-2 text-sm leading-snug text-foreground">
                          {it.label || "(no label)"}
                        </span>
                      </div>
                      <div className="ml-5 flex items-center gap-2 text-[10.5px] uppercase tracking-wide text-muted-foreground tabular-nums">
                        <span>{formatRelativeTime(it.last_activity)}</span>
                        <span>·</span>
                        <span>{it.message_count} msg</span>
                      </div>
                    </button>
                  );
                })}
            </div>
          </motion.aside>
        </>
      )}
    </AnimatePresence>
  );
}

function formatRelativeTime(utc: string): string {
  const ts = Date.parse(utc.replace(" ", "T") + "Z");
  if (Number.isNaN(ts)) return utc;
  const diff = Math.max(0, Date.now() - ts);
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return "now";
  if (mins < 60) return `${mins}m ago`;
  const hours = Math.floor(mins / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  if (days < 7) return `${days}d ago`;
  const date = new Date(ts);
  return date.toLocaleDateString(undefined, { month: "short", day: "numeric" });
}
