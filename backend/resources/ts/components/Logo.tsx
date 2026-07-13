export function FlowMark({ size = 20 }: { size?: number }) {
    return (
        <svg
            width={size}
            height={size}
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
            focusable="false"
        >
            <g stroke="currentColor" strokeWidth={2.2} strokeLinecap="round" strokeLinejoin="round">
                <path d="M7 7 C 11 9 13 15 17 17" />
                <path d="M7 17 C 11 15 13 9 17 7" />
            </g>
            <circle cx="7" cy="7" r="2.4" fill="currentColor" />
            <circle cx="7" cy="17" r="2.4" fill="currentColor" />
            <circle cx="17" cy="7" r="2.4" fill="#f26334" />
            <circle cx="17" cy="17" r="2.4" fill="#f26334" />
        </svg>
    );
}

export function Logo() {
    return (
        <div className="logo-wrap">
            <div className="logo-mark">
                <FlowMark size={19} />
            </div>
            <span>AI Flow</span>
        </div>
    );
}
