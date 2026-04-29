const ALLOWED_TAGS = new Set([
    'p', 'div', 'span', 'br', 'a', 'ul', 'ol', 'li',
    'b', 'i', 'em', 'strong', 'u', 's', 'code', 'pre',
    'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
    'hr', 'img', 'figure', 'figcaption', 'small', 'sub', 'sup',
]);

const REMOVE_TAGS = new Set([
    'script', 'style', 'iframe', 'object', 'embed', 'meta', 'link',
    'form', 'input', 'button', 'textarea', 'select', 'option',
    'svg', 'math', 'noscript',
]);

const ALLOWED_ATTRS: Record<string, Set<string>> = {
    a: new Set(['href', 'title']),
    img: new Set(['src', 'alt', 'title', 'width', 'height']),
    td: new Set(['colspan', 'rowspan']),
    th: new Set(['colspan', 'rowspan']),
    table: new Set(['border']),
};

const URL_ATTRS = new Set(['href', 'src']);

function decodeHtmlEntities(value: string): string {
    let decoded = value;

    for (let index = 0; index < 3; index += 1) {
        const textarea = document.createElement('textarea');
        textarea.innerHTML = decoded;
        const next = textarea.value;

        if (next === decoded) break;

        decoded = next;
    }

    return decoded;
}

function isUnsafeUrl(value: string): boolean {
    const normalized = decodeHtmlEntities(value)
        .replace(/[\u0000-\u001F\u007F\s]+/g, '')
        .trim()
        .toLowerCase();
    const schemeEnd = normalized.indexOf(':');

    if (schemeEnd === -1) return false;

    const scheme = normalized.slice(0, schemeEnd);

    if (scheme === 'javascript') return true;
    if (scheme === 'vbscript') return true;
    if (scheme === 'data' && !normalized.startsWith('data:image/')) return true;

    return false;
}

function sanitizeNode(node: Element): void {
    const children = Array.from(node.children);
    for (const child of children) {
        const tag = child.tagName.toLowerCase();

        if (REMOVE_TAGS.has(tag)) {
            child.remove();
            continue;
        }

        if (!ALLOWED_TAGS.has(tag)) {
            // Unwrap unknown tag — keep its children inline.
            sanitizeNode(child);
            while (child.firstChild) node.insertBefore(child.firstChild, child);
            child.remove();
            continue;
        }

        const allowed = ALLOWED_ATTRS[tag] ?? new Set<string>();
        for (const attr of Array.from(child.attributes)) {
            const name = attr.name.toLowerCase();
            if (name.startsWith('on') || name === 'style' || name === 'srcset') {
                child.removeAttribute(attr.name);
                continue;
            }
            if (!allowed.has(name)) {
                child.removeAttribute(attr.name);
                continue;
            }
            if (URL_ATTRS.has(name) && isUnsafeUrl(attr.value)) {
                child.removeAttribute(attr.name);
            }
        }

        if (tag === 'a' && child.hasAttribute('href')) {
            child.setAttribute('rel', 'noopener noreferrer nofollow');
            child.setAttribute('target', '_blank');
        }

        sanitizeNode(child);
    }
}

export function sanitizeHtml(input: string): string {
    if (typeof window === 'undefined' || !input) return '';

    const parser = new DOMParser();
    const doc = parser.parseFromString(`<div data-root>${input}</div>`, 'text/html');
    const root = doc.querySelector('[data-root]');
    if (!root) return '';

    sanitizeNode(root);
    return root.innerHTML;
}
