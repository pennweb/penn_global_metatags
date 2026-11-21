# Penn Global Metatags

Structured data and social sharing defaults for Penn Drupal sites, built on
[Metatag](https://www.drupal.org/project/metatag) and
[Schema.org Metatag](https://www.drupal.org/project/schema_metatag). It works
on any site, and fills the gaps those modules leave:

| Gap | What this module adds |
|---|---|
| Breadcrumbs leave out the current page, so top-level pages get a one-item list that Google ignores | `[current-page:schema-breadcrumb]`: Drupal's breadcrumb plus the current page, omitted when under two items (e.g. the front page) |
| WebPage has no `name`, `url`, `isPartOf`, `mainEntity` or dates | Tag plugins for each, with `isPartOf`/`mainEntity` emitted as `{"@id": ...}` references |
| Every site has to wire up Organization, WebSite, WebPage and social tags by hand | Defaults added to the site's metatag config on install |
| Share images are either missing or hard-coded per site | One default image each site sets; other modules can pick a per-page image |

Nothing is tied to one domain: everything uses `[site:url]` and `[site:name]`,
so each site describes itself.

## Requirements

- `drupal/metatag` 2.x, `drupal/schema_metatag` 3.x, `drupal/token`
- Enabled with it: `metatag_open_graph`, `metatag_twitter_cards`,
  `schema_web_page`, `schema_web_site`, `schema_organization`

## Install

Add this repository to your site's `composer.json`, then require it:

```json
"repositories": [
    {"type": "vcs", "url": "https://github.com/pennweb/penn_global_metatags"}
]
```

```
composer require pennweb/penn_global_metatags:dev-main
drush en penn_global_metatags
```

Composer installs it to `web/modules/contrib/penn_global_metatags` and pulls
in `drupal/schema_metatag`.

## What install adds

| Metatag defaults | Structured data | Social (Open Graph and Twitter cards) |
|---|---|---|
| Global | | Site name, type, URL, title, default share image, `summary_large_image` card |
| Front page | `EducationalOrganization` and `WebSite` | (inherits global) |
| Content | `WebPage` (name, url, description, isPartOf) and breadcrumb | Title, summary, URL, `[node:social-image]` |
| Taxonomy term | `CollectionPage` (name, url, description, isPartOf) and breadcrumb | Term name, description, URL |

- Only tags a site hasn't set are added; existing values are never
  overwritten.
- Nothing is added during a config import, since the imported config decides.
- On a site where the module is already installed, run `drush pgm-defaults`
  to add anything missing, then `drush cex`.
- Uninstalling removes the module's own tags, plus any standard tag still set
  to the value the module added. Values you changed are kept.

The full list is in `src/DefaultTags.php`. Review the result under
Configuration > Search and metadata > Metatag. For example, the flagship site
might use `CollegeOrUniversity` instead of `EducationalOrganization`, and add
a logo.

## Share image

Set your site's default share image at **Configuration > Search and metadata >
Penn Global Metatags** (`/admin/config/search/penn-global-metatags`), or in
`penn_global_metatags.settings`:

```yaml
default_image: /themes/custom/mytheme/share.jpg
default_image_alt: 'Description of the image'
```

It takes an absolute URL, a site path or a URI like `public://share.jpg`. Use a
JPG or PNG at least 1200 x 630; social sites don't show SVG. With no default
set, pages share no image.

On its own, the module shares this one image on every page; it doesn't look at
a node's fields, so it behaves the same on any content type.

To choose a different image, for one content type or for a whole group of
sites, implement `hook_penn_global_metatags_social_image_alter()` (see
`penn_global_metatags.api.php`). It receives the default image and, on content
pages, the node; `[node:social-image]` and `[site:social-image]` both pass
through it.

Don't also enable schema_web_page's own `breadcrumb` tag; it writes the same
property as this module's breadcrumb.

## Using the tags elsewhere

The WebPage tags (`schema_web_page_name`, `_url`, `_is_part_of`,
`_main_entity`, `_date_created`, `_date_modified`, `_breadcrumb_trail`) can be
set on any metatag defaults or per-entity override. For example, a profile
page:

```yaml
schema_web_page_type: ProfilePage
schema_web_page_main_entity: '[current-page:url]#person'
schema_web_page_date_created: '[node:created:custom:c]'
schema_web_page_date_modified: '[node:changed:custom:c]'
```

## Notes

- Validate with Google's Rich Results Test or https://validator.schema.org.
- Tests: `phpunit -c web/core web/modules/contrib/penn_global_metatags`

## License

GPL-2.0-or-later, like Drupal itself. See `LICENSE.txt`.
