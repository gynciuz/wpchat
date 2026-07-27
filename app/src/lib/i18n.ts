/**
 * Lightweight UI localization for the chat SPA.
 *
 * Two jobs:
 *  1. Detect the language the operator is actually conversing in (from the
 *     messages on screen) so the UI chrome — "Thinking…", the tool-call
 *     disclosure, the input placeholder, Confirm/Cancel, etc. — follows the
 *     conversation, not just the WordPress site locale. A shop admin whose
 *     WordPress is in English but who types in Lithuanian should see a
 *     Lithuanian UI once they start chatting.
 *  2. Hold the strings that were previously hardcoded in English (or, worse,
 *     hardcoded in one non-English language) so every visible label has a
 *     translation for each supported language.
 *
 * Supported languages mirror the rest of the product (LT/RU/PL/EN are the
 * core set; es/fr/pt/de/hi/zh round out the labels that already existed).
 */

export type Lang =
  | "en"
  | "es"
  | "fr"
  | "pt"
  | "hi"
  | "zh"
  | "de"
  | "lt"
  | "ru"
  | "pl";

const SUPPORTED: Lang[] = ["en", "es", "fr", "pt", "hi", "zh", "de", "lt", "ru", "pl"];

/**
 * Normalize a WordPress-style locale ("lt_LT", "pt-BR", "en") to one of our
 * supported short codes, defaulting to English.
 */
export function normalizeLang(locale?: string | null): Lang {
  if (!locale) return "en";
  const short = locale.slice(0, 2).toLowerCase() as Lang;
  return SUPPORTED.includes(short) ? short : "en";
}

/**
 * Best-effort language guess from a single piece of text, using script and
 * diacritic signals. Returns null when the text carries no signal (e.g. bare
 * ASCII like "taip" or an order number) so the caller can fall back.
 *
 * Ordering matters: the most specific, least ambiguous markers are tested
 * first. Within our supported set these markers are effectively unique
 * (e.g. ė/į/ū/ų only occur in Lithuanian; ł only in Polish).
 */
export function detectLang(text: string): Lang | null {
  if (!text) return null;

  // Script-based signals — strong and unambiguous.
  if (/[Ѐ-ӿ]/.test(text)) return "ru"; // Cyrillic
  if (/[一-鿿]/.test(text)) return "zh"; // CJK unified ideographs
  if (/[ऀ-ॿ]/.test(text)) return "hi"; // Devanagari

  const t = text.toLowerCase();

  // Latin-script languages, distinguished by characteristic letters.
  if (/[ėįūų]/.test(t)) return "lt"; // Lithuanian-only vowels
  if (/ł/.test(t)) return "pl"; // Polish-only
  if (/ß|[äöü]/.test(t)) return "de"; // German
  if (/[ñ¿¡]/.test(t)) return "es"; // Spanish
  if (/[ãõ]/.test(t)) return "pt"; // Portuguese nasals
  if (/[àâçèêëîïôûùœ]/.test(t)) return "fr"; // French
  // Weaker, still-unique-within-our-set fallbacks.
  if (/[čšž]/.test(t)) return "lt"; // shared Baltic/Slavic marks; LT within our set
  if (/[ćńśźż]/.test(t)) return "pl";
  if (/[áíó]/.test(t)) return "es";

  return null;
}

/**
 * Resolve the language of an on-screen conversation. Scans the message texts
 * (both the operator's and the assistant's replies — the assistant answers in
 * the operator's language, so its longer, diacritic-rich prose is a reliable
 * signal) and returns the first confident guess, or null if none.
 */
export function detectConversationLang(texts: string[]): Lang | null {
  if (!texts.length) return null;
  // Concatenate (newest first so a recent language switch wins) and cap the
  // length so detection stays cheap on long histories.
  const blob = texts.slice().reverse().join("\n").slice(0, 4000);
  return detectLang(blob);
}

interface UiStrings {
  thinking: string;
  waiting: string;
  typePlaceholder: string;
  uploading: string;
  removeAttachment: string;
  sendMessage: string;
  attachImage: string;
  openHistory: string;
}

