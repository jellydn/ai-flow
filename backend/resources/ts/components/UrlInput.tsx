import { GitFork, X } from "lucide-react";

interface UrlInputProps {
    url: string;
    setUrl: (url: string) => void;
    error: string;
    setError: (error: string) => void;
    launch: () => void;
    isLaunching: boolean;
}

export function UrlInput({ url, setUrl, error, setError, launch, isLaunching }: UrlInputProps) {
    return (
        <>
            <div className={`url-box ${error ? "has-error" : ""}`}>
                <GitFork size={22} />
                <input
                    value={url}
                    onChange={(event) => {
                        setUrl(event.target.value);
                        setError("");
                    }}
                    onKeyDown={(event) => event.key === "Enter" && !isLaunching && launch()}
                    placeholder="https://github.com/owner/repository/pull/42"
                    aria-label="GitHub URL"
                />
                {url && (
                    <button
                        type="button"
                        className="clear-input"
                        onClick={() => setUrl("")}
                        aria-label="Clear URL"
                    >
                        <X size={16} />
                    </button>
                )}
            </div>
            {error && <p className="input-error">{error}</p>}
        </>
    );
}
