# Penn Global Metatags

Schema.org structured data for Penn Drupal sites, built on
[Schema.org Metatag](https://www.drupal.org/project/schema_metatag). It fills
three gaps that module leaves on most sites:

| Gap | What this module adds |
|---|---|
| Breadcrumbs leave out the current page, so top-level pages get a one-item list that Google ignores | `[current-page:schema-breadcrumb]`: Drupal's breadcrumb plus the current page, omitted when under two items (e.g. the front page) |
| WebPage has no `name`, `url`, `isPartOf`, `mainEntity` or dates | Tag plugins for each, with `isPartOf`/`mainEntity` emitted as `{"@id": ...}` references |
| Video embedded as components never becomes a `VideoObject` | `[node:schema-video-*]` tokens built from the first matching embed in the page |

Nothing is tied to one domain: use `[site:url]` and `[site:name]` in metatag
config so each site describes itself.

## Requirements

- `drupal/metatag`, `drupal/schema_metatag` (3.x), `drupal/token`
- Submodules: `schema_web_page`, `schema_video_object` (plus `schema_web_site`
  and `schema_organization` for front-page markup)

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

## Setup

1. Enable the module (this also enables the `schema_web_page`,
   `schema_web_site`, `schema_organization` and `schema_video_object`
   submodules).

   On install it adds its tags to the site's metatag defaults:

   | Defaults | Tags added |
   |---|---|
   | Front page | `EducationalOrganization` and `WebSite` |
   | Content | `WebPage` (name, url, description, isPartOf), breadcrumb, `VideoObject` |
   | Taxonomy term | `CollectionPage`, breadcrumb |

   Only tags a site hasn't set are added; existing values are never
   overwritten. Nothing is added during a config import, since the imported
   config decides. On a site where the module is already installed, run
   `drush pgm-defaults` to add anything missing, then `drush cex`. The full
   list is in `src/DefaultTags.php`.

   Uninstalling removes the module's own tags, plus any standard tag still set
   to the value the module added. Values you changed are kept.
2. Review the tags under Configuration > Search and metadata > Metatag. For
   example, the flagship site might use `CollegeOrUniversity` instead of
   `EducationalOrganization`, and add a logo.
3. Map your video components in `penn_global_metatags.settings`
   (`config/install` ships the Penn upstream's `penn_entity` mappings; replace
   them with your own components):

   | Key | Meaning |
   |---|---|
   | `scan_fields` | Node text fields searched for `<drupal-entity>` embeds |
   | `video_sources[].entity_type` / `bundle` | Which embedded entities count as video |
   | `video_sources[].url_field` | Link or text field holding the video URL |
   | `video_sources[].url_type` | `file` (direct video file, becomes `contentUrl`) or `vimeo` (becomes `embedUrl`, plus Vimeo's duration, upload date and thumbnail) |
   | `video_sources[].poster_field` | Optional media reference used as the thumbnail |
   | `vimeo_referrer` | Production URL, for domain-restricted Vimeo videos |

   Leave decorative clips (autoplaying background loops) out of
   `video_sources`. Every video token is empty on pages without a video, so no
   partial `VideoObject` is rendered.

Don't also enable schema_web_page's own `breadcrumb` tag; it writes the same
property as this module's breadcrumb.

## Notes

- Vimeo metadata is fetched on the first render that needs it and cached for
  a week (failures for an hour).
- Validate with Google's Rich Results Test or https://validator.schema.org.
- Tests: `phpunit -c web/core web/modules/contrib/penn_global_metatags`

## License

GPL-2.0-or-later, like Drupal itself. See `LICENSE.txt`.
