import { type FormEvent, useState } from "react";
import { requestMagicLink } from "../services/auth.ts";
import { logger } from "../lib/logger.ts";

interface SignInProps {
    onRequested: (email: string) => void;
}

export function SignIn({ onRequested }: SignInProps) {
    const [email, setEmail] = useState("");
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState("");

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        const trimmed = email.trim();
        if (!trimmed || !trimmed.includes("@")) {
            setError("Enter a valid email address.");
            return;
        }
        setLoading(true);
        setError("");
        try {
            await requestMagicLink(trimmed);
            onRequested(trimmed);
        } catch (err) {
            logger.warn("Magic link request failed", err);
            setError("Something went wrong. Try again.");
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="auth-page">
            <div className="auth-card">
                <h2>Sign in</h2>
                <p>Enter your email to receive a one-click sign-in link.</p>
                <form onSubmit={handleSubmit}>
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
            </div>
        </div>
    );
}
