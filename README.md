# contenir-preview-mezzio

Renders the Contenir CMS's previews in a Mezzio application: the
[preview protocol](https://github.com/contenir/contenir-preview/blob/0.1.x/docs/protocol.md) from
[`contenir/contenir-preview`](https://github.com/contenir/contenir-preview), as PSR-15 middleware.

The CMS posts a signed token for the record being edited to `/api/resource/preview` on the site.
The middleware verifies it before routing, works out which page shows the record, and sends the
request down the pipeline as a visit to that page, which the application renders as it always
would, with the token's values in place of the stored record's. The answer carries the protocol's
headers (never cached, indexed, or framed by anyone but the CMS) and a small "Preview" badge.

## Install

```sh
composer require contenir/contenir-preview-mezzio
```

laminas-component-installer adds `Contenir\Preview\Mezzio\ConfigProvider` to `config/config.php`.
Then pipe the middleware before routing, in `config/pipeline.php`:

```php
$app->pipe(BodyParamsMiddleware::class);   // or any parser that fills the parsed body from a form post
$app->pipe(\Contenir\Preview\Mezzio\PreviewMiddleware::class);
$app->pipe(RouteMiddleware::class);
```

Piping it is what turns previews on: until then the package registers its services and changes
nothing. The middleware needs the PSR-17 response and stream factories, which laminas-diactoros's
`ConfigProvider` registers.

## Configure

```php
// config/autoload/preview.global.php (the secret in preview.local.php, out of version control)
return [
    'contenir_preview' => [
        'issuer'    => 'https://admin.example.com',
        'audience'  => 'https://www.example.com',
        'secrets'   => ['2026-10' => getenv('CONTENIR_PREVIEW_SECRET')],
        'sign_with' => '2026-10',
        'resolvers' => [
            'site.page.home'        => '/{slug}',
            'library.news.articles' => ['route' => 'news.article', 'params' => ['slug' => '{slug}']],
            'site.page.landing'     => App\Preview\LandingResolver::class,
        ],
    ],
];
```

`issuer`, `audience`, `secrets` and `sign_with` are the same on the CMS and the site.
The package registers the services contenir-preview's `ConfigProvider` builds from these settings,
and its own middleware.

The CMS chooses which sections offer a Preview; the site says, per section, which page shows one of
its records. Each resolver is one of:

| Resolver                                         | The page                                                                |
|--------------------------------------------------|-------------------------------------------------------------------------|
| A path, beginning `/`                            | That path, with each `{field}` filled from the record's values.          |
| `['route' => name, 'params' => [...]]`           | The named route, generated with the parameters filled the same way.     |
| The name of a `PreviewResolverInterface` service | Whatever it answers: a path, a route, or a handler (below).             |

A section the CMS previews but the site has no resolver for is answered `404`, "This section has no
preview on this site", as is a path or route the router has no page at.

### Handlers: drafts, new records and workflow sites

A path or route reaches only the pages the router knows. A resolver service can instead answer
`PreviewTarget::handler(SomeHandler::class, ['id' => [...], ...])`: the name of a middleware or
request handler service, and the parameters a route would have given it. That is how to preview a
record not saved yet, a draft at an address not live, or a site whose routes are built from its
published records (contenir-workflow-mezzio).

### Other settings

| Setting            | Default                          |                                                                         |
|--------------------|----------------------------------|-------------------------------------------------------------------------|
| `replay.cache`     | none                             | A PSR-16 cache service; use one when the site runs on several servers. |
| `replay.directory` | the system's temporary directory | Where accepted tokens are remembered otherwise; it must exist.          |
| `clock`            | the system's                     | A PSR-20 clock service.                                                 |
| `marker`           | `true`                           | The visible "Preview" badge on HTML pages.                              |

## Use the preview's values

Wherever the site loads a previewable section's record, lay the preview over it with the
`Contenir\Preview\PreviewContext` service:

```php
$page = $this->preview->overlay('site.page.home', ['resource_id' => $row['resource_id']], $row);
```

`overlay()` returns the stored record unchanged outside a preview, or for any other record. For a
record not saved yet, `$this->preview->unsaved('site.page.home')` gives its values (or `null`), and
`isActive()` tells a preview from a visit. contenir-resource does this for a site's resources.

The context holds the preview for the rest of the request, so the container must serve one
request: as under PHP-FPM, not a long-running server that keeps services between requests.

## How it runs

1. `PreviewMiddleware`, before routing: a request to `/api/resource/preview` is opened. Anything but
   a valid `POST` is answered there and then with the protocol's status and a short page saying
   why.
2. The resolved page becomes the request, as a `GET` with the token's post cleared. A path or route
   moves the request to that address, where the router must have a page. A handler, which no route
   reaches, is carried on the request (`TargetMatcher::HANDLER_ATTRIBUTE`), and the package's
   wrapper around `RouteMiddleware` gives it a route of its own at the preview endpoint for this
   request only (`PreviewRouteMiddleware::HANDLER_ROUTE`), with the target's parameters as route
   parameters. Either way, every middleware after the router sees the page's route result and
   parameters as it would on a visit, the site's own among them (such as contenir-resource-mezzio's,
   which loads the routed resource).
3. The request carries `Cache-Control: no-cache, no-store` in place of the browser's own, so a page
   cache that follows HTTP's rules neither serves a stored copy nor stores the answer: it would
   otherwise see the preview as a visit to the page. A refusal is answered before this and never
   reaches a cache.
4. The application routes, dispatches and renders as normal.
5. As the response comes back, a preview gets the protocol's headers, replacing the page's own, and
   the badge before `</body>`.

## Quality

```sh
composer check          # Mago format, lint and analyze, then the unit and integration tests
composer mutation-test  # Infection
```
