export function scrollToSelector(selector: string): void {
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            document.querySelector(selector)?.scrollIntoView({ behavior: 'smooth' });
        });
    });
}
