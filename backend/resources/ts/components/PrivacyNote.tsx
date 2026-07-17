export function PrivacyNote() {
    return (
        <div className="privacy-note">
            <h4>How your API keys are handled</h4>
            <ul>
                <li>Your API keys are encrypted before being stored.</li>
                <li>
                    They are decrypted only when an AI request is sent to your selected provider.
                </li>
                <li>
                    Keys are never shown again after saving — you can replace or delete them at any
                    time.
                </li>
                <li>
                    When you run a workflow, relevant input is sent to the AI provider you choose.
                    Their privacy and data retention terms also apply.
                </li>
                <li>
                    Do not submit secrets or confidential code unless you are authorized to share it
                    with the selected provider.
                </li>
            </ul>
            <p className="privacy-note-disclaimer">
                You can <a href="/user">delete your account</a> at any time, which removes all your
                stored keys and run history. For provider-specific privacy policies, refer to{" "}
                <a
                    href="https://openai.com/policies/privacy-policy"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    OpenAI
                </a>
                ,{" "}
                <a
                    href="https://www.anthropic.com/privacy"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Anthropic
                </a>
                ,{" "}
                <a
                    href="https://policies.google.com/privacy"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Google
                </a>
                , and{" "}
                <a href="https://openrouter.ai/privacy" target="_blank" rel="noopener noreferrer">
                    OpenRouter
                </a>
                .
            </p>
        </div>
    );
}
