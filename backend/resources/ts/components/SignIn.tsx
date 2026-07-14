import { type FormEvent, useState } from "react";
import {
    loginWithPassword,
    registerWithPassword,
    requestMagicLink,
    type User,
} from "../services/auth.ts";
import { logger } from "../lib/logger.ts";

type AuthMode = "sign-in" | "sign-up" | "magic";

interface SignInProps {
    onRequested: (email: string) => void;
    onAuthenticated: (user: User) => void;
}

function firstValidationMessage(err: unknown): string {
    if (!(err instanceof Error)) {
        return "Something went wrong. Try again.";
    }
    const match = err.message.match(/"([^"]+)"/);
    return match?.[1] ?? err.message;
}

export function SignIn({ onRequested, onAuthenticated }: SignInProps) {
    const [mode, setMode] = useState<AuthMode>("sign-in");
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const [passwordConfirmation, setPasswordConfirmation] = useState("");
    const [name, setName] = useState("");
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");

    const trimmedEmail = email.trim();

    const handleMagicSubmit = async (e: FormEvent) => {
        e.preventDefault();
        if (!trimmedEmail || !trimmedEmail.includes("@")) {
            setError("Enter a valid email address.");
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
            return;
        }
        if (!password) {
            setError("Enter your password.");
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
            return;
        }
        if (password.length < 8) {
            setError("Password must be at least 8 characters.");
            return;
        }
        if (password !== passwordConfirmation) {
            setError("Passwords do not match.");
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

    return (
        <div className="auth-page">
            <div className="auth-card">
                <h2>{mode === "sign-up" ? "Create account" : "Sign in"}</h2>
                <div className="auth-tabs" role="tablist">
                    <button
                        type="button"
                        role="tab"
                        aria-selected={mode === "sign-in"}
                        className={mode === "sign-in" ? "auth-tab active" : "auth-tab"}
                        onClick={() => {
                            setMode("sign-in");
                            setError("");
                        }}
                    >
                        Password
                    </button>
                    <button
                        type="button"
                        role="tab"
                        aria-selected={mode === "sign-up"}
                        className={mode === "sign-up" ? "auth-tab active" : "auth-tab"}
                        onClick={() => {
                            setMode("sign-up");
                            setError("");
                        }}
                    >
                        Sign up
                    </button>
                    <button
                        type="button"
                        role="tab"
                        aria-selected={mode === "magic"}
                        className={mode === "magic" ? "auth-tab active" : "auth-tab"}
                        onClick={() => {
                            setMode("magic");
                            setError("");
                        }}
                    >
                        Email link
                    </button>
                </div>

                {mode === "magic" && (
                    <>
                        <p>Enter your email to receive a one-click sign-in link.</p>
                        <form onSubmit={handleMagicSubmit}>
                            <input
                                type="email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                placeholder="you@example.com"
                                required
                                autoFocus
                                disabled={loading}
                            />
                            {error && <p className="auth-error">{error}</p>}
                            <button type="submit" disabled={loading}>
                                {loading ? "Sending…" : "Send sign-in link"}
                            </button>
                        </form>
                    </>
                )}

                {mode === "sign-in" && (
                    <>
                        <p>Sign in with your email and password.</p>
                        <form onSubmit={handlePasswordSignIn}>
                            <input
                                type="email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                placeholder="you@example.com"
                                required
                                autoFocus
                                disabled={loading}
                            />
                            <input
                                type="password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                placeholder="Password"
                                required
                                autoComplete="current-password"
                                disabled={loading}
                            />
                            {error && <p className="auth-error">{error}</p>}
                            <button type="submit" disabled={loading}>
                                {loading ? "Signing in…" : "Sign in"}
                            </button>
                        </form>
                    </>
                )}

                {mode === "sign-up" && (
                    <>
                        <p>
                            Create an account with email and password. Magic-link-only users can use
                            the same email to set a password.
                        </p>
                        <form onSubmit={handleSignUp}>
                            <input
                                type="text"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                placeholder="Name (optional)"
                                disabled={loading}
                            />
                            <input
                                type="email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                placeholder="you@example.com"
                                required
                                disabled={loading}
                            />
                            <input
                                type="password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                placeholder="Password"
                                required
                                autoComplete="new-password"
                                disabled={loading}
                            />
                            <input
                                type="password"
                                value={passwordConfirmation}
                                onChange={(e) => setPasswordConfirmation(e.target.value)}
                                placeholder="Confirm password"
                                required
                                autoComplete="new-password"
                                disabled={loading}
                            />
                            {error && <p className="auth-error">{error}</p>}
                            <button type="submit" disabled={loading}>
                                {loading ? "Creating…" : "Create account"}
                            </button>
                        </form>
                    </>
                )}
            </div>
        </div>
    );
}
