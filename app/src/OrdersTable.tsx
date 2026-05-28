import { useEffect, useRef, useState } from "react";
import { AnimatePresence, motion } from "motion/react";
import { MoreVertical, ExternalLink, Loader2, Check, AlertCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";

/**
 * Order shape returned by Tools::summarize() on the PHP side.
 * Matches the JSON in tool_call output for list_orders /
 * find_customer_orders.
 */
export interface OrderRow {
  id: number;
  number: string | number;
  status: string;
  date: string | null;
  total: number;
  currency: string;
  customer: string;
  email: string;
  item_names: string[];
}

interface StatusOption {
  slug: string;
  label: string;
}

interface OrdersTableProps {
  orders: OrderRow[];
  restUrl: string;
  nonce: string;
  siteUrl: string;
  /** Called after a successful inline status change so the chat can refresh. */
  onChanged?: () => void;
}

export function OrdersTable({ orders, restUrl, nonce, siteUrl, onChanged }: OrdersTableProps) {
  const [statuses, setStatuses] = useState<StatusOption[]>([]);
  const [localOrders, setLocalOrders] = useState<OrderRow[]>(orders);

  useEffect(() => {
    setLocalOrders(orders);
  }, [orders]);

  useEffect(() => {
    fetch(`${restUrl}actions/order-statuses`, {
      headers: { "X-WP-Nonce": nonce },
      credentials: "same-origin",
    })
      .then((r) => r.json())
      .then((d) => setStatuses(d.statuses ?? []))
      .catch(() => {
        /* swallow — table still renders, dropdown just shows fallback */
      });
  }, [restUrl, nonce]);

  function patchOrder(updated: OrderRow) {
    setLocalOrders((prev) => prev.map((o) => (o.id === updated.id ? updated : o)));
    onChanged?.();
  }

  if (localOrders.length === 0) return null;

  return (
    <div
      className="self-stretch overflow-hidden border border-border/40 bg-secondary/30"
      style={{ borderRadius: 12 }}
    >
      <table className="w-full border-collapse text-xs tabular-nums">
        <thead className="bg-background/50 text-muted-foreground">
          <tr>
            <th className="px-2.5 py-2 text-left font-semibold">#</th>
            <th className="px-2.5 py-2 text-left font-semibold">Data</th>
            <th className="px-2.5 py-2 text-left font-semibold">Klientas</th>
            <th className="px-2.5 py-2 text-right font-semibold">Suma</th>
            <th className="px-2.5 py-2 text-left font-semibold">Statusas</th>
            <th className="px-2.5 py-2 w-8"></th>
          </tr>
        </thead>
        <tbody>
          {localOrders.map((o) => (
            <OrderTableRow
              key={o.id}
              order={o}
              statuses={statuses}
              restUrl={restUrl}
              nonce={nonce}
              siteUrl={siteUrl}
              onUpdated={patchOrder}
            />
          ))}
        </tbody>
      </table>
    </div>
  );
}

function OrderTableRow({
  order,
  statuses,
  restUrl,
  nonce,
  siteUrl,
  onUpdated,
}: {
  order: OrderRow;
  statuses: StatusOption[];
  restUrl: string;
  nonce: string;
  siteUrl: string;
  onUpdated: (o: OrderRow) => void;
}) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const adminUrl = `${siteUrl}/wp-admin/admin.php?page=wc-orders&action=edit&id=${order.id}`;
  const itemPreview = order.item_names[0] ?? "";
  const itemSuffix = order.item_names.length > 1 ? ` +${order.item_names.length - 1}` : "";

  async function changeStatus(slug: string) {
    setBusy(true);
    setError(null);
    try {
      const res = await fetch(`${restUrl}actions/order/${order.id}/status`, {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-WP-Nonce": nonce },
        credentials: "same-origin",
        body: JSON.stringify({ status: slug }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data?.error || `HTTP ${res.status}`);
      if (data.order) {
        onUpdated(data.order);
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : "Failed");
    } finally {
      setBusy(false);
    }
  }

  return (
    <tr className="border-t border-border/30 hover:bg-background/30">
      <td className="px-2.5 py-2 font-semibold text-foreground">#{order.number}</td>
      <td className="px-2.5 py-2 text-muted-foreground whitespace-nowrap">{formatShortDate(order.date)}</td>
      <td className="px-2.5 py-2 text-foreground">
        <div className="line-clamp-1 max-w-[14ch]">{order.customer || order.email || "—"}</div>
        {itemPreview && (
          <div className="line-clamp-1 max-w-[18ch] text-[10.5px] text-muted-foreground">
            {itemPreview}
            {itemSuffix}
          </div>
        )}
      </td>
      <td className="px-2.5 py-2 text-right font-semibold text-foreground whitespace-nowrap">
        {formatTotal(order.total, order.currency)}
      </td>
      <td className="px-2.5 py-2">
        <StatusBadge status={order.status} statuses={statuses} />
        {error && (
          <div className="mt-1 flex items-center gap-1 text-[10px] text-destructive">
            <AlertCircle className="size-3" />
            {error}
          </div>
        )}
      </td>
      <td className="px-1 py-2 text-right">
        <RowMenu
          order={order}
          statuses={statuses}
          adminUrl={adminUrl}
          busy={busy}
          onChangeStatus={changeStatus}
        />
      </td>
    </tr>
  );
}

function RowMenu({
  order,
  statuses,
  adminUrl,
  busy,
  onChangeStatus,
}: {
  order: OrderRow;
  statuses: StatusOption[];
  adminUrl: string;
  busy: boolean;
  onChangeStatus: (slug: string) => void;
}) {
  const [open, setOpen] = useState(false);
  const [submenuOpen, setSubmenuOpen] = useState(false);
  const wrapRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    function onClick(e: MouseEvent) {
      if (!wrapRef.current?.contains(e.target as Node)) {
        setOpen(false);
        setSubmenuOpen(false);
      }
    }
    function onKey(e: KeyboardEvent) {
      if (e.key === "Escape") {
        setOpen(false);
        setSubmenuOpen(false);
      }
    }
    document.addEventListener("mousedown", onClick);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("mousedown", onClick);
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);

  return (
    <div ref={wrapRef} className="relative inline-block">
      <Button
        type="button"
        variant="ghost"
        size="icon"
        onClick={() => setOpen((v) => !v)}
        disabled={busy}
        className="size-7 text-muted-foreground"
        aria-label={`Actions for order #${order.number}`}
      >
        {busy ? <Loader2 className="size-3.5 animate-spin" /> : <MoreVertical className="size-4" />}
      </Button>

      <AnimatePresence>
        {open && (
          <motion.div
            initial={{ opacity: 0, scale: 0.95, y: -4 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.95, y: -4 }}
            transition={{ duration: 0.14 }}
            className="absolute right-0 top-full z-30 mt-1 min-w-[200px] border border-border/60 bg-popover py-1 text-sm text-popover-foreground shadow-lg"
            style={{ borderRadius: 8 }}
          >
            <a
              href={adminUrl}
              target="_blank"
              rel="noopener noreferrer"
              onClick={() => setOpen(false)}
              className="flex items-center gap-2 px-3 py-1.5 hover:bg-muted/60"
            >
              <ExternalLink className="size-3.5 text-muted-foreground" />
              Atidaryti WP admin
            </a>

            <button
              type="button"
              onMouseEnter={() => setSubmenuOpen(true)}
              onClick={() => setSubmenuOpen((v) => !v)}
              className="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-left hover:bg-muted/60"
            >
              <span>Keisti statusą</span>
              <span className="text-muted-foreground">›</span>
            </button>

            <AnimatePresence>
              {submenuOpen && (
                <motion.div
                  initial={{ opacity: 0, x: -4 }}
                  animate={{ opacity: 1, x: 0 }}
                  exit={{ opacity: 0, x: -4 }}
                  transition={{ duration: 0.12 }}
                  className="ml-3 mr-1 mt-0.5 border-l border-border/40 pl-2"
                >
                  {statuses.length === 0 ? (
                    <div className="px-2 py-1.5 text-[11px] text-muted-foreground">Kraunama…</div>
                  ) : (
                    statuses.map((s) => {
                      const isCurrent = s.slug === order.status;
                      return (
                        <button
                          key={s.slug}
                          type="button"
                          onClick={() => {
                            setOpen(false);
                            setSubmenuOpen(false);
                            if (!isCurrent) onChangeStatus(s.slug);
                          }}
                          disabled={isCurrent}
                          className={
                            "flex w-full items-center gap-1.5 rounded px-2 py-1 text-left text-[12px] " +
                            (isCurrent
                              ? "text-muted-foreground cursor-default"
                              : "hover:bg-muted/60 text-foreground")
                          }
                        >
                          {isCurrent && <Check className="size-3 text-muted-foreground" />}
                          <span className={isCurrent ? "" : "ml-4"}>{s.label}</span>
                        </button>
                      );
                    })
                  )}
                </motion.div>
              )}
            </AnimatePresence>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}

function StatusBadge({ status, statuses }: { status: string; statuses: StatusOption[] }) {
  const label = statuses.find((s) => s.slug === status)?.label ?? status;
  const tone = statusTone(status);
  return (
    <Badge variant="secondary" className={tone + " font-medium"}>
      {label}
    </Badge>
  );
}

function statusTone(slug: string): string {
  switch (slug) {
    case "completed":
    case "panaudotas":
      return "bg-emerald-950/50 text-emerald-300 border-emerald-900/60";
    case "processing":
      return "bg-blue-950/50 text-blue-300 border-blue-900/60";
    case "pending":
    case "on-hold":
      return "bg-amber-950/50 text-amber-300 border-amber-900/60";
    case "cancelled":
    case "failed":
    case "refunded":
      return "bg-rose-950/50 text-rose-300 border-rose-900/60";
    default:
      return "";
  }
}

function formatShortDate(iso: string | null): string {
  if (!iso) return "—";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return d.toLocaleDateString("lt-LT", { month: "2-digit", day: "2-digit" });
}

function formatTotal(total: number, currency: string): string {
  const symbol = currency === "EUR" ? "€" : currency;
  return `${total.toFixed(0)} ${symbol}`;
}

/**
 * Extract orders from an assistant message's tool calls. We look for
 * list_orders or find_customer_orders outputs; they both include an
 * `orders` array of OrderRow.
 */
export function extractOrders(toolCalls: Array<{ name: string; output: unknown }>): OrderRow[] {
  for (const tc of toolCalls) {
    if (tc.name !== "list_orders" && tc.name !== "find_customer_orders") continue;
    const out = tc.output as { orders?: OrderRow[] } | null;
    if (out && Array.isArray(out.orders) && out.orders.length > 0) {
      return out.orders;
    }
  }
  return [];
}
