export interface Boot {
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
}

export interface KeyHelp {
  url: string;
  placeholder: string;
  regex: string;
}

export interface OnboardingStatus {
  apiKey: {
    ok: boolean;
    masked: string | null;
    source: "constant" | "option" | "none";
    editable: boolean;
    provider: string;
    keyHelp: KeyHelp;
  };
  model: {
    current: string;
    options: Array<{ id: string; label: string }>;
  };
  /** Billing axis (BYO vs hosted Cloud waitlist). */
  provider: {
    current: "byo" | "cloud-waitlist";
    cloudAvailable: boolean;
    cloudWaitlistOpen: boolean;
  };
  /** Which LLM provider runs the chat. */
  llmProvider: {
    current: string;
    locked: boolean;
    options: Array<{ id: string; label: string; keyHelp: KeyHelp }>;
  };
  permissions: {
    ok: boolean;
    has: string[];
    required: string[];
    role: string;
  };
  wc: {
    active: boolean;
    version: string | null;
    order_count: number | null;
    install_url: string;
  };
  analytics: {
    detected: Array<{ id: string; name: string }>;
    recommended: Array<{ id: string; name: string; install_url: string }>;
  };
  backends: Array<{
    kind: string;
    description: string;
    fields: string[];
    source: "core" | "site";
    requiredCap: string;
    userCanEdit: boolean;
    siteDisabled: boolean;
  }>;
  integrations: {
    cf_purge: { configured: boolean; snippet: string };
    git_sync: { configured: boolean; snippet: string };
  };
  disabled_kinds: string[];
  isAdmin: boolean;
  user: {
    id: number;
    display_name: string;
    first_name: string;
    locale: string;
  };
  site: {
    name: string;
    url: string;
    admin: string;
  };
}
