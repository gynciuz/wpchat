import { useEffect, useRef, useState } from "react";
import { AnimatePresence, motion } from "motion/react";
import { Mic, MicOff, MicVocal } from "lucide-react";
import { Button } from "@/components/ui/button";

/**
 * Minimal browser SpeechRecognition typing — supports Safari/Chrome on
 * iOS + macOS. Duplicated from Chat.tsx so this component stays self-
 * contained.
 */
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

type MicPermission = "granted" | "denied" | "prompt" | "unsupported" | "unknown";

interface MicButtonProps {
  speechLang: string;
  busy: boolean;
  onTranscript: (text: string) => void;
  /** Receives short, auto-dismissing toast messages. */
  onError: (message: string) => void;
  /** Tracks listening state up to the parent so the input placeholder etc. can react. */
  onListeningChange?: (listening: boolean) => void;
}

export function MicButton({ speechLang, busy, onTranscript, onError, onListeningChange }: MicButtonProps) {
  const [permission, setPermission] = useState<MicPermission>("unknown");
  const [listening, setListening] = useState(false);
  const recRef = useRef<SpeechRecognition | null>(null);

  // Query the Permissions API on mount. Safari on iOS doesn't expose
  // microphone in navigator.permissions.query, so we fall through to
  // "unknown" and let the first start() reveal the real state via
  // error events.
  useEffect(() => {
    const perms = (navigator as unknown as { permissions?: { query: (q: { name: string }) => Promise<PermissionStatus> } }).permissions;
    if (!perms?.query) {
      setPermission("unknown");
      return;
    }
    perms
      .query({ name: "microphone" as PermissionName })
      .then((status) => {
        setPermission(status.state as MicPermission);
        status.onchange = () => setPermission(status.state as MicPermission);
      })
      .catch(() => {
        setPermission("unknown");
      });
  }, []);

  useEffect(() => {
    onListeningChange?.(listening);
  }, [listening, onListeningChange]);

  function stop() {
    recRef.current?.stop();
    setListening(false);
  }

  function start() {
    if (busy) return;
    const rec = getRecognition(speechLang);
    if (!rec) {
      setPermission("unsupported");
      onError(unsupportedMessage());
      return;
    }
    recRef.current = rec;
    setListening(true);

    rec.onresult = (ev) => {
      let txt = "";
      for (let i = 0; i < ev.results.length; i++) {
        txt += ev.results[i][0].transcript;
      }
      onTranscript(txt);
    };
    rec.onend = () => setListening(false);
    rec.onerror = (ev) => {
      setListening(false);
      if (ev.error === "no-speech" || ev.error === "aborted") return;
      if (ev.error === "not-allowed" || ev.error === "service-not-allowed") {
        setPermission("denied");
        onError(deniedMessage());
        return;
      }
      onError(`Voice error: ${ev.error}`);
    };
    rec.start();
  }

  // Don't render the button at all if we know mic is unavailable.
  // The user gets a "Voice off" hint in the footer instead so the icon
  // doesn't taunt them on every send.
  if (permission === "denied" || permission === "unsupported") {
    return null;
  }

  return (
    <Button
      type="button"
      variant={listening ? "destructive" : "secondary"}
      size="icon"
      onClick={listening ? stop : start}
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
            <MicVocal className="size-4" />
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
  );
}

/**
 * "Voice off" hint shown in the footer when the mic is unavailable.
 * Tells the user how to re-enable it. Compact, non-blocking.
 */
export function MicStatusHint({ /* nothing — derives from MicButton's own check */ }: object) {
  const [permission, setPermission] = useState<MicPermission>("unknown");

  useEffect(() => {
    const perms = (navigator as unknown as { permissions?: { query: (q: { name: string }) => Promise<PermissionStatus> } }).permissions;
    if (!perms?.query) return;
    perms.query({ name: "microphone" as PermissionName }).then((s) => {
      setPermission(s.state as MicPermission);
      s.onchange = () => setPermission(s.state as MicPermission);
    }).catch(() => {});
  }, []);

  if (permission !== "denied" && permission !== "unsupported") return null;
  return (
    <span className="inline-flex items-center gap-1 text-[10.5px] text-muted-foreground">
      <MicOff className="size-3" />
      {permission === "unsupported" ? "be balso įvesties" : "balsas išjungtas"}
    </span>
  );
}

function isIOS(): boolean {
  const ua = navigator.userAgent;
  return /iPad|iPhone|iPod/.test(ua) || (ua.includes("Mac") && "ontouchend" in document);
}

function isStandalonePWA(): boolean {
  return (
    window.matchMedia?.("(display-mode: standalone)").matches ||
    (navigator as unknown as { standalone?: boolean }).standalone === true
  );
}

function deniedMessage(): string {
  if (isIOS()) {
    return isStandalonePWA()
      ? "Mikrofonas išjungtas. iOS Settings → WPChat → Microphone → Allow."
      : "Mikrofonas išjungtas. Bakstelėkite „aA\" adreso juostoje → Website Settings → Microphone → Allow.";
  }
  return "Mikrofono prieiga atmesta. Spustelėkite spynos piktogramą adreso juostoje ir įjunkite mikrofoną.";
}

function unsupportedMessage(): string {
  return "Balso įvestis nepalaikoma šioje naršyklėje.";
}
