<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

class KnowledgeArticleContentFormatter
{
    /**
     * @var list<string>
     */
    private const ALLOWED_ELEMENTS = [
        'a',
        'b',
        'blockquote',
        'br',
        'code',
        'div',
        'em',
        'h1',
        'h2',
        'h3',
        'hr',
        'i',
        'li',
        'ol',
        'p',
        'pre',
        's',
        'strike',
        'strong',
        'u',
        'ul',
    ];

    /**
     * @var list<string>
     */
    private const DROP_ELEMENTS = [
        'iframe',
        'object',
        'script',
        'style',
        'svg',
        'template',
    ];

    public function format(string $content): string
    {
        $content = trim($content);

        if ($content === '') {
            return '';
        }

        if (! preg_match('/<\/?[a-z][^>]*>/i', $content)) {
            $paragraphs = preg_split('/\R{2,}/u', $content) ?: [$content];
            $content = implode('', array_map(
                fn (string $paragraph): string => '<p>'.nl2br(e(trim($paragraph)), false).'</p>',
                $paragraphs,
            ));
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrorSetting = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="article-content-root">'.$content.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorSetting);

        $root = $document->getElementById('article-content-root');

        if (! $root instanceof DOMElement) {
            return '';
        }

        $this->sanitizeChildren($root);

        $formattedContent = '';

        foreach ($root->childNodes as $child) {
            $formattedContent .= $document->saveHTML($child);
        }

        return $formattedContent;
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        for ($child = $parent->firstChild; $child !== null;) {
            $nextChild = $child->nextSibling;

            if ($child instanceof DOMText) {
                $child = $nextChild;

                continue;
            }

            if (! $child instanceof DOMElement) {
                $parent->removeChild($child);
                $child = $nextChild;

                continue;
            }

            $tagName = strtolower($child->tagName);

            if (in_array($tagName, self::DROP_ELEMENTS, true)) {
                $parent->removeChild($child);
                $child = $nextChild;

                continue;
            }

            $this->sanitizeChildren($child);

            if (! in_array($tagName, self::ALLOWED_ELEMENTS, true)) {
                while ($child->firstChild !== null) {
                    $parent->insertBefore($child->firstChild, $child);
                }

                $parent->removeChild($child);
                $child = $nextChild;

                continue;
            }

            $this->sanitizeAttributes($child, $tagName);
            $child = $nextChild;
        }
    }

    private function sanitizeAttributes(DOMElement $element, string $tagName): void
    {
        for ($index = $element->attributes->length - 1; $index >= 0; $index--) {
            $attribute = $element->attributes->item($index);

            if ($attribute === null) {
                continue;
            }

            $attributeName = strtolower($attribute->name);

            if ($tagName === 'a' && in_array($attributeName, ['href', 'title'], true)) {
                continue;
            }

            $element->removeAttributeNode($attribute);
        }

        if ($tagName === 'a' && $element->hasAttribute('href') && ! $this->isSafeLink($element->getAttribute('href'))) {
            $element->removeAttribute('href');
        }
    }

    private function isSafeLink(string $url): bool
    {
        $url = trim($url);

        if (
            $url === ''
            || preg_match('/[\x00-\x20\x7f\\\\]/', $url)
            || str_starts_with($url, '//')
        ) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if ($scheme === false) {
            return false;
        }

        return $scheme === null || in_array(strtolower($scheme), ['http', 'https', 'mailto'], true);
    }
}
