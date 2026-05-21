import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { Chat } from "./Chat";
import "./index.css";

declare global {
  interface Window {
    WPCHAT_BOOT?: {
      restUrl: string;
      nonce: string;
      userId: number;
      locale: string;
    };
  }
}

const container = document.getElementById("wpchat-root");
if (container) {
  // Default WPChat to dark theme (per product direction).
  container.classList.add("dark");
  createRoot(container).render(
    <StrictMode>
      <Chat boot={window.WPCHAT_BOOT} />
    </StrictMode>
  );
}
