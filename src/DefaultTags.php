<?php

namespace Drupal\penn_global_metatags;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Adds and removes this module's tags in a site's metatag defaults.
 *
 * Metatag owns the defaults config (front, node, taxonomy_term, ...), and
 * every site already has it, so the module cannot ship those files. Instead
 * it merges its tags in on install: only tags a site has not set are added,
 * so existing values are never overwritten.
 */
class DefaultTags {

  /**
   * Tags this module adds, keyed by metatag defaults ID.
   *
   * Values use [site:*] tokens so each site describes itself.
   */
  public const DEFAULTS = [
    'front' => [
      'schema_organization_type' => 'EducationalOrganization',
      'schema_organization_id' => '[site:url]#organization',
      'schema_organization_name' => '[site:name]',
      'schema_organization_url' => '[site:url]',
      'schema_web_site_type' => 'WebSite',
      'schema_web_site_id' => '[site:url]#website',
      'schema_web_site_name' => '[site:name]',
      'schema_web_site_url' => '[site:url]',
    ],
    'node' => [
      'schema_web_page_type' => 'WebPage',
      'schema_web_page_id' => '[current-page:url]#webpage',
      'schema_web_page_name' => '[node:title]',
      'schema_web_page_url' => '[node:url]',
      'schema_web_page_description' => '[node:summary]',
      'schema_web_page_is_part_of' => '[site:url]#website',
      'schema_web_page_breadcrumb_trail' => '[current-page:schema-breadcrumb]',
      'schema_video_object_type' => '[node:schema-video-type]',
      'schema_video_object_id' => '[node:schema-video-id]',
      'schema_video_object_name' => '[node:schema-video-name]',
      'schema_video_object_description' => '[node:schema-video-description]',
      'schema_video_object_thumbnail_url' => '[node:schema-video-thumbnail-url]',
      'schema_video_object_upload_date' => '[node:schema-video-upload-date]',
      'schema_video_object_content_url' => '[node:schema-video-content-url]',
      'schema_video_object_embed_url' => '[node:schema-video-embed-url]',
      'schema_video_object_duration' => '[node:schema-video-duration]',
    ],
    'taxonomy_term' => [
      'schema_web_page_type' => 'CollectionPage',
      'schema_web_page_id' => '[term:url]#webpage',
      'schema_web_page_name' => '[term:name]',
      'schema_web_page_url' => '[term:url]',
      'schema_web_page_description' => '[term:description]',
      'schema_web_page_is_part_of' => '[site:url]#website',
      'schema_web_page_breadcrumb_trail' => '[current-page:schema-breadcrumb]',
    ],
  ];

  /**
   * Tag plugins provided by this module. They stop existing on uninstall, so
   * they are always removed then, whatever their value.
   */
  public const OWN_TAGS = [
    'schema_web_page_name',
    'schema_web_page_url',
    'schema_web_page_is_part_of',
    'schema_web_page_main_entity',
    'schema_web_page_date_created',
    'schema_web_page_date_modified',
    'schema_web_page_breadcrumb_trail',
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Adds missing tags to each metatag defaults entity.
   *
   * @return array<string, string[]>
   *   Tag names added, keyed by defaults ID. A defaults ID the site does not
   *   have is skipped rather than created.
   */
  public function apply(): array {
    $added = [];
    $storage = $this->entityTypeManager->getStorage('metatag_defaults');

    foreach (self::DEFAULTS as $id => $defaults) {
      $entity = $storage->load($id);
      if (!$entity) {
        continue;
      }

      $tags = (array) $entity->get('tags');
      foreach ($defaults as $name => $value) {
        if (!isset($tags[$name]) || $tags[$name] === '') {
          $tags[$name] = $value;
          $added[$id][] = $name;
        }
      }

      if (!empty($added[$id])) {
        $entity->set('tags', $tags);
        $entity->save();
      }
    }

    return $added;
  }

  /**
   * Removes this module's tags from the metatag defaults.
   *
   * Removes this module's own tag plugins, plus any standard tag still set to
   * the value this module added; a value a site changed is left alone.
   *
   * @return array<string, string[]>
   *   Tag names removed, keyed by defaults ID.
   */
  public function remove(): array {
    $removed = [];
    $storage = $this->entityTypeManager->getStorage('metatag_defaults');

    foreach (self::DEFAULTS as $id => $defaults) {
      $entity = $storage->load($id);
      if (!$entity) {
        continue;
      }

      $tags = (array) $entity->get('tags');
      foreach (array_keys($tags) as $name) {
        $ours = in_array($name, self::OWN_TAGS, TRUE)
          || (isset($defaults[$name]) && $tags[$name] === $defaults[$name]);
        if ($ours) {
          unset($tags[$name]);
          $removed[$id][] = $name;
        }
      }

      if (!empty($removed[$id])) {
        $entity->set('tags', $tags);
        $entity->save();
      }
    }

    return $removed;
  }

}
