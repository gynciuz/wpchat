import { useEffect, useRef, useState, type FormEvent } from "react";

interface Boot {
  restUrl: string;
  nonce: string;
  userId: number;
  locale: string;
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

/** Wire-format message sent to the API (just role + plain text content). */
interface WireMessage {
  role: "user" | "assistant";
  content: string;
}

export function Chat({ boot }: { boot?: Boot }) {
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [input, setInput] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const endRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages, busy]);

  async function handleSend(e: FormEvent) {
    e.preventDefault();
    const text = input.trim();
    if (!text || busy || !boot) return;

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
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": boot.nonce,
        },
        credentials: "same-origin",
        body: JSON.stringify({ messages: history }),
      });

      const data = await res.json();
      if (!res.ok) {
        throw new Error(data?.error || `HTTP ${res.status}`);
      }

      const assistant: ChatMessage = {
        role: "assistant",
        text: data.text ?? "(no response)",
        toolCalls: data.tool_calls ?? [],
      };
      setMessages((m) => [...m, assistant]);
    } catch (err) {
      const message = err instanceof Error ? err.message : "Request failed.";
      setError(message);
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-4 px-4 py-6">
      <header className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold tracking-tight">WPChat</h1>
        <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
          v0.2
        </span>
      </header>

      <div className="flex min-h-[500px] flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        {messages.length === 0 && (
          <div className="my-auto self-center text-center text-sm text-gray-500">
            <p className="font-medium">Try one of these:</p>
            <ul className="mt-2 space-y-1 text-xs">
              <li>"show me 5 most recent orders"</li>
              <li>"order 2833 panaudotas, dalinai 30 eur, liko 20"</li>
              <li>"find orders from petras@example.com"</li>
            </ul>
          </div>
        )}

        {messages.map((m, i) => (
          <div key={i} className="flex flex-col gap-2">
            <div
              className={
                "max-w-[85%] whitespace-pre-wrap rounded-lg px-3 py-2 text-sm leading-relaxed " +
                (m.role === "user"
                  ? "self-end bg-blue-600 text-white"
                  : "self-start bg-gray-100 text-gray-900")
              }
            >
              {m.text}
            </div>
            {m.toolCalls && m.toolCalls.length > 0 && (
              <details className="self-start max-w-[85%] rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-xs">
                <summary className="cursor-pointer text-gray-600">
                  {m.toolCalls.length} tool call{m.toolCalls.length > 1 ? "s" : ""}
                </summary>
                <div className="mt-2 space-y-2">
                  {m.toolCalls.map((tc, j) => (
                    <div key={j} className="rounded bg-white p-2 font-mono">
                      <div className="font-semibold text-gray-700">{tc.name}</div>
                      <div className="text-gray-500">input: {JSON.stringify(tc.input)}</div>
                      <div className="mt-1 max-h-40 overflow-auto text-gray-500">
                        output: {JSON.stringify(tc.output, null, 2)}
                      </div>
                    </div>
                  ))}
                </div>
              </details>
            )}
          </div>
        ))}

        {busy && (
          <div className="self-start rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-600">
            <span className="inline-block animate-pulse">Thinking…</span>
          </div>
        )}

        {error && (
          <div className="self-stretch rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
            {error}
          </div>
        )}

        <div ref={endRef} />
      </div>

      <form onSubmit={handleSend} className="flex gap-2">
        <input
          type="text"
          value={input}
          onChange={(e) => setInput(e.target.value)}
          placeholder={busy ? "Waiting for assistant…" : "Type a message…"}
          disabled={busy}
          className="flex-1 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:opacity-50"
        />
        <button
          type="submit"
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 disabled:opacity-50"
          disabled={!input.trim() || busy}
        >
          Send
        </button>
      </form>

      <p className="text-xs text-gray-500">
        Logged-in user ID: {boot?.userId ?? "?"} · Locale: {boot?.locale ?? "?"} ·{" "}
        <a
          href="admin.php?page=wpchat-settings"
          className="text-blue-600 hover:underline"
        >
          Settings
        </a>
      </p>
    </div>
  );
}
