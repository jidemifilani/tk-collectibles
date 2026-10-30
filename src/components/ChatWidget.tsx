"use client";

import { useEffect, useRef, useState } from "react";
import Link from "next/link";
import { MessageCircle, X, Send, Loader2 } from "lucide-react";
import type { SiteSettings } from "@prisma/client";
import { useChatStore, type ChatMsg as Msg } from "@/store/chat";

const QUICK_REPLIES = [
  "What's available now?",
  "How do I pay?",
  "Do you deliver nationwide?",
  "Talk to customer care",
];

/** Renders text with /product/slug paths turned into links. */
function MessageBody({ text }: { text: string }) {
  const parts = text.split(/(\/product\/[a-z0-9-]+)/gi);
  return (
    <>
      {parts.map((part, i) =>
        /^\/product\/[a-z0-9-]+$/i.test(part) ? (
          <Link
            key={i}
            href={part}
            className="font-medium text-terracotta underline underline-offset-2"
          >
            view item
          </Link>
        ) : (
          <span key={i}>{part}</span>
        )
      )}
    </>
  );
}

export function ChatWidget({ settings }: { settings: SiteSettings }) {
  const [open, setOpen] = useState(false);
  const [input, setInput] = useState("");
  const [loading, setLoading] = useState(false);
  const messages = useChatStore((s) => s.messages);
  const setMessages = useChatStore((s) => s.setMessages);
  const scrollRef = useRef<HTMLDivElement>(null);

  const greeting: Msg = {
    role: "assistant",
    content: `Hi! 👋 I'm the ${settings.storeName} assistant. Ask me about any item, prices, sizes, delivery, or how to order.`,
  };

  useEffect(() => {
    if (open && messages.length === 0) {
      setMessages([greeting]);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  useEffect(() => {
    scrollRef.current?.scrollTo({
      top: scrollRef.current.scrollHeight,
      behavior: "smooth",
    });
  }, [messages, loading]);

  async function send(text: string) {
    const question = text.trim();
    if (!question || loading) return;

    const next = [...messages, { role: "user" as const, content: question }];
    setMessages(next);
    setInput("");
    setLoading(true);

    try {
      const res = await fetch("/api/chat", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          messages: next.filter((m) => m !== greeting),
        }),
      });
      const data = await res.json();
      const reply =
        data.reply ||
        data.error ||
        "Sorry, something went wrong. Please try again.";
      setMessages((prev) => [...prev, { role: "assistant", content: reply }]);
    } catch {
      setMessages((prev) => [
        ...prev,
        {
          role: "assistant",
          content: `I'm having trouble connecting. Please call us on ${settings.customerCarePhone}.`,
        },
      ]);
    } finally {
      setLoading(false);
    }
  }

  return (
    <>
      {/* Launcher */}
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        className="fixed bottom-5 right-5 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-terracotta text-white shadow-lg transition-transform hover:scale-105 hover:bg-rust focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-charcoal focus-visible:ring-offset-2"
        aria-label={open ? "Close chat" : "Chat with us"}
      >
        {open ? <X className="h-6 w-6" /> : <MessageCircle className="h-6 w-6" />}
      </button>

      {/* Panel */}
      {open && (
        <div className="fixed bottom-24 right-5 z-50 flex h-[32rem] w-[calc(100vw-2.5rem)] max-w-sm flex-col overflow-hidden rounded-2xl border border-sand bg-cream shadow-2xl">
          <div className="flex items-center gap-3 bg-charcoal px-4 py-3 text-cream">
            <div className="flex h-9 w-9 items-center justify-center rounded-full bg-terracotta">
              <MessageCircle className="h-5 w-5" />
            </div>
            <div>
              <p className="text-sm font-semibold leading-tight">
                {settings.storeName} Assistant
              </p>
              <p className="text-xs text-sand/80">Typically replies instantly</p>
            </div>
          </div>

          <div
            ref={scrollRef}
            role="log"
            aria-live="polite"
            aria-label="Chat messages"
            className="flex-1 space-y-3 overflow-y-auto p-4"
          >
            {messages.map((m, i) => (
              <div
                key={i}
                className={`flex ${m.role === "user" ? "justify-end" : "justify-start"}`}
              >
                <div
                  className={`max-w-[85%] rounded-2xl px-3 py-2 text-sm ${
                    m.role === "user"
                      ? "rounded-br-sm bg-terracotta text-white"
                      : "rounded-bl-sm bg-white text-charcoal ring-1 ring-sand"
                  }`}
                >
                  <MessageBody text={m.content} />
                </div>
              </div>
            ))}

            {loading && (
              <div className="flex justify-start">
                <div className="rounded-2xl rounded-bl-sm bg-white px-3 py-2 text-sm ring-1 ring-sand">
                  <Loader2 className="h-4 w-4 animate-spin text-warmgray" />
                </div>
              </div>
            )}

            {messages.length <= 1 && !loading && (
              <div className="flex flex-wrap gap-2 pt-2">
                {QUICK_REPLIES.map((q) => (
                  <button
                    key={q}
                    type="button"
                    onClick={() => send(q)}
                    className="rounded-full border border-sand bg-white px-3 py-1.5 text-xs text-charcoal transition-colors hover:border-terracotta hover:text-terracotta"
                  >
                    {q}
                  </button>
                ))}
              </div>
            )}
          </div>

          <form
            onSubmit={(e) => {
              e.preventDefault();
              send(input);
            }}
            className="flex items-center gap-2 border-t border-sand bg-white p-3"
          >
            <input
              value={input}
              onChange={(e) => setInput(e.target.value)}
              placeholder="Ask about an item, price, delivery…"
              aria-label="Message"
              className="flex-1 rounded-full border border-sand bg-cream px-4 py-2 text-sm focus:border-terracotta focus:outline-none"
            />
            <button
              type="submit"
              disabled={loading || !input.trim()}
              className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-terracotta text-white transition-colors hover:bg-rust disabled:opacity-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-charcoal"
              aria-label="Send"
            >
              <Send className="h-4 w-4" />
            </button>
          </form>
        </div>
      )}
    </>
  );
}
