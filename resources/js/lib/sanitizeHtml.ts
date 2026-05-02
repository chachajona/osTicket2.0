import DOMPurify from 'dompurify';

const ALLOWED_TAGS = [
    'p', 'div', 'span', 'br', 'a', 'ul', 'ol', 'li',
    'b', 'i', 'em', 'strong', 'u', 's', 'code', 'pre',
    'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th',
    'hr', 'img', 'figure', 'figcaption', 'small', 'sub', 'sup',
];

const ALLOWED_ATTR = [
    'href', 'title',
    'src', 'alt', 'width', 'height',
    'colspan', 'rowspan',
    'border',
];

function applyLinkPolicy(html: string): string {
    const template = document.createElement('template');
    template.innerHTML = html;

    template.content.querySelectorAll('a[href]').forEach((link) => {
        link.setAttribute('rel', 'noopener noreferrer nofollow');
        link.setAttribute('target', '_blank');
    });

    return template.innerHTML;
}

export function sanitizeHtml(input: string): string {
    if (typeof window === 'undefined' || !input) return '';

    const sanitized = DOMPurify.sanitize(input, {
        ALLOWED_TAGS,
        ALLOWED_ATTR,
        ALLOW_DATA_ATTR: false,
        FORBID_ATTR: ['style', 'srcset'],
        FORBID_TAGS: [
            'script', 'style', 'iframe', 'object', 'embed', 'meta', 'link',
            'form', 'input', 'button', 'textarea', 'select', 'option',
            'svg', 'math', 'noscript',
        ],
    });

    return applyLinkPolicy(sanitized);
}
