import { useState, type FormEvent } from "react";

interface Boot {
  restUrl: string;
  nonce: string;
  userId: number;
  locale: string;
}

interface Message {
  role: "user" | "assistant";
  text: string;
}

export function Chat({ boot }: { boot?: Boot }) {
  const [messages, setMessages] = useState<Message[]>([
    {
      role: "assistant",
      text:
        "Hi — WPChat scaffold is live. The chat backend and order tools land in the next commit. " +
        "For now, this proves the React bundle mounts inside wp-admin.",
    },
  ]);
  const [input, setInput] = useState("");

  function handleSend(e: FormEvent) {
    e.preventDefault();
    if (!input.trim()) return;
    setMessages((m) => [...m, { role: "user", text: input }]);
    setMessages((m) => [
      ...m,
      {
        role: "assistant",
        text:
          "(Stub) The chat loop and tool dispatch aren't wired yet. " +
          `When they are, I'll call ${boot?.restUrl ?? "/wp-json/wpchat/v1/"}chat with your message + a session nonce.`,
      },
    ]);
    setInput("");
  }

  return (
    <div className="mx-auto flex max-w-3xl flex-col gap-4 px-4 py-6">
      <header className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold tracking-tight">WPChat</h1>
        <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">
          scaffold v0.1
        </span>
      </header>

      <div className="flex min-h-[400px] flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
        {messages.map((m, i) => (
          <div
            key={i}
            className={
              "max-w-[85%] rounded-lg px-3 py-2 text-sm leading-relaxed " +
              (m.role === "user"
                ? "self-end bg-blue-600 text-white"
                : "self-start bg-gray-100 text-gray-900")
            }
          >
            {m.text}
          </div>
        ))}
      </div>

      <form onSubmit={handleSend} className="flex gap-2">
        <input
          type="text"
          value={input}
          onChange={(e) => setInput(e.target.value)}
          placeholder="Type a message…"
          className="flex-1 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        />
        <button
          type="submit"
          className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 disabled:opacity-50"
          disabled={!input.trim()}
        >
          Send
        </button>
      </form>

      <p className="text-xs text-gray-500">
        Logged-in user ID: {boot?.userId ?? "?"} · Locale: {boot?.locale ?? "?"}
      </p>
    </div>
  );
}
