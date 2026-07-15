import {
    type FormEvent,
    type KeyboardEvent,
    type RefObject,
    useEffect,
    useId,
    useRef,
    useState,
} from "react";
import {
    loginWithPassword,
    registerWithPassword,
    requestMagicLink,
    type User,
} from "../services/auth.ts";
import { logger } from "../lib/logger.ts";

type AuthMode = "sign-in" | "sign-up" | "magic";

const AUTH_TAB_MODES: AuthMode[] = ["sign-in", "sign-up", "magic"];

interface SignInProps {
    onRequested: (email: string) => void;
    onAuthenticated: (user: User) => void;
}

function firstValidationMessage(err: unknown): string {
    if (!(err instanceof Error)) {
        return "Something went wrong. Try again.";
    }
    return err.message;
}

function AuthError({ message }: { message: string }) {
    return (
        <p className="auth-error" role="alert" aria-live="polite">
            {message}
        </p>
    );
}

export function SignIn({ onRequested, onAuthenticated }: SignInProps) {
    const baseId = useId();
    const [mode, setMode] = useState<AuthMode>("sign-in");
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const [passwordConfirmation, setPasswordConfirmation] = useState("");
    const [name, setName] = useState("");
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");

    const trimmedEmail = email.trim();
    const emailId = `${baseId}-email`;
    const passwordId = `${baseId}-password`;
    const passwordConfirmId = `${baseId}-password-confirm`;
    const nameId = `${baseId}-name`;

    const emailRef = useRef<HTMLInputElement>(null);
    const passwordRef = useRef<HTMLInputElement>(null);
    const passwordConfirmRef = useRef<HTMLInputElement>(null);

    const switchMode = (next: AuthMode) => {
        setMode(next);
        setError("");
        const params = new URLSearchParams(window.location.search);
        if (next === "sign-in") {
            params.delete("auth");
        } else {
            params.set("auth", next === "magic" ? "link" : "signup");
        }
        const qs = params.toString();
        const path = `${window.location.pathname}${qs ? `?${qs}` : ""}${window.location.hash}`;
        window.history.replaceState({}, "", path);
    };

    useEffect(() => {
        const auth = new URLSearchParams(window.location.search).get("auth");
        if (auth === "link" || auth === "magic") {
            setMode("magic");
        } else if (auth === "signup" || auth === "sign-up") {
            setMode("sign-up");
        }
    }, []);

    const focusField = (ref: RefObject<HTMLInputElement | null>) => {
        requestAnimationFrame(() => ref.current?.focus());
    };

    const handleMagicSubmit = async (e: FormEvent) => {
        e.preventDefault();
        if (!trimmedEmail || !trimmedEmail.includes("@")) {
            setError("Enter a valid email address.");
            focusField(emailRef);
            return;
        }
        setLoading(true);
        setError("");
        try {
            await requestMagicLink(trimmedEmail);
            onRequested(trimmedEmail);
        } catch (err) {
            logger.warn("Magic link request failed", err);
            setError("Something went wrong. Try again.");
        } finally {
            setLoading(false);
        }
    };

    const handlePasswordSignIn = async (e: FormEvent) => {
        e.preventDefault();
        if (!trimmedEmail || !trimmedEmail.includes("@")) {
            setError("Enter a valid email address.");
            focusField(emailRef);
            return;
        }
        if (!password) {
            setError("Enter your password.");
            focusField(passwordRef);
            return;
        }
        setLoading(true);
        setError("");
        try {
            const user = await loginWithPassword(trimmedEmail, password);
            onAuthenticated(user);
        } catch (err) {
            logger.warn("Password sign-in failed", err);
            setError(firstValidationMessage(err));
        } finally {
            setLoading(false);
        }
    };

    const handleSignUp = async (e: FormEvent) => {
        e.preventDefault();
        if (!trimmedEmail || !trimmedEmail.includes("@")) {
            setError("Enter a valid email address.");
            focusField(emailRef);
            return;
        }
        if (password.length < 8) {
            setError("Password must be at least 8 characters.");
            focusField(passwordRef);
            return;
        }
        if (password !== passwordConfirmation) {
            setError("Passwords do not match.");
            focusField(passwordConfirmRef);
            return;
        }
        setLoading(true);
        setError("");
        try {
            const user = await registerWithPassword({
                email: trimmedEmail,
                password,
                password_confirmation: passwordConfirmation,
                name: name.trim() || undefined,
            });
            onAuthenticated(user);
        } catch (err) {
            logger.warn("Sign-up failed", err);
            setError(firstValidationMessage(err));
        } finally {
            setLoading(false);
        }
    };

    const tabIds = {
        "sign-in": `${baseId}-tab-sign-in`,
        "sign-up": `${baseId}-tab-sign-up`,
        magic: `${baseId}-tab-magic`,
    };
    const panelIds = {
        "sign-in": `${baseId}-panel-sign-in`,
        "sign-up": `${baseId}-panel-sign-up`,
        magic: `${baseId}-panel-magic`,
    };

    const handleTabListKeyDown = (e: KeyboardEvent<HTMLDivElement>) => {
        const idx = AUTH_TAB_MODES.indexOf(mode);
        if (idx < 0) {
            return;
        }

        let next: AuthMode | null = null;
        if (e.key === "ArrowRight") {
            next = AUTH_TAB_MODES[(idx + 1) % AUTH_TAB_MODES.length];
        } else if (e.key === "ArrowLeft") {
            next = AUTH_TAB_MODES[(idx - 1 + AUTH_TAB_MODES.length) % AUTH_TAB_MODES.length];
        } else if (e.key === "Home") {
            next = AUTH_TAB_MODES[0];
        } else if (e.key === "End") {
            next = AUTH_TAB_MODES[AUTH_TAB_MODES.length - 1];
        }

        if (next && next !== mode) {
            e.preventDefault();
            switchMode(next);
            requestAnimationFrame(() => document.getElementById(tabIds[next])?.focus());
        }
    };

    return (
        <main className="auth-page">
            <div className="auth-card">
                <p className="auth-kicker">Account</p>
                <h2 id={`${baseId}-heading`}>
                    {mode === "sign-up"
                        ? "Create account"
                        : mode === "magic"
                          ? "Email link"
                          : "Sign in"}
                </h2>

                <div
                    className="auth-tabs"
                    role="tablist"
                    aria-labelledby={`${baseId}-heading`}
                    onKeyDown={handleTabListKeyDown}
                >
                    <button
                        type="button"
                        role="tab"
                        id={tabIds["sign-in"]}
                        aria-selected={mode === "sign-in"}
                        aria-controls={panelIds["sign-in"]}
                        tabIndex={mode === "sign-in" ? 0 : -1}
                        className={mode === "sign-in" ? "auth-tab active" : "auth-tab"}
                        onClick={() => switchMode("sign-in")}
                    >
                        Password
                    </button>
                    <button
                        type="button"
                        role="tab"
                        id={tabIds["sign-up"]}
                        aria-selected={mode === "sign-up"}
                        aria-controls={panelIds["sign-up"]}
                        tabIndex={mode === "sign-up" ? 0 : -1}
                        className={mode === "sign-up" ? "auth-tab active" : "auth-tab"}
                        onClick={() => switchMode("sign-up")}
                    >
                        Sign up
                    </button>
                    <button
                        type="button"
                        role="tab"
                        id={tabIds.magic}
                        aria-selected={mode === "magic"}
                        aria-controls={panelIds.magic}
                        tabIndex={mode === "magic" ? 0 : -1}
                        className={mode === "magic" ? "auth-tab active" : "auth-tab"}
                        onClick={() => switchMode("magic")}
                    >
                        Email link
                    </button>
                </div>

                {mode === "magic" && (
                    <div role="tabpanel" id={panelIds.magic} aria-labelledby={tabIds.magic}>
                        <p>We’ll email you a one-click sign-in link. No password required.</p>
                        <form onSubmit={handleMagicSubmit}>
                            <label className="auth-field" htmlFor={emailId}>
                                <span>Email</span>
                                <input
                                    ref={emailRef}
                                    id={emailId}
                                    name="email"
                                    type="email"
                                    inputMode="email"
                                    autoComplete="email"
                                    spellCheck={false}
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    placeholder="you@example.com…"
                                    required
                                    disabled={loading}
                                    aria-invalid={error ? true : undefined}
                                />
                            </label>
                            {error && <AuthError message={error} />}
                            <button type="submit" disabled={loading}>
                                {loading ? "Sending…" : "Send sign-in link"}
                            </button>
                        </form>
                    </div>
                )}

                {mode === "sign-in" && (
                    <div
                        role="tabpanel"
                        id={panelIds["sign-in"]}
                        aria-labelledby={tabIds["sign-in"]}
                    >
                        <p>Use the email and password for your account.</p>
                        <form onSubmit={handlePasswordSignIn}>
                            <label className="auth-field" htmlFor={emailId}>
                                <span>Email</span>
                                <input
                                    ref={emailRef}
                                    id={emailId}
                                    name="email"
                                    type="email"
                                    inputMode="email"
                                    autoComplete="username email"
                                    spellCheck={false}
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    placeholder="you@example.com…"
                                    required
                                    disabled={loading}
                                    aria-invalid={error ? true : undefined}
                                />
                            </label>
                            <label className="auth-field" htmlFor={passwordId}>
                                <span>Password</span>
                                <input
                                    ref={passwordRef}
                                    id={passwordId}
                                    name="password"
                                    type="password"
                                    autoComplete="current-password"
                                    spellCheck={false}
                                    value={password}
                                    onChange={(e) => setPassword(e.target.value)}
                                    placeholder="Your password…"
                                    required
                                    disabled={loading}
                                    aria-invalid={error ? true : undefined}
                                />
                            </label>
                            {error && <AuthError message={error} />}
                            <button type="submit" disabled={loading}>
                                {loading ? "Signing in…" : "Sign in"}
                            </button>
                        </form>
                        <p className="auth-alt">
                            Prefer no password?{" "}
                            <button
                                type="button"
                                className="auth-link"
                                onClick={() => switchMode("magic")}
                            >
                                Use email link
                            </button>
                        </p>
                    </div>
                )}

                {mode === "sign-up" && (
                    <div
                        role="tabpanel"
                        id={panelIds["sign-up"]}
                        aria-labelledby={tabIds["sign-up"]}
                    >
                        <p>
                            New here? Register with email and password. If you already use email
                            link sign-in, keep using that—this form is for new accounts only.
                        </p>
                        <form onSubmit={handleSignUp}>
                            <label className="auth-field" htmlFor={nameId}>
                                <span>Name (optional)</span>
                                <input
                                    id={nameId}
                                    name="name"
                                    type="text"
                                    autoComplete="name"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    placeholder="Your name…"
                                    disabled={loading}
                                />
                            </label>
                            <label className="auth-field" htmlFor={emailId}>
                                <span>Email</span>
                                <input
                                    ref={emailRef}
                                    id={emailId}
                                    name="email"
                                    type="email"
                                    inputMode="email"
                                    autoComplete="email"
                                    spellCheck={false}
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    placeholder="you@example.com…"
                                    required
                                    disabled={loading}
                                    aria-invalid={error ? true : undefined}
                                />
                            </label>
                            <label className="auth-field" htmlFor={passwordId}>
                                <span>Password</span>
                                <input
                                    ref={passwordRef}
                                    id={passwordId}
                                    name="password"
                                    type="password"
                                    autoComplete="new-password"
                                    spellCheck={false}
                                    value={password}
                                    onChange={(e) => setPassword(e.target.value)}
                                    placeholder="At least 8 characters…"
                                    required
                                    disabled={loading}
                                    aria-invalid={error ? true : undefined}
                                />
                            </label>
                            <label className="auth-field" htmlFor={passwordConfirmId}>
                                <span>Confirm password</span>
                                <input
                                    ref={passwordConfirmRef}
                                    id={passwordConfirmId}
                                    name="password_confirmation"
                                    type="password"
                                    autoComplete="new-password"
                                    spellCheck={false}
                                    value={passwordConfirmation}
                                    onChange={(e) => setPasswordConfirmation(e.target.value)}
                                    placeholder="Repeat password…"
                                    required
                                    disabled={loading}
                                    aria-invalid={error ? true : undefined}
                                />
                            </label>
                            {error && <AuthError message={error} />}
                            <button type="submit" disabled={loading}>
                                {loading ? "Creating…" : "Create account"}
                            </button>
                        </form>
                        <p className="auth-alt">
                            Already have an account?{" "}
                            <button
                                type="button"
                                className="auth-link"
                                onClick={() => switchMode("sign-in")}
                            >
                                Sign in
                            </button>
                        </p>
                    </div>
                )}
            </div>
        </main>
    );
}
