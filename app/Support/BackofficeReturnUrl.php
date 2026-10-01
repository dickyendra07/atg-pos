<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Carries "where the user came from" through Backoffice edit/create/delete flows.
 *
 * A list page links to its edit page with ?return_to=<that list's own URL, query string included>,
 * the form posts it back as a hidden field, and the controller redirects there after saving. That
 * keeps search / filter / sort / page (and, via the anchor, the record just touched) intact.
 *
 * return_to is user input, so it is only ever accepted as a same-app Backoffice path. Anything else
 * (absolute URLs, protocol-relative URLs, other schemes, other app paths, traversal, control
 * characters, backslashes) is dropped and the caller's own fallback route is used instead.
 */
class BackofficeReturnUrl
{
    public const FIELD = 'return_to';

    private const PREFIX = '/backoffice';

    private const MAX_LENGTH = 2048;

    /**
     * Returns a safe relative URL (path + optional query, no fragment), or null when the value is
     * missing or not an internal Backoffice path.
     */
    public static function sanitize(mixed $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);

        if ($url === '' || strlen($url) > self::MAX_LENGTH) {
            return null;
        }

        // Control characters / whitespace inside the value and backslashes are how browsers get
        // tricked into reading "/\evil.com" or "/\t/evil.com" as a different host.
        if (preg_match('/[\x00-\x20\x7F\\\\]/', $url)) {
            return null;
        }

        // Must be a path on this app: exactly one leading slash (so never "//host").
        if ($url[0] !== '/' || (isset($url[1]) && $url[1] === '/')) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['port'])) {
            return null;
        }

        $path = $parts['path'] ?? '';

        if ($path !== self::PREFIX && ! str_starts_with($path, self::PREFIX.'/')) {
            return null;
        }

        // No traversal, in plain or percent-encoded form, and no encoded slash/backslash tricks.
        $decodedPath = rawurldecode($path);

        if (str_contains($decodedPath, '..') || str_contains($decodedPath, '\\') || str_contains($decodedPath, '//')) {
            return null;
        }

        $safe = $path;

        if (isset($parts['query']) && $parts['query'] !== '') {
            $safe .= '?'.$parts['query'];
        }

        return $safe;
    }

    /**
     * The sanitized return_to submitted with this request (query string or form body).
     */
    public static function fromRequest(?Request $request = null): ?string
    {
        $request ??= request();

        return self::sanitize($request->input(self::FIELD));
    }

    /**
     * The current page's own URL as a return_to value, so a list can pass itself to its edit/create
     * links and delete forms. Null for non-GET requests (nothing sensible to come back to).
     */
    public static function current(?Request $request = null): ?string
    {
        $request ??= request();

        if (! $request->isMethod('GET')) {
            return null;
        }

        return self::sanitize($request->getRequestUri());
    }

    /**
     * $url (an already-safe relative URL) with a fragment pointing at one record's DOM id.
     */
    public static function withAnchor(string $url, ?string $anchor): string
    {
        $url = strtok($url, '#');

        return self::validAnchor($anchor) ? $url.'#'.$anchor : $url;
    }

    /**
     * Where to send the user after a successful action: the sanitized return_to (plus $anchor, the DOM
     * id of the record to bring back into view), otherwise the plain fallback route. Pass a null
     * $anchor when the record may no longer be listed (hard delete) so no dead anchor is produced.
     */
    public static function resolve(?Request $request, string $fallbackRoute, array $fallbackParams = [], ?string $anchor = null): string
    {
        $returnTo = self::fromRequest($request);

        // Without a (valid) return_to this is a direct visit: land on the plain index, as before.
        return $returnTo === null
            ? route($fallbackRoute, $fallbackParams, false)
            : self::withAnchor($returnTo, $anchor);
    }

    public static function redirect(?Request $request, string $fallbackRoute, array $fallbackParams = [], ?string $anchor = null): RedirectResponse
    {
        return redirect(self::resolve($request, $fallbackRoute, $fallbackParams, $anchor));
    }

    /**
     * For redirects that must stay on a Backoffice page (e.g. the Recipe edit page after changing an
     * item): that page's URL with the incoming return_to re-attached, so its Back/Cancel still works.
     */
    public static function routeWithReturn(?Request $request, string $route, array $params = [], ?string $anchor = null): string
    {
        $returnTo = self::fromRequest($request);

        if ($returnTo !== null) {
            $params[self::FIELD] = $returnTo;
        }

        return self::withAnchor(route($route, $params, false), $anchor);
    }

    private static function validAnchor(?string $anchor): bool
    {
        return $anchor !== null && preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,100}$/', $anchor) === 1;
    }
}
