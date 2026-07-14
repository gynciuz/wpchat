import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { Chat } from "./Chat";
import { OnboardingWizard } from "./Onboarding/Wizard";
import "./index.css";

declare global {
  interface Window {
    CHATADMIN_BOOT?: {
      mode?: "chat" | "onboarding";
      restUrl: string;
      nonce: string;
      userId: number;
      userName?: string;
      firstName?: string;
      locale: string;
      siteName?: string;
      siteUrl?: string;
      logoutUrl?: string;
      version?: string;
    };
  }
}

const container = document.getElementById("chatadmin-root");
if (container) {
  container.classList.add("dark");
  const boot = window.CHATADMIN_BOOT;
  const mode = boot?.mode ?? "chat";
  createRoot(container).render(
    <StrictMode>
      {mode === "onboarding" && boot ? (
        <OnboardingWizard boot={boot} />
      ) : (
        <Chat boot={boot} />
      )}
    </StrictMode>
  );
}
