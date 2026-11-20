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

1. Enable the module (this also enables `schema_web_page` and
   `schema_video_object`).
2. Map your video components in `penn_global_metatags.settings`
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
   `video_sources`.
3. Add the tags to your metatag defaults, e.g. on Content:

   ```yaml
   schema_web_page_type: WebPage
   schema_web_page_id: '[current-page:url]#webpage'
   schema_web_page_name: '[node:title]'
   schema_web_page_url: '[node:url]'
   schema_web_page_is_part_of: '[site:url]#website'
   schema_web_page_breadcrumb_trail: '[current-page:schema-breadcrumb]'
   schema_video_object_type: '[node:schema-video-type]'
   schema_video_object_id: '[node:schema-video-id]'
   schema_video_object_name: '[node:schema-video-name]'
   schema_video_object_description: '[node:schema-video-description]'
   schema_video_object_thumbnail_url: '[node:schema-video-thumbnail-url]'
   schema_video_object_upload_date: '[node:schema-video-upload-date]'
   schema_video_object_content_url: '[node:schema-video-content-url]'
   schema_video_object_embed_url: '[node:schema-video-embed-url]'
   schema_video_object_duration: '[node:schema-video-duration]'
   ```

   Every video token is empty on pages without a video, so no partial
   `VideoObject` is rendered. Don't also enable schema_web_page's own
   `breadcrumb` tag; both write the same property.

## Notes

- Vimeo metadata is fetched on the first render that needs it and cached for
  a week (failures for an hour).
- Validate with Google's Rich Results Test or https://validator.schema.org.
- Tests: `phpunit -c web/core web/modules/contrib/penn_global_metatags`

## License

GPL-2.0-or-later, like Drupal itself. See `LICENSE.txt`.