const UI: Record<Lang, UiStrings> = {
  en: {
    thinking: "Thinking…",
    waiting: "Waiting for assistant…",
    typePlaceholder: "Type…",
    uploading: "Uploading…",
    removeAttachment: "Remove attachment",
    sendMessage: "Send message",
    attachImage: "Attach image",
    openHistory: "Chat history",
  },
  es: {
    thinking: "Pensando…",
    waiting: "Esperando al asistente…",
    typePlaceholder: "Escribe…",
    uploading: "Subiendo…",
    removeAttachment: "Quitar adjunto",
    sendMessage: "Enviar mensaje",
    attachImage: "Adjuntar imagen",
    openHistory: "Historial de chat",
  },
  fr: {
    thinking: "Réflexion…",
    waiting: "En attente de l'assistant…",
    typePlaceholder: "Écrivez…",
    uploading: "Téléversement…",
    removeAttachment: "Supprimer la pièce jointe",
    sendMessage: "Envoyer le message",
    attachImage: "Joindre une image",
    openHistory: "Historique des discussions",
  },
  pt: {
    thinking: "Pensando…",
    waiting: "Aguardando o assistente…",
    typePlaceholder: "Escreva…",
    uploading: "Enviando…",
    removeAttachment: "Remover anexo",
    sendMessage: "Enviar mensagem",
    attachImage: "Anexar imagem",
    openHistory: "Histórico de conversas",
  },
  hi: {
    thinking: "सोच रहा हूँ…",
    waiting: "सहायक की प्रतीक्षा…",
    typePlaceholder: "लिखें…",
    uploading: "अपलोड हो रहा है…",
    removeAttachment: "अटैचमेंट हटाएँ",
    sendMessage: "संदेश भेजें",
    attachImage: "छवि संलग्न करें",
    openHistory: "चैट इतिहास",
  },
  zh: {
    thinking: "思考中…",
    waiting: "等待助手…",
    typePlaceholder: "输入…",
    uploading: "上传中…",
    removeAttachment: "移除附件",
    sendMessage: "发送消息",
    attachImage: "附加图片",
    openHistory: "聊天记录",
  },
  de: {
    thinking: "Denkt nach…",
    waiting: "Warte auf den Assistenten…",
    typePlaceholder: "Schreiben…",
    uploading: "Wird hochgeladen…",
    removeAttachment: "Anhang entfernen",
    sendMessage: "Nachricht senden",
    attachImage: "Bild anhängen",
    openHistory: "Chatverlauf",
  },
  lt: {
    thinking: "Galvoju…",
    waiting: "Laukiama asistento…",
    typePlaceholder: "Rašykite…",
    uploading: "Įkeliama…",
    removeAttachment: "Pašalinti priedą",
    sendMessage: "Siųsti žinutę",
    attachImage: "Pridėti nuotrauką",
    openHistory: "Pokalbių istorija",
  },
  ru: {
    thinking: "Думаю…",
    waiting: "Ожидание ассистента…",
    typePlaceholder: "Введите…",
    uploading: "Загрузка…",
    removeAttachment: "Удалить вложение",
    sendMessage: "Отправить сообщение",
    attachImage: "Прикрепить изображение",
    openHistory: "История чата",
  },
  pl: {
    thinking: "Myślę…",
    waiting: "Czekam na asystenta…",
    typePlaceholder: "Napisz…",
    uploading: "Przesyłanie…",
    removeAttachment: "Usuń załącznik",
    sendMessage: "Wyślij wiadomość",
    attachImage: "Załącz obraz",
    openHistory: "Historia czatu",
  },
};

/** UI strings for a language (falls back to English for unknown codes). */
export function ui(lang?: Lang): UiStrings {
  return UI[lang ?? "en"] ?? UI.en;
}

/**
 * "N tool calls" with per-language pluralization. Full CLDR plural rules for
 * the core Baltic/Slavic languages (lt/ru/pl), simple one-vs-many for the
 * rest — good enough for a small disclosure label.
 */
export function toolCallsLabel(lang: Lang, n: number): string {
  const abs = Math.abs(n);
  const mod10 = abs % 10;
  const mod100 = abs % 100;

  switch (lang) {
    case "lt": {
      // 1, 21, 31… (but not 11) → singular; 2–9, 22–29… (not 11–19) → plural;
      // 0, 10–19, 20, 30… → genitive plural.
      if (mod10 === 1 && mod100 !== 11) return `${n} įrankio iškvietimas`;
      if (mod10 >= 2 && mod10 <= 9 && !(mod100 >= 11 && mod100 <= 19))
        return `${n} įrankių iškvietimai`;
      return `${n} įrankių iškvietimų`;
    }
    case "ru": {
      if (mod10 === 1 && mod100 !== 11) return `${n} вызов инструмента`;
      if (mod10 >= 2 && mod10 <= 4 && !(mod100 >= 12 && mod100 <= 14))
        return `${n} вызова инструмента`;
      return `${n} вызовов инструментов`;
    }
    case "pl": {
      if (n === 1) return `${n} wywołanie narzędzia`;
      if (mod10 >= 2 && mod10 <= 4 && !(mod100 >= 12 && mod100 <= 14))
        return `${n} wywołania narzędzi`;
      return `${n} wywołań narzędzi`;
    }
    case "es":
      return `${n} ${n === 1 ? "llamada a herramienta" : "llamadas a herramientas"}`;
    case "fr":
      return `${n} ${n === 1 ? "appel d'outil" : "appels d'outil"}`;
    case "pt":
      return `${n} ${n === 1 ? "chamada de ferramenta" : "chamadas de ferramenta"}`;
    case "de":
      return `${n} ${n === 1 ? "Tool-Aufruf" : "Tool-Aufrufe"}`;
    case "hi":
      return `${n} टूल कॉल`;
    case "zh":
      return `${n} 次工具调用`;
    default:
      return `${n} tool call${n === 1 ? "" : "s"}`;
  }
}
