# Penn Global Metatags

Schema.org structured data for Penn Drupal sites, built on
[Schema.org Metatag](https://www.drupal.org/project/schema_metatag). It works
on any site without configuration and fills the gaps that module leaves:

| Gap | What this module adds |
|---|---|
| Breadcrumbs leave out the current page, so top-level pages get a one-item list that Google ignores | `[current-page:schema-breadcrumb]`: Drupal's breadcrumb plus the current page, omitted when under two items (e.g. the front page) |
| WebPage has no `name`, `url`, `isPartOf`, `mainEntity` or dates | Tag plugins for each, with `isPartOf`/`mainEntity` emitted as `{"@id": ...}` references |
| Every site has to wire up Organization, WebSite and WebPage by hand | Defaults added to the site's metatag config on install |

Nothing is tied to one domain: everything uses `[site:url]` and `[site:name]`,
so each site describes itself.

## Requirements

- `drupal/metatag` 2.x, `drupal/schema_metatag` 3.x, `drupal/token`
- Enabled with it: `schema_web_page`, `schema_web_site`, `schema_organization`

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

| Metatag defaults | Tags added |
|---|---|
| Front page | `EducationalOrganization` and `WebSite` |
| Content | `WebPage` (name, url, description, isPartOf) and breadcrumb |
| Taxonomy term | `CollectionPage` (name, url, description, isPartOf) and breadcrumb |

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
