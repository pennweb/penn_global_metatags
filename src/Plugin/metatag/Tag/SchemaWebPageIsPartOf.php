<?php

namespace Drupal\penn_global_metatags\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the schema.org WebPage 'isPartOf' meta tag.
 *
 * schema_web_page does not ship an isPartOf tag. Pointing every page at the
 * front page WebSite @id ties the per-page graphs to the site-level entity, so
 * search engines read them as one connected site rather than isolated pages.
 *
 * @MetatagTag(
 *   id = "schema_web_page_is_part_of",
 *   label = @Translation("isPartOf"),
 *   description = @Translation("The @id of the WebSite this page belongs to."),
 *   name = "isPartOf",
 *   group = "schema_web_page",
 *   weight = 4,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "text",
 *   tree_parent = {},
 *   tree_depth = -1,
 * )
 */
class SchemaWebPageIsPartOf extends SchemaNameBase {

  /**
   * {@inheritdoc}
   *
   * Emits {"@id": ...} rather than a bare URL string, so search engines read
   * the value as a reference to another node in the JSON-LD graph instead of
   * plain text.
   */
  public function outputValue($input_value) {
    if (is_string($input_value) && $input_value !== '') {
      return ['@id' => $input_value];
    }

    return parent::outputValue($input_value);
  }

}
