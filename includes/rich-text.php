<?php
declare(strict_types=1);

/**
 * Rich text sanitizer + renderer for blog content.
 * Requires HTMLPurifier in /htmlpurifier/ at project root.
 */

function rich_text_purify(string $html): string
{
    $configFile = ROOT_PATH . '/htmlpurifier/library/HTMLPurifier.auto.php';

    if (!file_exists($configFile)) {
        /*
         * HTMLPurifier is required for sanitizing user-submitted HTML.
         * Silently stripping all tags would corrupt blog content with no warning.
         * Fail loudly so the administrator installs the library before publishing.
         *
         * Install: https://github.com/ezyang/htmlpurifier
         * Extract to: /htmlpurifier/ at project root
         */
        throw new RuntimeException(
            'HTMLPurifier is not installed. Blog content cannot be safely saved. '
            . 'Please install HTMLPurifier at /htmlpurifier/ (see README).'
        );
    }

    require_once $configFile;

    static $purifier = null;

    if ($purifier === null) {
        $config = HTMLPurifier_Config::createDefault();

        /* Cache dir */
        $cacheDir = ROOT_PATH . '/cache/htmlpurifier';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        if (is_dir($cacheDir) && is_writable($cacheDir)) {
            $config->set('Cache.SerializerPath', $cacheDir);
        } else {
            $config->set('Cache.DefinitionImpl', null);
        }

        /* Allowed HTML */
        $config->set('HTML.Allowed',
            'p,br,strong,b,em,i,u,s,blockquote,' .
            'h2,h3,h4,' .
            'ul,ol,li,' .
            'a[href|title|target|rel],' .
            'img[src|alt|title|width|height],' .
            'hr,code,pre'
        );

        /* Auto-add rel="noopener noreferrer" to links */
        $config->set('HTML.TargetBlank', true);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);

        /* Safe URLs only */
        $config->set('URI.AllowedSchemes', [
            'http' => true,
            'https' => true,
            'mailto' => true,
        ]);

        $purifier = new HTMLPurifier($config);
    }

    return $purifier->purify($html);
}

/**
 * Render sanitized HTML content for public display.
 * Splits content into blocks and wraps paragraphs properly.
 */
function rich_text_render(string $html): string
{
    $html = rich_text_purify($html);
    $html = trim($html);

    if ($html === '') {
        return '';
    }

    /* If content has no block tags (p, h2, etc.), wrap in <p> */
    if (!preg_match('/<(p|h[1-6]|ul|ol|blockquote|pre|hr)[\s>]/i', $html)) {
        $paragraphs = preg_split('/\R{2,}/', $html);
        $out = '';
        foreach ($paragraphs as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out .= '<p>' . nl2br($p) . '</p>';
            }
        }
        return $out;
    }

    return $html;
}

/**
 * Extract plain text for excerpt / meta description.
 */
function rich_text_excerpt(string $html, int $length = 180): string
{
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', $text) ?? '';
    return excerpt(trim($text), $length);
}