import ReactMarkdown from "react-markdown";
import remarkGfm from "remark-gfm";

interface MarkdownBodyProps {
    children: string;
    className?: string;
}

export function MarkdownBody({ children, className }: MarkdownBodyProps) {
    const merged = ["markdown-body", className].filter(Boolean).join(" ");
    return (
        <div className={merged}>
            <ReactMarkdown remarkPlugins={[remarkGfm]}>{children}</ReactMarkdown>
        </div>
    );
}
