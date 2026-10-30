import { create } from "zustand";
import { persist } from "zustand/middleware";

export type ChatMsg = { role: "user" | "assistant"; content: string };

type ChatState = {
  messages: ChatMsg[];
  setMessages: (updater: ChatMsg[] | ((prev: ChatMsg[]) => ChatMsg[])) => void;
  clear: () => void;
};

export const useChatStore = create<ChatState>()(
  persist(
    (set) => ({
      messages: [],
      setMessages: (updater) =>
        set((state) => ({
          messages: typeof updater === "function" ? updater(state.messages) : updater,
        })),
      clear: () => set({ messages: [] }),
    }),
    { name: "thrift-chat" }
  )
);
